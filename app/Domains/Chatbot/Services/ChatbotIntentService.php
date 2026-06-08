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

        if ($this->containsAny($text, ['best seller', 'bestseller', 'terlaris', 'paling laku', 'menu favorit'])) {
            return ['intent' => 'best_seller', 'entities' => []];
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
            'seger',
            'menyegarkan',
            'nyegerin',
            'ringan',
            'asin',
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

        if (str_starts_with($action, 'recommend_relax_price:')) {
            $payload = substr($action, strlen('recommend_relax_price:'));
            $parts = explode(':', (string) $payload);
            $category = $this->mapCategoryTokenToValue((string) ($parts[0] ?? ''));
            $minPrice = max(0, (int) ($parts[1] ?? 0));
            $maxPrice = max(0, (int) ($parts[2] ?? 0));

            return [
                'intent' => 'menu_recommendation',
                'entities' => [
                    'category' => $category,
                    'price_mode' => 'range',
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'query_text' => '',
                ],
            ];
        }

        if (str_starts_with($action, 'recommend_nearest_price:')) {
            $payload = substr($action, strlen('recommend_nearest_price:'));
            $parts = explode(':', (string) $payload);
            $category = $this->mapCategoryTokenToValue((string) ($parts[0] ?? ''));
            $minPrice = max(0, (int) ($parts[1] ?? 0));
            $maxPrice = max(0, (int) ($parts[2] ?? 0));
            $targetPrice = $minPrice > 0 && $maxPrice > 0
                ? (int) floor(($minPrice + $maxPrice) / 2)
                : 0;

            return [
                'intent' => 'menu_recommendation',
                'entities' => [
                    'category' => $category,
                    'price_mode' => 'around',
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'target_price' => $targetPrice,
                    'query_text' => '',
                ],
            ];
        }

        if (str_starts_with($action, 'recommend_category:')) {
            $category = $this->mapCategoryTokenToValue(substr($action, strlen('recommend_category:')));
            return [
                'intent' => 'menu_recommendation',
                'entities' => [
                    'category' => $category,
                    'query_text' => '',
                ],
            ];
        }

        if (str_starts_with($action, 'recommend_tag:')) {
            $payload = substr($action, strlen('recommend_tag:'));
            $parts = explode(':', (string) $payload);
            $category = $this->mapCategoryTokenToValue((string) ($parts[0] ?? ''));
            $tag = trim(strtolower((string) ($parts[1] ?? '')));
            return [
                'intent' => 'menu_recommendation',
                'entities' => [
                    'category' => $category,
                    'preferred_tags' => $tag !== '' ? [$tag] : [],
                    'query_text' => '',
                ],
            ];
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
        $isFresh = $this->containsAny($text, ['segar', 'seger', 'fresh', 'menyegarkan', 'nyegerin']);
        $isSalty = $this->containsAny($text, ['asin', 'gurih asin']);
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

        $price = $this->extractPriceSignal($text);

        $result = [
            'taste' => $isSpicy ? 'spicy' : ($isSweet ? 'sweet' : ($isFresh ? 'fresh' : null)),
            'taste_intensity' => $isVery ? 'high' : 'normal',
            'category' => $category,
            'light' => $isLight,
            'filling' => $isFilling,
            'required_tags' => $isForSharing ? ['sharing_bersama'] : [],
            'preferred_tags' => array_values(array_filter([
                $isFresh ? 'segar' : null,
                $isFresh ? 'dingin' : null,
                $isSalty ? 'asin' : null,
                $isSpicy ? 'pedas' : null,
                $isLight ? 'ringan' : null,
            ])),
            'calorie_level' => $calorieLevel,
            'query_text' => $text,
        ];

        if (is_array($price)) {
            $result = array_merge($result, $price);
        }

        return $result;
    }

    private function extractPriceSignal(string $text): ?array
    {
        $normalized = strtolower(trim($text));
        if ($normalized === '') {
            return null;
        }

        $range = $this->extractPriceRange($normalized);
        if (is_array($range)) {
            return $range;
        }

        $price = $this->extractPriceNumber($normalized);

        $isAround = $this->containsAny($normalized, ['sekitar', 'kisaran', 'kira-kira', 'kurang lebih', 'harganya sekitar']);
        $isMax = $this->containsAny($normalized, ['harga maksimal', 'maksimal', 'max', 'budget', 'di bawah', 'dibawah', 'kurang dari', 'under']);
        $isCheap = $this->containsAny($normalized, ['murah', 'termurah', 'yang murah']);

        if ($isAround && $price !== null && $price > 0) {
            $min = max(0, (int) floor($price * 0.8));
            $max = (int) ceil($price * 1.2);
            return [
                'price_mode' => 'around',
                'target_price' => $price,
                'min_price' => $min,
                'max_price' => $max,
            ];
        }

        if ($isMax && $price !== null && $price > 0) {
            return [
                'price_mode' => 'max',
                'max_price' => $price,
            ];
        }

        if ($isCheap) {
            $result = ['price_mode' => 'cheap'];
            if ($price !== null && $price > 0) {
                $result['max_price'] = $price;
            } else {
                $result['max_price'] = 20000;
            }
            return $result;
        }

        if ($price !== null && $price > 0) {
            return [
                'price_mode' => 'max',
                'max_price' => $price,
            ];
        }

        return null;
    }

    private function extractPriceRange(string $text): ?array
    {
        $prices = $this->extractAllPriceNumbers($text);
        if (count($prices) < 2) {
            return null;
        }

        $hasRangeSignal = $this->containsAny($text, [
            'sampai',
            'sampe',
            'antara',
            'hingga',
            ' - ',
            '-',
            'dan',
        ]);
        if (!$hasRangeSignal) {
            return null;
        }

        $first = (int) ($prices[0] ?? 0);
        $second = (int) ($prices[1] ?? 0);
        if ($first <= 0 || $second <= 0) {
            return null;
        }

        $min = min($first, $second);
        $max = max($first, $second);
        return [
            'price_mode' => 'range',
            'min_price' => $min,
            'max_price' => $max,
        ];
    }

    private function extractPriceNumber(string $text): ?int
    {
        // Supports: 20000, 20.000, 20,000, 20 000, 20rb, 20 rb, 20k, 20 k, Rp20.000, rp 20rb
        if (preg_match('/(?:rp\.?\s*)?(\d{1,3}(?:[\s\.,]\d{3})+|\d+)\s*(rb|ribu|k)?\b/u', $text, $matches) !== 1) {
            return null;
        }

        $rawNumber = strtolower(trim((string) ($matches[1] ?? '')));
        $suffix = strtolower(trim((string) ($matches[2] ?? '')));
        $digits = preg_replace('/[^\d]/', '', $rawNumber);
        if ($digits === '') {
            return null;
        }

        $value = (int) $digits;
        if (in_array($suffix, ['rb', 'ribu', 'k'], true)) {
            if ($value < 1000) {
                $value *= 1000;
            }
        }

        return $value > 0 ? $value : null;
    }

    private function extractAllPriceNumbers(string $text): array
    {
        if (preg_match_all('/(?:rp\.?\s*)?(\d{1,3}(?:[\s\.,]\d{3})+|\d+)\s*(rb|ribu|k)?\b/u', $text, $matches, PREG_SET_ORDER) !== false) {
            $values = [];
            foreach ($matches as $row) {
                $rawNumber = strtolower(trim((string) ($row[1] ?? '')));
                $suffix = strtolower(trim((string) ($row[2] ?? '')));
                $digits = preg_replace('/[^\d]/', '', $rawNumber);
                if ($digits === '') {
                    continue;
                }
                $value = (int) $digits;
                if (in_array($suffix, ['rb', 'ribu', 'k'], true) && $value < 1000) {
                    $value *= 1000;
                }
                if ($value > 0) {
                    $values[] = $value;
                }
            }
            return $values;
        }

        return [];
    }

    private function mapCategoryTokenToValue(string $token): ?string
    {
        $normalized = strtoupper(trim($token));
        return match ($normalized) {
            'MAKANAN_UTAMA' => 'makanan utama',
            'MINUMAN' => 'minuman',
            'CEMILAN' => 'cemilan',
            default => null,
        };
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
