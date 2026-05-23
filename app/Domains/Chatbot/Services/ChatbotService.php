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
        'order_menu',
        'tracking_order',
        'menu_recommendation',
        'view_cart',
        'checkout_request',
        'cancel_order_request',
    ];
    private const AI_MIN_CONFIDENCE_BY_INTENT = [
        'greeting' => 0.7,
        'order_menu' => 0.75,
        'tracking_order' => 0.7,
        'menu_recommendation' => 0.65,
        'view_cart' => 0.75,
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
        $intentPayload = $this->intentService->detect($message, $action);
        $intent = (string) ($intentPayload['intent'] ?? 'unknown_or_ambiguous');
        $entities = (array) ($intentPayload['entities'] ?? []);
        $source = 'rule_based';
        $aiConfidence = null;
        $aiDecision = 'not_used';

        $response = match ($intent) {
            'greeting' => $this->greetingResponse(),
            'tracking_order' => $this->trackingResponse($user),
            'view_cart' => $this->cartResponse($user),
            'order_menu' => $this->orderMenuResponse($user, $entities),
            'menu_recommendation' => $this->recommendationResponse($entities),
            'checkout_request' => $this->checkoutPromptResponse(),
            'confirm_checkout' => $this->checkoutConfirmResponse(),
            'checkout_type_select' => $this->checkoutTypeSelectedResponse((string) ($entities['checkout_type'] ?? '')),
            'cancel_order_request' => $this->cancelPromptResponse($user),
            'confirm_cancel' => $this->cancelConfirmResponse($user, (string) ($entities['order_id'] ?? '')),
            default => $this->fallbackWithGemini($user, $message, $source, $aiConfidence, $aiDecision),
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

    private function fallbackWithGemini(User $user, string $message, string &$source, ?float &$aiConfidence, string &$aiDecision): array
    {
        $geminiService = $this->geminiNluService;
        if ($geminiService === null) {
            try {
                $geminiService = app(GeminiNluService::class);
            } catch (\Throwable) {
                $geminiService = null;
            }
        }

        $aiPayload = $geminiService?->detectIntent($message);
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
            'tracking_order' => $this->trackingResponse($user),
            'view_cart' => $this->cartResponse($user),
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
            return [
                'taste' => in_array($taste, ['spicy'], true) ? $taste : null,
                'light' => (bool) ($entities['light'] ?? false),
                'filling' => (bool) ($entities['filling'] ?? false),
                'max_price' => $maxPrice > 0 && $maxPrice <= 500000 ? $maxPrice : null,
            ];
        }

        return [];
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
                'actions' => [],
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
        $query = MenuItem::query();

        $maxPrice = isset($entities['max_price']) ? (int) $entities['max_price'] : null;
        if ($maxPrice !== null && $maxPrice > 0) {
            $query->where('price', '<=', $maxPrice);
        }

        if (($entities['taste'] ?? null) === 'spicy') {
            $query->where(function ($builder) {
                $builder->where('name', 'like', '%pedas%')
                    ->orWhere('description', 'like', '%pedas%')
                    ->orWhere('description', 'like', '%cabe%')
                    ->orWhere('description', 'like', '%sambal%');
            });
        }

        if (($entities['light'] ?? false) === true) {
            $query->where('category', '!=', 'makanan utama');
        }

        $menus = $query->orderBy('price', 'asc')->limit(5)->get();
        if ($menus->isEmpty()) {
            return [
                'reply' => 'Belum ada menu yang cocok dengan kriteria itu. Coba kriteria lain ya.',
                'intent' => 'menu_recommendation',
                'data' => ['items' => []],
                'actions' => [],
            ];
        }

        return [
            'reply' => 'Ini rekomendasi menu yang cocok untuk kamu.',
            'intent' => 'menu_recommendation',
            'data' => [
                'items' => $menus->map(fn ($item) => $this->mapMenuCard($item))->values()->all(),
            ],
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Lihat Keranjang', 'value' => 'greeting_view_cart'],
            ],
            'cards' => $menus->map(fn ($item) => ['type' => 'menu_card', 'menu' => $this->mapMenuCard($item)])->values()->all(),
        ];
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
