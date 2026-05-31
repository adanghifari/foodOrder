<?php

namespace App\Domains\Chatbot\Services;

class ChatbotIntentService
{
    public function detect(string $message, string $action = ''): array
    {
        $action = trim(strtolower($action));
        $text = trim(mb_strtolower($message));

        if ($action !== '') {
            return $this->fromAction($action);
        }

        if ($text === '' || $this->containsAny($text, ['halo', 'hai', 'hello', 'hi'])) {
            return ['intent' => 'greeting', 'entities' => []];
        }

        if ($this->containsAny($text, ['restoran apa', 'ini restoran apa', 'kedaiklik', 'kedaibot'])) {
            return ['intent' => 'small_talk', 'entities' => []];
        }

        if ($this->containsAny($text, ['kosongkan keranjang', 'hapus semua keranjang', 'clear cart', 'kosongkan cart'])) {
            return ['intent' => 'clear_cart_request', 'entities' => []];
        }

        if ($this->containsAny($text, ['tambah', 'tambahin', 'tambahin'])) {
            return [
                'intent' => 'cart_increase_qty',
                'entities' => $this->extractCartMutationEntities($text),
            ];
        }

        if ($this->containsAny($text, ['kurang', 'kurangi', 'hapus satu', 'hapus 1', 'turunin'])) {
            return [
                'intent' => 'cart_decrease_qty',
                'entities' => $this->extractCartMutationEntities($text),
            ];
        }

        if ($this->containsAny($text, ['tracking', 'lacak', 'status pesanan', 'pesanan saya sampai mana', 'cek pesanan'])) {
            return ['intent' => 'tracking_order', 'entities' => []];
        }

        if ($this->containsAny($text, ['keranjang', 'cart'])) {
            return ['intent' => 'view_cart', 'entities' => []];
        }

        if ($this->containsAny($text, ['checkout', 'bayar', 'lanjut bayar', 'selesai pesan'])) {
            return ['intent' => 'checkout_request', 'entities' => []];
        }

        if ($this->containsAny($text, ['batal', 'cancel'])) {
            return ['intent' => 'cancel_order_request', 'entities' => []];
        }

        if ($this->containsAny($text, [
            'rekomendasi',
            'saran menu',
            'cocok',
            'yang enak',
            'bingung mau makan apa',
            'bingung mau makan',
            'makan apa',
            'enaknya apa',
            'lapar',
            'pedas murah',
            'pedas',
            'manis',
            'segar',
            'ringan',
            'makanan',
            'minuman',
            'cemilan',
            'murah',
            'termurah',
            'paling murah',
        ])) {
            return [
                'intent' => 'menu_recommendation',
                'entities' => $this->extractRecommendationEntities($text),
            ];
        }

        if ($this->containsAny($text, ['pesan', 'order', 'mau'])) {
            $looksAmbiguousRecommendation = $this->containsAny($text, [
                'bingung',
                'makan apa',
                'enaknya apa',
                'lapar',
            ]);
            if ($looksAmbiguousRecommendation) {
                return [
                    'intent' => 'menu_recommendation',
                    'entities' => $this->extractRecommendationEntities($text),
                ];
            }
            return [
                'intent' => 'order_menu',
                'entities' => $this->extractOrderEntities($text),
            ];
        }

        // Support direct format like "bakwan jagung 3" without trigger words.
        // Guard: do not force order intent for recommendation-style prompts with budget/criteria.
        $hasNumber = preg_match('/\b\d+\b/u', $text) === 1;
        $hasRecommendationSignal = $this->containsAny($text, [
            'pedas',
            'manis',
            'segar',
            'ringan',
            'kenyang',
            'murah',
            'makanan',
            'minuman',
            'cemilan',
            'rekomendasi',
            'saran',
            'cocok',
        ]);
        if ($hasNumber && str_word_count($text) >= 2 && !$hasRecommendationSignal) {
            return [
                'intent' => 'order_menu',
                'entities' => $this->extractOrderEntities($text),
            ];
        }

        return ['intent' => 'unknown_or_ambiguous', 'entities' => []];
    }

