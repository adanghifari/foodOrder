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
            'pedas murah',
            'ringan',
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
            return [
                'intent' => 'order_menu',
                'entities' => $this->extractOrderEntities($text),
            ];
        }

        // Support direct format like "bakwan jagung 3" without trigger words.
        if (preg_match('/\b\d+\b/u', $text) === 1 && str_word_count($text) >= 2) {
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
        $isLight = $this->containsAny($text, ['ringan']);
        $isFilling = $this->containsAny($text, ['kenyang']);

        $maxPrice = null;
        if (preg_match('/\b(\d{4,7})\b/u', $text, $matches) === 1) {
            $maxPrice = (int) $matches[1];
        } elseif ($this->containsAny($text, ['murah'])) {
            $maxPrice = 20000;
        }

        return [
            'taste' => $isSpicy ? 'spicy' : null,
            'light' => $isLight,
            'filling' => $isFilling,
            'max_price' => $maxPrice,
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
