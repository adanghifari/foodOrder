<?php

namespace App\Domains\Chatbot\Services;

use App\Domains\Cart\Services\CartService;
use App\Domains\Payment\Services\PaymentService;
use App\Models\ChatbotMetric;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    private const RESPONSE_VERSION = '1.1';
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];
    private const AI_ALLOWED_INTENTS = [
        'greeting',
        'small_talk',
        'out_of_scope',
        'order_menu',
        'tracking_order',
        'menu_recommendation',
        'view_cart',
        'cart_increase_qty',
        'cart_decrease_qty',
        'clear_cart_request',
        'checkout_request',
        'cancel_order_request',
    ];
    private const AI_MIN_CONFIDENCE_BY_INTENT = [
        'greeting' => 0.7,
        'small_talk' => 0.65,
        'out_of_scope' => 0.75,
        'order_menu' => 0.75,
        'tracking_order' => 0.7,
        'menu_recommendation' => 0.65,
        'view_cart' => 0.75,
        'cart_increase_qty' => 0.75,
        'cart_decrease_qty' => 0.75,
        'clear_cart_request' => 0.8,
        'checkout_request' => 0.75,
        'cancel_order_request' => 0.8,
    ];

    public function __construct(
        private readonly ChatbotIntentService $intentService,
        private readonly CartService $cartService,
        private readonly PaymentService $paymentService,
        private readonly ?GeminiNluService $geminiNluService = null
    ) {
    }

    public function handleMessage(User $user, string $message, string $action = ''): array
    {
        $startedAt = microtime(true);
        $aiPayload = null;
        $intentPayload = $this->intentService->detect($message, $action);
        $intent = (string) ($intentPayload['intent'] ?? 'unknown_or_ambiguous');
        $entities = (array) ($intentPayload['entities'] ?? []);
        if ($intent === 'menu_recommendation' && !isset($entities['query_text'])) {
            $entities['query_text'] = $message;
        }
        $source = 'rule_based';
        $aiConfidence = null;
        $aiDecision = 'not_used';

        $contextGateResponse = $this->contextGateWithGemini($message, $action, $source, $aiConfidence, $aiDecision, $aiPayload);
        if (is_array($contextGateResponse)) {
            $normalized = $this->normalizeResponse($contextGateResponse);
            $this->logTelemetry(
                userId: (string) ($user->_id ?? ''),
                source: $source,
                intentRuleBased: $intent,
                intentResolved: (string) ($normalized['intent'] ?? 'unknown_or_ambiguous'),
                aiDecision: $aiDecision,
                aiConfidence: $aiConfidence,
                action: $action,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000)
            );
            return $normalized;
        }

        if ($intent === 'menu_recommendation') {
            $entities = $this->enrichRecommendationEntitiesFromGemini(
                $message,
                $entities,
                $source,
                $aiConfidence,
                $aiDecision,
                $aiPayload
            );
        }

        $response = match ($intent) {
            'greeting' => $this->greetingResponse(),
            'small_talk' => $this->smallTalkResponse(),
            'tracking_order' => $this->trackingResponse($user),
            'view_cart' => $this->cartResponse($user),
            'cart_increase_qty' => $this->cartAdjustResponse($user, $entities, true),
            'cart_decrease_qty' => $this->cartAdjustResponse($user, $entities, false),
            'clear_cart_request' => $this->clearCartResponse($user),
            'order_menu' => $this->orderMenuResponse($user, $entities),
            'menu_recommendation' => $this->recommendationResponse($entities),
            'checkout_request' => $this->checkoutPromptResponse(),
            'confirm_checkout' => $this->checkoutConfirmResponse(),
            'checkout_type_select' => $this->checkoutTypeSelectedResponse((string) ($entities['checkout_type'] ?? '')),
            'cancel_order_request' => $this->cancelPromptResponse($user),
            'confirm_cancel' => $this->cancelConfirmResponse($user, (string) ($entities['order_id'] ?? '')),
            default => $this->fallbackWithGemini($user, $message, $source, $aiConfidence, $aiDecision, $aiPayload),
        };

        $normalized = $this->normalizeResponse($response);
        $this->logTelemetry(
            userId: (string) ($user->_id ?? ''),
            source: $source,
            intentRuleBased: $intent,
            intentResolved: (string) ($normalized['intent'] ?? 'unknown_or_ambiguous'),
            aiDecision: $aiDecision,
            aiConfidence: $aiConfidence,
            action: $action,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000)
        );

        return $normalized;
    }

    private function contextGateWithGemini(
        string $message,
        string $action,
        string &$source,
        ?float &$aiConfidence,
        string &$aiDecision,
        ?array &$aiPayload
    ): ?array {
        if (trim($action) !== '' || trim($message) === '') {
            return null;
        }

        $geminiService = $this->geminiNluService;
        if ($geminiService === null) {
            try {
                $geminiService = app(GeminiNluService::class);
            } catch (\Throwable) {
                $geminiService = null;
            }
        }

        if (!is_array($aiPayload)) {
            $aiPayload = $geminiService?->detectIntent($message);
        }
        if (!is_array($aiPayload)) {
            return null;
        }

        $errorReason = trim((string) ($aiPayload['error_reason'] ?? ''));
        if ($errorReason !== '') {
            $aiDecision = 'context_gate_' . $errorReason;
            return null;
        }

        $scope = strtolower(trim((string) ($aiPayload['scope'] ?? '')));
        $intent = strtolower(trim((string) ($aiPayload['intent'] ?? '')));
        $confidence = (float) ($aiPayload['confidence'] ?? 0);
        $reasonShort = trim((string) ($aiPayload['reason_short'] ?? ''));

        if (($scope === 'out_of_scope' || $intent === 'out_of_scope') && $confidence >= 0.65) {
            $source = 'gemini_context_gate';
            $aiConfidence = $confidence;
            $aiDecision = 'context_gate_out_of_scope';
            return $this->outOfScopeResponse($reasonShort);
        }

        return null;
    }

    private function enrichRecommendationEntitiesFromGemini(
        string $message,
        array $baseEntities,
        string &$source,
        ?float &$aiConfidence,
        string &$aiDecision,
        ?array &$aiPayload
    ): array {
        $text = trim($message);
        if ($text === '') {
            $aiDecision = 'recommendation_rule_based_no_message';
            return $baseEntities;
        }

        $geminiService = $this->geminiNluService;
        if ($geminiService === null) {
            try {
                $geminiService = app(GeminiNluService::class);
            } catch (\Throwable) {
                $geminiService = null;
            }
        }

        if (!is_array($aiPayload)) {
            $aiPayload = $geminiService?->detectIntent($text);
        }
        if (!is_array($aiPayload)) {
            $aiDecision = 'recommendation_no_ai_payload';
            return $baseEntities;
        }

        $errorReason = trim((string) ($aiPayload['error_reason'] ?? ''));
        if ($errorReason !== '') {
            $aiDecision = 'recommendation_' . $errorReason;
            return $baseEntities;
        }

        $intent = strtolower(trim((string) ($aiPayload['intent'] ?? '')));
        if ($intent !== 'menu_recommendation') {
            $aiDecision = 'recommendation_intent_mismatch';
            return $baseEntities;
        }

        $confidence = (float) ($aiPayload['confidence'] ?? 0);
        $aiConfidence = $confidence;
        if ($confidence < 0.55) {
            $aiDecision = 'recommendation_below_threshold';
            return $baseEntities;
        }

        $sanitized = $this->sanitizeAiEntities(
            'menu_recommendation',
            is_array($aiPayload['entities'] ?? null) ? $aiPayload['entities'] : []
        );

        $merged = $baseEntities;
        foreach (['taste', 'taste_intensity', 'category', 'calorie_level', 'query_text'] as $key) {
            $value = trim((string) ($sanitized[$key] ?? ''));
            if ($value !== '') {
                $merged[$key] = $value;
            }
        }

        if (isset($sanitized['max_price']) && (int) $sanitized['max_price'] > 0) {
            $merged['max_price'] = (int) $sanitized['max_price'];
        }
        if (($sanitized['light'] ?? false) === true) {
            $merged['light'] = true;
        }
        if (($sanitized['filling'] ?? false) === true) {
            $merged['filling'] = true;
        }
        if (!empty($sanitized['required_tags']) && is_array($sanitized['required_tags'])) {
            $merged['required_tags'] = $sanitized['required_tags'];
        }
        $aiConversationalReply = trim((string) ($sanitized['conversational_reply'] ?? ''));
        if ($aiConversationalReply !== '') {
            $merged['conversational_reply'] = $aiConversationalReply;
        }
        $merged['needs_clarification'] = (bool) ($aiPayload['needs_clarification'] ?? false);

        if (!isset($merged['query_text']) || trim((string) $merged['query_text']) === '') {
            $merged['query_text'] = $text;
        }

        $source = 'gemini_recommendation_nlu';
        $aiDecision = 'recommendation_nlu_used';
        return $merged;
    }

    private function fallbackWithGemini(
        User $user,
        string $message,
        string &$source,
        ?float &$aiConfidence,
        string &$aiDecision,
        ?array &$aiPayload
    ): array
    {
        $geminiService = $this->geminiNluService;
        if ($geminiService === null) {
            try {
                $geminiService = app(GeminiNluService::class);
            } catch (\Throwable) {
                $geminiService = null;
            }
        }

        if (!is_array($aiPayload)) {
            $aiPayload = $geminiService?->detectIntent($message);
        }
        if (!is_array($aiPayload)) {
            $aiDecision = 'no_ai_payload';
            return $this->fallbackResponse('no_ai_payload');
        }

        $errorReason = trim((string) ($aiPayload['error_reason'] ?? ''));
        if ($errorReason !== '') {
            $aiDecision = $errorReason;
            return $this->fallbackResponse($errorReason);
        }

        $intent = strtolower(trim((string) ($aiPayload['intent'] ?? '')));
        $confidence = (float) ($aiPayload['confidence'] ?? 0);
        $entities = $this->sanitizeAiEntities(
            $intent,
            is_array($aiPayload['entities'] ?? null) ? $aiPayload['entities'] : []
        );
        $aiConfidence = $confidence;

        if (!in_array($intent, self::AI_ALLOWED_INTENTS, true)) {
            $aiDecision = 'intent_not_allowed';
            return $this->fallbackResponse();
        }

        $minConfidence = self::AI_MIN_CONFIDENCE_BY_INTENT[$intent] ?? 0.8;
        if ($confidence < $minConfidence) {
            $aiDecision = 'below_threshold';
            return $this->fallbackResponse();
        }

        $source = 'gemini_fallback';
        $aiDecision = 'used';

        return match ($intent) {
            'greeting' => $this->greetingResponse(),
            'small_talk' => $this->smallTalkResponse(),
            'out_of_scope' => $this->outOfScopeResponse(),
            'tracking_order' => $this->trackingResponse($user),
            'view_cart' => $this->cartResponse($user),
            'cart_increase_qty' => $this->cartAdjustResponse($user, $entities, true),
            'cart_decrease_qty' => $this->cartAdjustResponse($user, $entities, false),
            'clear_cart_request' => $this->clearCartResponse($user),
            'order_menu' => $this->orderMenuResponse($user, $entities),
            'menu_recommendation' => $this->recommendationResponse($entities),
            'checkout_request' => $this->checkoutPromptResponse(),
            'cancel_order_request' => $this->cancelPromptResponse($user),
            default => $this->fallbackResponse(),
        };
    }

    private function sanitizeAiEntities(string $intent, array $entities): array
    {
        if ($intent === 'order_menu') {
            $menuName = trim((string) ($entities['menu_name'] ?? ''));
            $quantity = (int) ($entities['quantity'] ?? 0);
            return [
                'menu_name' => $menuName,
                'quantity' => $quantity > 0 && $quantity <= 20 ? $quantity : null,
            ];
        }

        if ($intent === 'menu_recommendation') {
            $taste = trim((string) ($entities['taste'] ?? ''));
            $maxPrice = (int) ($entities['max_price'] ?? 0);
            $calorieLevel = trim((string) ($entities['calorie_level'] ?? ''));
            $queryText = trim((string) ($entities['query_text'] ?? ''));
            $category = trim((string) ($entities['category'] ?? ''));
            $tasteIntensity = trim((string) ($entities['taste_intensity'] ?? ''));
            $conversationalReply = trim((string) ($entities['conversational_reply'] ?? ''));
            $needsClarification = (bool) ($entities['needs_clarification'] ?? false);
            $requiredTags = is_array($entities['required_tags'] ?? null) ? $entities['required_tags'] : [];
            $allowedTags = $this->getAllowedMenuTags();
            return [
                'taste' => in_array($taste, ['spicy', 'sweet', 'fresh'], true) ? $taste : null,
                'taste_intensity' => in_array($tasteIntensity, ['normal', 'high'], true) ? $tasteIntensity : 'normal',
                'category' => in_array($category, ['makanan utama', 'cemilan', 'minuman'], true) ? $category : null,
                'light' => (bool) ($entities['light'] ?? false),
                'filling' => (bool) ($entities['filling'] ?? false),
                'conversational_reply' => mb_substr($conversationalReply, 0, 280),
                'needs_clarification' => $needsClarification,
                'required_tags' => array_values(array_filter(array_map(
                    fn ($tag) => trim(strtolower((string) $tag)),
                    $requiredTags
                ), fn ($tag) => in_array($tag, $allowedTags, true))),
                'calorie_level' => in_array($calorieLevel, ['low', 'medium', 'high'], true) ? $calorieLevel : null,
                'max_price' => $maxPrice > 0 && $maxPrice <= 500000 ? $maxPrice : null,
                'query_text' => $queryText,
            ];
        }

        return [];
    }

    private function getAllowedMenuTags(): array
    {
        try {
            $tags = config('menu_taxonomy.allowed_tags', []);
            return is_array($tags) ? $tags : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function greetingResponse(): array
    {
        return [
            'reply' => 'Halo! Saya bisa bantu pesan makanan, tracking pesanan, rekomendasi menu, atau lihat keranjang.',
            'intent' => 'greeting',
            'data' => null,
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Pesan Makanan', 'value' => 'greeting_order'],
                ['type' => 'quick_reply', 'label' => 'Tracking Pesanan', 'value' => 'greeting_tracking'],
                ['type' => 'quick_reply', 'label' => 'Rekomendasi Menu', 'value' => 'greeting_recommendation'],
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
            ],
        ];
    }

    private function smallTalkResponse(): array
    {
        return [
            'reply' => 'Siap, sama-sama. Kalau kamu mau, saya bisa bantu rekomendasi menu, lihat keranjang, atau tracking pesanan.',
            'intent' => 'small_talk',
            'data' => null,
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Rekomendasi Menu', 'value' => 'greeting_recommendation'],
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
                ['type' => 'quick_reply', 'label' => 'Tracking Pesanan', 'value' => 'greeting_tracking'],
            ],
        ];
    }

    private function outOfScopeResponse(string $reasonShort = ''): array
    {
        $reply = 'Topik itu di luar konteks restoran ya. Saya fokus bantu seputar menu, pemesanan, keranjang, checkout, dan tracking pesanan.';
        if ($reasonShort !== '') {
            $reply = 'Sepertinya itu di luar konteks restoran (' . $reasonShort . '). Saya fokus bantu menu, pemesanan, keranjang, checkout, dan tracking pesanan.';
        }
        return [
            'reply' => $reply,
            'intent' => 'out_of_scope',
            'data' => null,
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Pesan Makanan', 'value' => 'greeting_order'],
                ['type' => 'quick_reply', 'label' => 'Rekomendasi Menu', 'value' => 'greeting_recommendation'],
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
            ],
        ];
    }

    private function cartResponse(User $user): array
    {
        $items = $this->cartService->getCartData((string) $user->_id);
        $total = (float) $items->sum('subtotal');

        if ($items->isEmpty()) {
            return [
                'reply' => 'Keranjang kamu masih kosong.',
                'intent' => 'view_cart',
                'data' => ['items' => [], 'total' => 0],
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Lihat Menu', 'value' => 'greeting_order'],
                ],
            ];
        }

        return [
            'reply' => 'Ini isi keranjang kamu saat ini.',
            'intent' => 'view_cart',
            'data' => [
                'items' => $items->values()->all(),
                'total' => $total,
            ],
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Ke Keranjang', 'value' => 'nav_cart_read_only'],
                ['type' => 'quick_reply', 'label' => 'Checkout', 'value' => 'confirm_checkout'],
            ],
            'cards' => [
                [
                    'type' => 'order_summary_card',
                    'items' => $items->values()->all(),
                    'total' => $total,
                ],
            ],
        ];
    }

    private function cartAdjustResponse(User $user, array $entities, bool $isIncrease): array
    {
        $items = $this->cartService->getCartData((string) $user->_id);
        if ($items->isEmpty()) {
            return [
                'reply' => 'Keranjang kamu masih kosong.',
                'intent' => $isIncrease ? 'cart_increase_qty' : 'cart_decrease_qty',
                'data' => null,
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Pesan Makanan', 'value' => 'greeting_order'],
                ],
            ];
        }

        $menuId = trim((string) ($entities['menu_id'] ?? ''));
        $menuName = trim((string) ($entities['menu_name'] ?? ''));
        $delta = max(1, (int) ($entities['quantity'] ?? 1));

        $matched = null;
        if ($menuId !== '') {
            $matched = $items->first(fn ($item) => (string) ($item['menuId'] ?? '') === $menuId);
        } elseif ($menuName !== '') {
            $matched = $items->first(function ($item) use ($menuName) {
                $name = strtolower((string) ($item['name'] ?? ''));
                return str_contains($name, strtolower($menuName));
            });
        }

        if ($matched === null) {
            if ($items->count() > 1) {
                return [
                    'reply' => 'Di keranjang kamu ada lebih dari satu menu. Mau ' . ($isIncrease ? 'tambah' : 'kurangi') . ' yang mana? Balas sesuai nama di ringkasan pesanan.',
                    'intent' => $isIncrease ? 'cart_increase_qty' : 'cart_decrease_qty',
                    'data' => [
                        'requires_menu_selection' => true,
                        'operation' => $isIncrease ? 'increase' : 'decrease',
                        'quantity' => $delta,
                    ],
                    'actions' => $items->take(5)->map(function ($item) use ($isIncrease, $delta) {
                        $id = (string) ($item['menuId'] ?? '');
                        $name = (string) ($item['name'] ?? '-');
                        return [
                            'type' => 'quick_reply',
                            'label' => $name,
                            'value' => ($isIncrease ? 'cart_increase:' : 'cart_decrease:') . $id . ':' . $delta,
                        ];
                    })->values()->all(),
                ];
            }
            $matched = $items->first();
        }

        $targetMenuId = (string) ($matched['menuId'] ?? '');
        $targetName = (string) ($matched['name'] ?? '-');
        $currentQty = max(0, (int) ($matched['quantity'] ?? 0));
        $nextQty = $isIncrease ? ($currentQty + $delta) : ($currentQty - $delta);

        if ($nextQty <= 0) {
            $remove = $this->cartService->removeItem((string) $user->_id, $targetMenuId);
            if (!($remove['ok'] ?? false)) {
                return [
                    'reply' => (string) ($remove['message'] ?? 'Gagal memperbarui keranjang.'),
                    'intent' => $isIncrease ? 'cart_increase_qty' : 'cart_decrease_qty',
                    'data' => null,
                    'actions' => [],
                ];
            }

            return [
                'reply' => $targetName . ' dihapus dari keranjang.',
                'intent' => $isIncrease ? 'cart_increase_qty' : 'cart_decrease_qty',
                'data' => [
                    'menu_id' => $targetMenuId,
                    'menu_name' => $targetName,
                    'quantity' => 0,
                ],
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
                ],
            ];
        }

        $result = $this->cartService->addOrUpdateItem((string) $user->_id, $targetMenuId, $nextQty);
        if (!($result['ok'] ?? false)) {
            return [
                'reply' => (string) ($result['message'] ?? 'Gagal memperbarui keranjang.'),
                'intent' => $isIncrease ? 'cart_increase_qty' : 'cart_decrease_qty',
                'data' => null,
                'actions' => [],
            ];
        }

        return [
            'reply' => $targetName . ' sekarang jadi ' . $nextQty . ' porsi di keranjang.',
            'intent' => $isIncrease ? 'cart_increase_qty' : 'cart_decrease_qty',
            'data' => [
                'menu_id' => $targetMenuId,
                'menu_name' => $targetName,
                'quantity' => $nextQty,
            ],
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
            ],
        ];
    }

    private function clearCartResponse(User $user): array
    {
        $items = $this->cartService->getCartData((string) $user->_id);
        if ($items->isEmpty()) {
            return [
                'reply' => 'Keranjang kamu sudah kosong.',
                'intent' => 'clear_cart_request',
                'data' => null,
                'actions' => [],
            ];
        }

        $menuIds = $items->map(fn ($item) => (string) ($item['menuId'] ?? ''))
            ->filter(fn ($id) => $id !== '')
            ->values();

        foreach ($menuIds as $menuId) {
            $this->cartService->removeItem((string) $user->_id, (string) $menuId);
        }

        return [
            'reply' => 'Keranjang berhasil dikosongkan.',
            'intent' => 'clear_cart_request',
            'data' => ['cleared' => true],
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Pesan Makanan', 'value' => 'greeting_order'],
            ],
        ];
    }

    private function orderMenuResponse(User $user, array $entities): array
    {
        $menuId = trim((string) ($entities['menu_id'] ?? ''));
        $menuName = trim((string) ($entities['menu_name'] ?? ''));
        $quantity = isset($entities['quantity']) ? (int) $entities['quantity'] : null;

        if ($menuId === '' && $menuName === '') {
            return [
                'reply' => 'Mau pesan menu apa? Contoh: "ayam geprek 2".',
                'intent' => 'order_menu',
                'data' => null,
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Rekomendasi Menu', 'value' => 'greeting_recommendation'],
                ],
            ];
        }

        $menu = $menuId !== ''
            ? MenuItem::find($menuId)
            : MenuItem::where('name', 'like', '%' . $menuName . '%')->orderBy('_id', 'asc')->first();
        if (!$menu) {
            $firstWord = trim((string) explode(' ', $menuName)[0]);
            $alternatives = $firstWord !== ''
                ? MenuItem::where('name', 'like', '%' . $firstWord . '%')->limit(3)->get()
                : collect();

            return [
                'reply' => 'Menu tidak ditemukan. Coba salah satu menu serupa berikut.',
                'intent' => 'order_menu',
                'data' => [
                    'query' => $menuName,
                    'alternatives' => $alternatives->map(fn ($item) => $this->mapMenuCard($item))->values()->all(),
                ],
                'actions' => [],
            ];
        }

        if ($quantity === null) {
            return [
                'reply' => $menu->name . ' tersedia. Mau pesan berapa porsi?',
                'intent' => 'order_menu',
                'data' => [
                    'menu_id' => (string) $menu->_id,
                    'menu_name' => (string) $menu->name,
                    'price' => (float) $menu->price,
                    'quantity' => null,
                ],
                'actions' => [
                    ['type' => 'quick_reply', 'label' => '1 porsi', 'value' => 'qty_1:' . (string) $menu->_id],
                    ['type' => 'quick_reply', 'label' => '2 porsi', 'value' => 'qty_2:' . (string) $menu->_id],
                    ['type' => 'quick_reply', 'label' => '3 porsi', 'value' => 'qty_3:' . (string) $menu->_id],
                ],
            ];
        }

        $result = $this->cartService->addOrUpdateItem((string) $user->_id, (string) $menu->_id, $quantity);
        if (!($result['ok'] ?? false)) {
            return [
                'reply' => (string) ($result['message'] ?? 'Gagal menambahkan item ke keranjang.'),
                'intent' => 'order_menu',
                'data' => null,
                'actions' => [],
            ];
        }

        return [
            'reply' => $menu->name . ' x' . $quantity . ' berhasil ditambahkan ke keranjang.',
            'intent' => 'order_menu',
            'data' => [
                'menu_id' => (string) $menu->_id,
                'menu_name' => (string) $menu->name,
                'price' => (float) $menu->price,
                'quantity' => $quantity,
            ],
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
                ['type' => 'quick_reply', 'label' => 'Checkout', 'value' => 'confirm_checkout'],
            ],
        ];
    }

    private function recommendationResponse(array $entities): array
    {
        $taste = (string) ($entities['taste'] ?? '');
        $tasteIntensity = (string) ($entities['taste_intensity'] ?? 'normal');
        $category = (string) ($entities['category'] ?? '');
        $lightRequested = ($entities['light'] ?? false) === true;
        $fillingRequested = ($entities['filling'] ?? false) === true;
        $queryKeywords = $this->extractQueryKeywords((string) ($entities['query_text'] ?? ''));
        $queryText = strtolower(trim((string) ($entities['query_text'] ?? '')));
        $maxPrice = isset($entities['max_price']) ? (int) $entities['max_price'] : null;
        $calorieLevel = (string) ($entities['calorie_level'] ?? '');
        $requiredTags = is_array($entities['required_tags'] ?? null) ? $entities['required_tags'] : [];
        $aiConversationalReply = trim((string) ($entities['conversational_reply'] ?? ''));
        $needsClarification = (bool) ($entities['needs_clarification'] ?? false);
        $hasUncertaintySignal = $this->containsAnyPhrase($queryText, [
            'bingung',
            'makan apa',
            'enaknya apa',
            'gatau',
            'ga tau',
            'tidak tahu',
            'belum tau',
            'belum tahu',
        ]);
        $hasStructuredSignal = $taste !== ''
            || $category !== ''
            || $lightRequested
            || $fillingRequested
            || !empty($requiredTags)
            || ($maxPrice !== null && $maxPrice > 0)
            || $calorieLevel !== '';

        $hasFreeTextSignal = !empty($queryKeywords);
        if ($needsClarification || $hasUncertaintySignal || (!$hasStructuredSignal && !$hasFreeTextSignal)) {
            return [
                'reply' => $aiConversationalReply !== ''
                    ? $aiConversationalReply
                    : 'Boleh, kamu maunya yang seperti apa dulu? Misalnya pedas/manis, makanan/minuman, dan kisaran budget.',
                'intent' => 'menu_recommendation',
                'data' => ['clarification_required' => true],
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Pedas', 'value' => 'pedas'],
                    ['type' => 'quick_reply', 'label' => 'Manis', 'value' => 'manis'],
                    ['type' => 'quick_reply', 'label' => 'Minuman Segar', 'value' => 'minuman segar'],
                ],
            ];
        }

        $hasSignal = $hasStructuredSignal || !empty($queryKeywords);
        if (!$hasSignal) {
            return [
                'reply' => $aiConversationalReply !== ''
                    ? $aiConversationalReply
                    : 'Boleh, kamu lagi cari yang seperti apa? Coba tulis: "pedas murah", "manis ringan", atau "mengenyangkan 25000".',
                'intent' => 'menu_recommendation',
                'data' => null,
                'actions' => [],
            ];
        }

        $query = MenuItem::query();

        if ($maxPrice !== null && $maxPrice > 0) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($calorieLevel !== '') {
            $query->where('calorie_level', $calorieLevel);
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        foreach ($requiredTags as $tag) {
            $normalizedTag = (string) $tag;
            if ($normalizedTag === 'sharing_bersama') {
                $query->where(function ($builder) {
                    $builder->where('tags', 'sharing_bersama')
                        ->orWhere('tags', 'cocok_berbagi');
                });
                continue;
            }
            $query->where('tags', $normalizedTag);
        }

        // Hard business rule:
        // If user asks spicy, only spicy candidates are allowed.
        if ($taste === 'spicy') {
            $query->where(function ($builder) use ($tasteIntensity) {
                if ($tasteIntensity === 'high') {
                    $builder->where('spice_level', '>=', 4)
                        ->orWhere('tags', 'pedas');
                    return;
                }
                $builder->where('spice_level', '>', 0)
                    ->orWhere('tags', 'pedas');
            });
        }

        if ($taste === 'sweet') {
            $query->where(function ($builder) use ($tasteIntensity) {
                if ($tasteIntensity === 'high') {
                    $builder->where('sweet_level', '>=', 4)
                        ->orWhere('tags', 'manis');
                    return;
                }
                $builder->where('sweet_level', '>', 0)
                    ->orWhere('tags', 'manis');
            });
        }

        if ($taste === 'fresh') {
            $query->where(function ($builder) {
                $builder->where('tags', 'segar')
                    ->orWhere('name', 'like', '%es%')
                    ->orWhere('description', 'like', '%segar%');
            });
        }

        $menus = $query->where('stock', '>', 0)->limit(80)->get();
        $scored = $menus
            ->map(function (MenuItem $item) use ($lightRequested, $fillingRequested, $taste, $queryKeywords, $tasteIntensity) {
                $score = 0;
                $tags = $this->normalizeMenuTags($item);

                if ($taste === 'spicy') {
                    $spiceLevel = (int) ($item->spice_level ?? 0);
                    if ($spiceLevel > 0) {
                        $score += 4 + min(4, $spiceLevel);
                    }
                    if (in_array('pedas', $tags, true)) {
                        $score += 3;
                    }
                    if ($tasteIntensity === 'high' && $spiceLevel >= 4) {
                        $score += 4;
                    }
                }

                if ($taste === 'sweet') {
                    $sweetLevel = (int) ($item->sweet_level ?? 0);
                    if ($sweetLevel > 0) {
                        $score += 4 + min(4, $sweetLevel);
                    }
                    if (in_array('manis', $tags, true)) {
                        $score += 3;
                    }
                    if ($tasteIntensity === 'high' && $sweetLevel >= 4) {
                        $score += 4;
                    }
                }

                if ($taste === 'fresh' && in_array('segar', $tags, true)) {
                    $score += 5;
                }

                if ($lightRequested && in_array('ringan', $tags, true)) {
                    $score += 3;
                }

                if ($fillingRequested && in_array('mengenyangkan', $tags, true)) {
                    $score += 3;
                }

                $score += $this->scoreKeywordOverlap($item, $queryKeywords, $tags);

                if (is_string($item->recommendation_note ?? null) && trim((string) $item->recommendation_note) !== '') {
                    $score += 1;
                    $score += $this->scoreRecommendationNote((string) $item->recommendation_note, $queryKeywords);
                }

                return [
                    'item' => $item,
                    'score' => $score,
                    'price' => (float) ($item->price ?? 0),
                ];
            })
            ->sort(function (array $a, array $b): int {
                if ($a['score'] === $b['score']) {
                    return $a['price'] <=> $b['price'];
                }
                return $b['score'] <=> $a['score'];
            })
            ->values();

        $minScore = ($taste !== '' || $category !== '' || $lightRequested || $fillingRequested || !empty($requiredTags) || $calorieLevel !== '')
            ? 3
            : 1;

        $menus = $scored
            ->filter(fn (array $row) => $row['score'] >= $minScore)
            ->pluck('item')
            ->take(5);

        if ($menus->isEmpty()) {
            return [
                'reply' => 'Menu yang anda inginkan belum tersedia, silahkan explore menu agar sesuai dengan yang anda mau.',
                'intent' => 'menu_recommendation',
                'data' => ['items' => []],
                'actions' => [],
            ];
        }

        $orderActions = $menus->take(3)->map(function (MenuItem $item) {
            $name = trim((string) ($item->name ?? ''));
            return [
                'type' => 'quick_reply',
                'label' => 'Pesan ' . ($name !== '' ? $name : 'Menu'),
                'value' => 'suggest_menu:' . (string) $item->_id,
            ];
        })->values()->all();

        return [
            'reply' => $aiConversationalReply !== '' ? $aiConversationalReply : 'Ini rekomendasi menu yang cocok untuk kamu.',
            'intent' => 'menu_recommendation',
            'data' => [
                'items' => $menus->map(fn ($item) => $this->mapMenuCard($item))->values()->all(),
            ],
            'actions' => array_merge($orderActions, [
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
            ]),
            'cards' => $menus->map(fn ($item) => ['type' => 'menu_card', 'menu' => $this->mapMenuCard($item)])->values()->all(),
        ];
    }

    private function normalizeMenuTags(MenuItem $item): array
    {
        $raw = $item->tags ?? [];
        if (!is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn ($tag) => trim(strtolower((string) $tag)))
            ->filter(fn ($tag) => $tag !== '')
            ->values()
            ->all();
    }

    private function extractQueryKeywords(string $query): array
    {
        $normalized = strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized);
        $parts = preg_split('/\s+/', (string) $normalized) ?: [];
        $stopwords = [
            'yang', 'dan', 'atau', 'dengan', 'untuk', 'saya', 'aku', 'mau',
            'menu', 'rekomendasi', 'apa', 'ada', 'nya', 'nih', 'dong',
            'murah', 'mahal', 'paling', 'buat', 'lagi', 'enaknya', 'enak',
            'cari', 'minta', 'dong', 'nih', 'kak', 'bang', 'mba',
        ];

        return collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter(fn ($part) => $part !== '' && strlen($part) >= 2 && !in_array($part, $stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function scoreRecommendationNote(string $note, array $queryKeywords): int
    {
        if (empty($queryKeywords)) {
            return 0;
        }

        $normalizedNote = strtolower($note);
        $hits = 0;
        foreach ($queryKeywords as $keyword) {
            if (str_contains($normalizedNote, (string) $keyword)) {
                $hits++;
            }
        }

        return min(4, $hits * 2);
    }

    private function scoreKeywordOverlap(MenuItem $item, array $queryKeywords, array $tags): int
    {
        if (empty($queryKeywords)) {
            return 0;
        }

        $haystack = strtolower(
            trim(
                implode(' ', [
                    (string) ($item->name ?? ''),
                    (string) ($item->description ?? ''),
                    implode(' ', $tags),
                    (string) ($item->recommendation_note ?? ''),
                ])
            )
        );

        if ($haystack === '') {
            return 0;
        }

        $score = 0;
        foreach ($queryKeywords as $keyword) {
            if (str_contains($haystack, (string) $keyword)) {
                $score += strlen((string) $keyword) >= 4 ? 3 : 2;
            }
        }

        return min(12, $score);
    }

    private function containsAnyPhrase(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if ($phrase !== '' && str_contains($text, strtolower((string) $phrase))) {
                return true;
            }
        }
        return false;
    }

    private function trackingResponse(User $user): array
    {
        $businessTimezone = 'Asia/Jakarta';
        $todayStart = Carbon::now($businessTimezone)->startOfDay()->utc();
        $todayEnd = Carbon::now($businessTimezone)->endOfDay()->utc();

        $orders = Order::where('customer_id', (string) $user->_id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->orderBy('_id', 'desc')
            ->limit(10)
            ->get();

        if ($orders->isEmpty()) {
            return [
                'reply' => 'Belum ada pesanan kamu untuk hari ini.',
                'intent' => 'tracking_order',
                'data' => ['orders' => []],
                'actions' => [],
            ];
        }

        $cards = $orders->map(function (Order $order) {
            $statusRaw = strtoupper((string) ($order->status ?? 'PENDING_PAYMENT'));
            $createdAt = $order->created_at ? Carbon::parse($order->created_at)->setTimezone('Asia/Jakarta') : null;
            return [
                'type' => 'tracking_status_card',
                'order_id' => (string) $order->_id,
                'status' => strtolower($statusRaw),
                'status_label' => $this->mapOrderStatusLabel($statusRaw),
                'tracking_date_label' => $createdAt?->translatedFormat('d M Y'),
                'payment_status' => strtoupper((string) ($order->payment_status ?? 'PENDING')),
                'queue_number' => (int) ($order->queue_number ?? 0),
                'total_price' => (float) ($order->total_price ?? 0),
                'created_at' => $createdAt?->toDateTimeString(),
            ];
        })->values()->all();

        return [
            'reply' => 'Ini status pesanan terbaru kamu.',
            'intent' => 'tracking_order',
            'data' => ['orders' => $cards],
            'actions' => [],
            'cards' => $cards,
        ];
    }

    private function checkoutPromptResponse(): array
    {
        return [
            'reply' => 'Sebelum checkout, pastikan isi keranjang sudah sesuai. Lanjut checkout sekarang?',
            'intent' => 'checkout_request',
            'data' => null,
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Lanjut Checkout', 'value' => 'confirm_checkout'],
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
            ],
        ];
    }

    private function checkoutConfirmResponse(): array
    {
        return [
            'reply' => 'Pilih tipe order untuk lanjut checkout.',
            'intent' => 'confirm_checkout',
            'data' => ['requires_checkout_type_selection' => true],
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Dine In On The Spot (QR)', 'value' => 'checkout_type:dine_in_qr'],
                ['type' => 'quick_reply', 'label' => 'Dine In Booking', 'value' => 'checkout_type:booking_dine_in'],
                ['type' => 'quick_reply', 'label' => 'Pickup', 'value' => 'checkout_type:pickup'],
                ['type' => 'quick_reply', 'label' => 'Takeaway (QR)', 'value' => 'checkout_type:takeaway_qr'],
            ],
        ];
    }

    private function checkoutTypeSelectedResponse(string $checkoutType): array
    {
        $type = strtolower(trim($checkoutType));

        return match ($type) {
            'dine_in_qr' => [
                'reply' => 'Silakan scan QR meja untuk lanjut Dine In On The Spot.',
                'intent' => 'checkout_type_select',
                'data' => [
                    'checkout_type' => $type,
                    'requires_qr_scan' => true,
                ],
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Scan QR Sekarang', 'value' => 'nav_scan_qr_dine_in'],
                ],
            ],
            'takeaway_qr' => [
                'reply' => 'Silakan scan QR takeaway untuk lanjut checkout.',
                'intent' => 'checkout_type_select',
                'data' => [
                    'checkout_type' => $type,
                    'requires_qr_scan' => true,
                ],
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Scan QR Takeaway', 'value' => 'nav_scan_qr_takeaway'],
                ],
            ],
            'booking_dine_in' => [
                'reply' => 'Siap. Kamu akan diarahkan ke checkout Dine In Booking.',
                'intent' => 'checkout_type_select',
                'data' => [
                    'checkout_type' => $type,
                    'preferred_order_type' => 'booking_dine_in',
                ],
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Buka Checkout', 'value' => 'nav_checkout_booking_dine_in'],
                ],
            ],
            'pickup' => [
                'reply' => 'Siap. Kamu akan diarahkan ke checkout Pickup.',
                'intent' => 'checkout_type_select',
                'data' => [
                    'checkout_type' => $type,
                    'preferred_order_type' => 'pickup',
                ],
                'actions' => [
                    ['type' => 'quick_reply', 'label' => 'Buka Checkout', 'value' => 'nav_checkout_pickup'],
                ],
            ],
            default => $this->checkoutConfirmResponse(),
        };
    }

    private function cancelPromptResponse(User $user): array
    {
        $order = $this->findLatestUnpaidOrder($user);
        if (!$order) {
            return [
                'reply' => 'Tidak ada pesanan yang bisa dibatalkan. Pembatalan hanya untuk pesanan yang belum lunas.',
                'intent' => 'cancel_order_request',
                'data' => null,
                'actions' => [],
            ];
        }

        return [
            'reply' => 'Pesanan #' . strtoupper(substr((string) $order->_id, -6)) . ' belum lunas. Yakin ingin membatalkan pembayaran pesanan ini?',
            'intent' => 'cancel_order_request',
            'data' => [
                'order_id' => (string) $order->_id,
                'payment_status' => strtoupper((string) ($order->payment_status ?? 'PENDING')),
            ],
            'actions' => [
                [
                    'type' => 'quick_reply',
                    'label' => 'Ya, Batalkan',
                    'value' => 'confirm_cancel:' . (string) $order->_id,
                ],
            ],
        ];
    }

    private function cancelConfirmResponse(User $user, string $orderId): array
    {
        $order = Order::where('_id', $orderId)
            ->where('customer_id', (string) $user->_id)
            ->first();

        if (!$order) {
            return [
                'reply' => 'Pesanan tidak ditemukan.',
                'intent' => 'confirm_cancel',
                'data' => null,
                'actions' => [],
            ];
        }

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if (in_array($paymentStatus, self::PAID_STATUSES, true)) {
            return [
                'reply' => 'Pesanan ini sudah lunas, jadi tidak bisa dibatalkan.',
                'intent' => 'confirm_cancel',
                'data' => [
                    'order_id' => (string) $order->_id,
                    'payment_status' => $paymentStatus,
                ],
                'actions' => [],
            ];
        }

        $midtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));
        $result = $midtransOrderId !== ''
            ? $this->paymentService->cancelTransaction($midtransOrderId)
            : $this->paymentService->cancelPendingOrderLocally((string) $order->_id);

        return [
            'reply' => (string) ($result['message'] ?? 'Permintaan pembatalan diproses.'),
            'intent' => 'confirm_cancel',
            'data' => $result['data'] ?? null,
            'actions' => [],
        ];
    }

    private function fallbackResponse(?string $fallbackReason = null): array
    {
        $data = null;
        if ($fallbackReason !== null && trim($fallbackReason) !== '') {
            $data = [
                'fallback_reason' => trim($fallbackReason),
            ];
        }

        return [
            'reply' => 'Maksud kamu belum terbaca jelas. Coba pilih aksi cepat di bawah ini.',
            'intent' => 'unknown_or_ambiguous',
            'data' => $data,
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Pesan Makanan', 'value' => 'greeting_order'],
                ['type' => 'quick_reply', 'label' => 'Tracking Pesanan', 'value' => 'greeting_tracking'],
                ['type' => 'quick_reply', 'label' => 'Rekomendasi Menu', 'value' => 'greeting_recommendation'],
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
            ],
        ];
    }

    private function mapOrderStatusLabel(string $status): string
    {
        return match ($status) {
            'PENDING_PAYMENT' => 'Menunggu Pembayaran',
            'CONFIRMED' => 'Terkonfirmasi',
            'IN_QUEUE' => 'Dalam Antrean',
            'IN_PROGRESS' => 'Sedang Diproses',
            'DELIVERED' => 'Disajikan',
            default => ucfirst(strtolower(str_replace('_', ' ', $status))),
        };
    }

    private function mapMenuCard(MenuItem $item): array
    {
        return [
            'menu_id' => (string) $item->_id,
            'menu_name' => (string) $item->name,
            'description' => (string) ($item->description ?? ''),
            'price' => (float) ($item->price ?? 0),
            'stock' => (int) ($item->stock ?? 0),
            'category' => (string) ($item->category ?? ''),
            'image_url' => (string) ($item->image_url ?? ''),
        ];
    }

    private function findLatestUnpaidOrder(User $user): ?Order
    {
        $orders = Order::where('customer_id', (string) $user->_id)
            ->orderBy('_id', 'desc')
            ->limit(10)
            ->get();

        foreach ($orders as $order) {
            $status = strtoupper((string) ($order->payment_status ?? 'PENDING'));
            if (!in_array($status, self::PAID_STATUSES, true)) {
                return $order;
            }
        }

        return null;
    }

    private function normalizeResponse(array $response): array
    {
        $actions = is_array($response['actions'] ?? null) ? $response['actions'] : [];
        $cards = is_array($response['cards'] ?? null) ? $response['cards'] : [];

        $normalizedActions = array_map(function (array $action): array {
            $type = (string) ($action['type'] ?? 'quick_reply');
            return array_merge([
                'ui_block_type' => $type,
            ], $action);
        }, $actions);

        $normalizedCards = array_map(function (array $card): array {
            $type = (string) ($card['type'] ?? 'card');
            return array_merge([
                'ui_block_type' => $type,
            ], $card);
        }, $cards);

        return [
            'response_version' => self::RESPONSE_VERSION,
            'reply' => (string) ($response['reply'] ?? ''),
            'intent' => (string) ($response['intent'] ?? 'unknown_or_ambiguous'),
            'data' => $response['data'] ?? null,
            'actions' => array_values($normalizedActions),
            'cards' => array_values($normalizedCards),
        ];
    }

    private function logTelemetry(
        string $userId,
        string $source,
        string $intentRuleBased,
        string $intentResolved,
        string $aiDecision,
        ?float $aiConfidence,
        string $action,
        int $latencyMs
    ): void {
        try {
            ChatbotMetric::create([
                'user_id' => $userId,
                'source' => $source,
                'intent_rule_based' => $intentRuleBased,
                'intent_resolved' => $intentResolved,
                'ai_decision' => $aiDecision,
                'ai_confidence' => $aiConfidence,
                'action' => $action,
                'latency_ms' => $latencyMs,
                'channel' => 'mobile_chatbot',
            ]);

            Log::info('chatbot.message.resolved', [
                'user_id' => $userId,
                'source' => $source,
                'intent_rule_based' => $intentRuleBased,
                'intent_resolved' => $intentResolved,
                'ai_decision' => $aiDecision,
                'ai_confidence' => $aiConfidence,
                'action' => $action,
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable) {
            // Skip telemetry logging when container/facade is unavailable in pure unit tests.
        }
    }
}