    private function fromAction(string $action): array
    {
        if ($action === 'greeting_order') {
            return ['intent' => 'order_menu', 'entities' => []];
        }

        if ($action === 'greeting_tracking') {
            return ['intent' => 'tracking_order', 'entities' => []];
        }

        if ($action === 'greeting_recommendation') {
            return ['intent' => 'menu_recommendation', 'entities' => []];
        }

        if ($action === 'greeting_view_cart') {
            return ['intent' => 'view_cart', 'entities' => []];
        }

        if (str_starts_with($action, 'qty_')) {
            $payload = substr($action, strlen('qty_'));
            $parts = explode(':', (string) $payload);
            $qty = isset($parts[0]) ? (int) $parts[0] : 1;
            $menuId = trim((string) ($parts[1] ?? ''));

            return [
                'intent' => 'order_menu',
                'entities' => [
                    'quantity' => max(1, $qty),
                    'menu_id' => $menuId,
                    'menu_name' => '',
                ],
            ];
        }

        if (str_starts_with($action, 'suggest_menu:')) {
            $menuId = trim(substr($action, strlen('suggest_menu:')));
            return [
                'intent' => 'order_menu',
                'entities' => [
                    'menu_id' => $menuId,
                    'menu_name' => '',
                    'quantity' => null,
                ],
            ];
        }

        if ($action === 'confirm_checkout') {
            return ['intent' => 'confirm_checkout', 'entities' => []];
        }

        if (str_starts_with($action, 'checkout_type:')) {
            $type = trim(substr($action, strlen('checkout_type:')));
            return ['intent' => 'checkout_type_select', 'entities' => ['checkout_type' => $type]];
        }

        if (str_starts_with($action, 'confirm_cancel:')) {
            $orderId = trim(substr($action, strlen('confirm_cancel:')));
            return ['intent' => 'confirm_cancel', 'entities' => ['order_id' => $orderId]];
        }

        if (str_starts_with($action, 'cart_increase:')) {
            $payload = substr($action, strlen('cart_increase:'));
            $parts = explode(':', (string) $payload);
            return [
                'intent' => 'cart_increase_qty',
                'entities' => [
                    'menu_id' => trim((string) ($parts[0] ?? '')),
                    'quantity' => max(1, (int) ($parts[1] ?? 1)),
                ],
            ];
        }

        if (str_starts_with($action, 'cart_decrease:')) {
            $payload = substr($action, strlen('cart_decrease:'));
            $parts = explode(':', (string) $payload);
            return [
                'intent' => 'cart_decrease_qty',
                'entities' => [
                    'menu_id' => trim((string) ($parts[0] ?? '')),
                    'quantity' => max(1, (int) ($parts[1] ?? 1)),
                ],
            ];
        }

        if ($action === 'clear_cart_now') {
            return ['intent' => 'clear_cart_request', 'entities' => []];
        }

        return ['intent' => 'unknown_or_ambiguous', 'entities' => []];
    }

    private function extractOrderEntities(string $text): array
    {
        $quantity = null;
        if (preg_match('/\b(\d+)\b/u', $text, $matches) === 1) {
            $quantity = max(1, (int) $matches[1]);
        }

        $clean = preg_replace('/\b(saya|aku|mau|pesan|order|porsi|ya|dong)\b/u', ' ', $text);
        $clean = preg_replace('/\b\d+\b/u', ' ', (string) $clean);
        $menuName = trim((string) preg_replace('/\s+/', ' ', (string) $clean));

        return [
            'menu_name' => $menuName,
            'quantity' => $quantity,
        ];
    }

    private function extractRecommendationEntities(string $text): array
    {
        $isSpicy = $this->containsAny($text, ['pedas', 'spicy', 'cabe', 'sambal']);
        $isSweet = $this->containsAny($text, ['manis', 'sweet']);
        $isFresh = $this->containsAny($text, ['segar', 'fresh']);
        $isLight = $this->containsAny($text, ['ringan']);
        $isFilling = $this->containsAny($text, ['kenyang']);
        $isForSharing = $this->containsAny($text, ['buat berbagi', 'untuk berbagi', 'sharing', 'rame-rame', 'ramai ramai']);
        $isVery = $this->containsAny($text, ['banget', 'sangat', 'sekali']);
        $category = null;
        if ($this->containsAny($text, ['minuman', 'drink'])) {
            $category = 'minuman';
        } elseif ($this->containsAny($text, ['cemilan', 'snack'])) {
            $category = 'cemilan';
        } elseif ($this->containsAny($text, ['makanan', 'makan'])) {
            $category = 'makanan utama';
        }
        $calorieLevel = null;
        if ($this->containsAny($text, ['rendah kalori', 'low calorie', 'diet'])) {
            $calorieLevel = 'low';
        } elseif ($this->containsAny($text, ['sedang kalori', 'kalori sedang'])) {
            $calorieLevel = 'medium';
        } elseif ($this->containsAny($text, ['tinggi kalori', 'high calorie'])) {
            $calorieLevel = 'high';
        }

        $maxPrice = null;
        if (preg_match('/\b(\d{4,7})\b/u', $text, $matches) === 1) {
            $maxPrice = (int) $matches[1];
        } elseif ($this->containsAny($text, ['murah'])) {
            $maxPrice = 20000;
        }

        return [
            'taste' => $isSpicy ? 'spicy' : ($isSweet ? 'sweet' : ($isFresh ? 'fresh' : null)),
            'taste_intensity' => $isVery ? 'high' : 'normal',
            'category' => $category,
            'light' => $isLight,
            'filling' => $isFilling,
            'required_tags' => $isForSharing ? ['sharing_bersama'] : [],
            'calorie_level' => $calorieLevel,
            'max_price' => $maxPrice,
            'query_text' => $text,
        ];
    }

    private function extractCartMutationEntities(string $text): array
    {
        $quantity = 1;
        if (preg_match('/\b(\d+)\b/u', $text, $matches) === 1) {
            $quantity = max(1, (int) $matches[1]);
        }

        $clean = preg_replace('/\b(saya|aku|dong|ya|tolong|di|ke|keranjang|cart|item|menu|tambah|tambahin|tambahin|kurang|kurangi|hapus|satu|turunin)\b/u', ' ', $text);
        $clean = preg_replace('/\b\d+\b/u', ' ', (string) $clean);
        $menuName = trim((string) preg_replace('/\s+/', ' ', (string) $clean));

        return [
            'menu_name' => $menuName,
            'quantity' => $quantity,
        ];
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
