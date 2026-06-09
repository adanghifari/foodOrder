<?php

namespace App\Domains\Chatbot\Services;

class ChatbotIntentService
{
    public function detect(string $message, string $action = ''): array
    {
        $action = trim(strtolower($action));
        $rawText = trim($message);
        
        if ($action !== '') {
            return $this->fromAction($action);
        }

        $normalizedText = $this->normalizeText($rawText);

        // 1. Detect Multi-Slot Recommendation Query
        if ($this->isMultiSlotQuery($normalizedText)) {
            return [
                'intent' => 'RECOMMEND_MENU',
                'isRestaurantContext' => true,
                'confidence' => 0.5, // low confidence to trigger Gemini NLU fallback
                'criteria' => null,
                'recommendationSlots' => [],
                'entities' => []
            ];
        }

        // 2. Direct quantity order detection (e.g. "bakwan jagung 3")
        $hasNumber = preg_match('/\b\d+\b/u', $normalizedText) === 1;
        $hasRecommendationSignal = $this->containsAny($normalizedText, [
            'pedas', 'manis', 'segar', 'ringan', 'mengenyangkan', 'murah', 'makanan', 'minuman', 'cemilan', 'rekomendasi', 'saran', 'cocok', 'populer'
        ]);
        $numberLooksLikePrice = $this->hasPriceContextSignal($normalizedText) || preg_match('/\b\d{4,}\b/u', $normalizedText) === 1;
        
        if ($hasNumber && str_word_count($normalizedText) >= 2 && !$hasRecommendationSignal && !$numberLooksLikePrice) {
            $legacyEntities = $this->extractLegacyEntities($normalizedText, 'ADD_TO_CART');
            return [
                'intent' => 'ADD_TO_CART',
                'isRestaurantContext' => true,
                'confidence' => 1.0,
                'criteria' => [
                    'menuName' => $legacyEntities['menu_name'] ?? null,
                    'quantity' => $legacyEntities['quantity'] ?? null,
                ],
                'recommendationSlots' => [],
                'entities' => $legacyEntities
            ];
        }

        // 3. Specific menu inquiry check (e.g. "ada mi ayam ga?")
        $menuInquiryName = null;
        if (preg_match('/\b(?:ada|punya|jual)\s+(.+)/u', $normalizedText, $menuInquiry) === 1) {
            $inquiryName = trim((string) ($menuInquiry[1] ?? ''));
            $inquiryName = preg_replace('/\s*\b(?:ga|gak|gk|nggak|tidak|kah|ya|dong|nggk)\b\s*/', '', $inquiryName);
            $inquiryName = preg_replace('/[?\s]+$/', '', $inquiryName);
            $inquiryName = trim((string) $inquiryName);
            if ($inquiryName !== '' && mb_strlen($inquiryName) >= 2) {
                $menuInquiryName = $inquiryName;
            }
        }

        // 4. Intent Detection
        $intent = 'unknown_or_ambiguous';
        $confidence = 1.0;

        // Yes/Confirmation mapping (confirming pending actions)
        $yesPhrases = ['iya', 'boleh', 'tambahkan', 'tambahin', 'masukin', 'masukkan', 'tambah', 'oke boleh', 'ya', 'ok', 'gas', 'lanjut'];
        $isYesPhrase = in_array($normalizedText, $yesPhrases, true);
        if (!$isYesPhrase) {
            $words = explode(' ', $normalizedText);
            $allYes = true;
            foreach ($words as $word) {
                if (!in_array($word, $yesPhrases, true) && !in_array($word, ['semua', 'dong', 'saja', 'aja', 'ke', 'keranjang'], true)) {
                    $allYes = false;
                    break;
                }
            }
            if ($allYes && count($words) > 0) {
                $isYesPhrase = true;
            }
        }

        if ($isYesPhrase) {
            return [
                'intent' => 'ADD_TO_CART',
                'isRestaurantContext' => true,
                'confidence' => 1.0,
                'criteria' => [
                    'confirm_last_recommendation' => true,
                ],
                'recommendationSlots' => [],
                'entities' => [
                    'confirm_last_recommendation' => true,
                ]
            ];
        }

        // Target selection (e.g. "yang pertama")
        if (str_contains($normalizedText, 'pertama') || str_contains($normalizedText, 'kesatu') || str_contains($normalizedText, '1')) {
            $cleanedTargetText = $normalizedText;
            foreach ($yesPhrases as $yp) {
                $cleanedTargetText = preg_replace('/\b' . preg_quote($yp, '/') . '\b/u', '', $cleanedTargetText);
            }
            $cleanedTargetText = trim(preg_replace('/\s+/', ' ', $cleanedTargetText));
            if (in_array($cleanedTargetText, ['yang pertama', 'pertama', 'nomor 1', 'no 1', '1'], true)) {
                return [
                    'intent' => 'ADD_TO_CART',
                    'isRestaurantContext' => true,
                    'confidence' => 1.0,
                    'criteria' => [
                        'confirm_last_recommendation' => true,
                        'target_index' => 0,
                    ],
                    'recommendationSlots' => [],
                    'entities' => [
                        'confirm_last_recommendation' => true,
                        'target_index' => 0,
                    ]
                ];
            }
        }

        $hasClearWord = $this->containsAny($normalizedText, ['kosongkan', 'kosongin', 'clear', 'bersihkan', 'reset']);
        $hasRemoveWord = $this->containsAny($normalizedText, ['hapus', 'kurang', 'turun', 'buang', 'delete', 'remove', 'kurangi']);
        $hasAllWord = $this->containsAny($normalizedText, ['semua', 'semuanya', 'all']);

        $isClearRequest = $hasClearWord || ($hasRemoveWord && $hasAllWord);
        $isRemoveRequest = $hasRemoveWord;

        $hasAllWord = $this->containsAny($normalizedText, ['semua', 'semuanya', 'daftar semua', 'lihat semua', 'tampilkan semua']);
        $hasCategoryWord = $this->containsAny($normalizedText, ['makanan', 'minuman', 'cemilan', 'camilan', 'snack']);

        if ($hasAllWord && $hasCategoryWord) {
            $intent = 'ASK_CATEGORY';
        } elseif ($normalizedText === '' || $this->containsAny($normalizedText, ['halo', 'hai', 'hello', 'hi', 'pagi', 'siang', 'sore', 'malam'])) {
            $intent = 'SMALL_TALK_RESTAURANT';
        } elseif ($this->containsAny($normalizedText, ['restoran apa', 'ini restoran apa', 'kedaiklik', 'kedaibot', 'siapa kamu', 'makasih', 'terima kasih', 'thanks', 'oke', 'sip', 'mantap'])) {
            $intent = 'SMALL_TALK_RESTAURANT';
        } elseif ($this->containsAny($normalizedText, ['lacak', 'tracking', 'status pesanan', 'pesanan saya sampai mana', 'cek pesanan', 'pesanan saya'])) {
            $intent = 'CHECK_ORDER';
        } elseif ($isClearRequest) {
            $intent = 'REMOVE_FROM_CART';
        } elseif ($isRemoveRequest && $this->containsAny($normalizedText, ['keranjang', 'cart'])) {
            $intent = 'REMOVE_FROM_CART';
        } elseif ($this->containsAny($normalizedText, ['keranjang', 'cart', 'isi keranjang', 'lihat keranjang'])) {
            $intent = 'VIEW_CART';
        } elseif ($this->containsAny($normalizedText, ['checkout', 'bayar', 'lanjut bayar', 'selesai pesan'])) {
            $intent = 'checkout_request';
        } elseif ($this->containsAny($normalizedText, ['batal', 'cancel'])) {
            $intent = 'cancel_order_request';
        } elseif ($this->containsAny($normalizedText, ['rekomendasi', 'saran', 'cocok', 'lapar', 'laper', 'bingung', 'pedas', 'manis', 'segar', 'seger', 'ringan', 'mengenyangkan', 'murah', 'populer', 'hangat', 'hujan'])) {
            $intent = 'RECOMMEND_MENU';
        } elseif ($this->hasPriceContextSignal($normalizedText)) {
            $intent = 'RECOMMEND_MENU';
        } elseif ($this->containsAny($normalizedText, ['berapa harga', 'harganya berapa', 'harga'])) {
            $intent = 'ASK_PRICE';
        } elseif ($menuInquiryName !== null) {
            $intent = 'ASK_MENU_DETAIL';
        } elseif ($this->containsAny($normalizedText, ['detail', 'apa itu', 'ada menu'])) {
            $intent = 'ASK_MENU_DETAIL';
        } elseif ($this->containsAny($normalizedText, ['kategori', 'daftar makanan', 'daftar minuman', 'daftar cemilan'])) {
            $intent = 'ASK_CATEGORY';
        } elseif ($this->containsAny($normalizedText, ['tambah', 'tambahin', 'kurang', 'kurangi', 'hapus', 'hapus satu', 'hapus 1', 'turun', 'turunin', 'buang', 'delete', 'remove', 'pesan', 'order', 'mau', 'beli'])) {
            $intent = $this->containsAny($normalizedText, ['kurang', 'kurangi', 'hapus', 'turun', 'turunin', 'buang', 'delete', 'remove']) ? 'REMOVE_FROM_CART' : 'ADD_TO_CART';
        }

        // Fallback or legacy extraction
        $criteria = $this->extractCriteria($normalizedText);
        if ($menuInquiryName !== null && $intent === 'ASK_MENU_DETAIL') {
            $criteria['menuName'] = $menuInquiryName;
        }
        $legacyEntities = $this->extractLegacyEntities($normalizedText, $intent);
        if ($menuInquiryName !== null && $intent === 'ASK_MENU_DETAIL') {
            $legacyEntities['menu_name'] = $menuInquiryName;
            $legacyEntities['quantity'] = null;
        }

        // If rule-based intent is recommendation but we have no criteria signals, confidence is lower
        if ($intent === 'RECOMMEND_MENU') {
            $hasSignals = $criteria['category'] !== null 
                || !empty($criteria['taste']) 
                || !empty($criteria['tags']) 
                || $criteria['portion'] !== null 
                || $criteria['pricePreference'] !== null 
                || $criteria['popularity'] !== null 
                || $criteria['mood'] !== null;
            if (!$hasSignals) {
                $confidence = 0.5; // Trigger Gemini NLU fallback
            }
        }

        if ($intent === 'unknown_or_ambiguous') {
            $confidence = 0.5;
        }

        return [
            'intent' => $intent,
            'isRestaurantContext' => true,
            'confidence' => $confidence,
            'criteria' => $criteria,
            'recommendationSlots' => [],
            'entities' => $legacyEntities
        ];
    }

    public function normalizeText(string $text): string
    {
        $text = trim(mb_strtolower($text));
        // Remove punctuation
        $text = preg_replace('/[^\w\s\-]/u', ' ', $text);
        // Normalize multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Pemetaan sinonim & slang (asin tidak dipetakan ke gurih agar kompatibel dengan tag asin)
        $slangMap = [
            'hemat' => 'murah', 'budget' => 'murah', 'bokek' => 'murah', 'terjangkau' => 'murah', 
            'ga mahal' => 'murah', 'nggak mahal' => 'murah', 'jangan mahal' => 'murah', 'ramah di kantong' => 'murah',
            'spicy' => 'pedas', 'nendang' => 'pedas', 'sambel' => 'pedas', 'sambal' => 'pedas', 'hot' => 'pedas', 'pedes' => 'pedas',
            'sweet' => 'manis', 'legit' => 'manis',
            'seger' => 'segar', 'fresh' => 'segar', 'dingin' => 'segar', 'adem' => 'segar', 'nyegerin' => 'segar', 'menyegarkan' => 'segar',
            'kenyang' => 'mengenyangkan', 'laper' => 'mengenyangkan', 'lapar' => 'mengenyangkan', 'berat' => 'mengenyangkan', 
            'makan besar' => 'mengenyangkan', 'makan siang' => 'mengenyangkan', 'makan malam' => 'mengenyangkan', 'ngenyangin' => 'mengenyangkan',
            'camilan' => 'cemilan', 'snack' => 'cemilan', 'ga berat' => 'ringan', 'nggak berat' => 'ringan', 'nyemil' => 'cemilan',
            'best seller' => 'populer', 'bestseller' => 'populer', 'favorit' => 'populer', 'paling laku' => 'populer', 
            'rekomendasi' => 'populer', 'rekomendasiin' => 'populer', 'yang enak' => 'populer', 'menu andalan' => 'populer', 'aman' => 'populer',
            'panas' => 'hangat', 'kuah' => 'hangat', 'hujan' => 'hangat', 'dingin-dingin' => 'hangat', 'anget' => 'hangat',
            'savory' => 'gurih'
        ];
        
        // Ganti frasa multi-kata dulu
        foreach ($slangMap as $slang => $standard) {
            if (str_contains($slang, ' ')) {
                $text = str_replace($slang, $standard, $text);
            }
        }
        
        // Ganti per kata
        $words = explode(' ', $text);
        foreach ($words as &$word) {
            if (isset($slangMap[$word])) {
                $word = $slangMap[$word];
            }
        }
        unset($word);
        
        return implode(' ', $words);
    }

    private function isMultiSlotQuery(string $text): bool
    {
        $hasMakanan = str_contains($text, 'makan');
        $hasMinuman = str_contains($text, 'minum') || str_contains($text, 'segar') || str_contains($text, 'fresh');
        $hasCemilan = str_contains($text, 'cemilan') || str_contains($text, 'camilan') || str_contains($text, 'snack') || str_contains($text, 'ringan');
        
        $categoriesCount = ($hasMakanan ? 1 : 0) + ($hasMinuman ? 1 : 0) + ($hasCemilan ? 1 : 0);
        
        // Jika meminta item dari lebih dari satu kategori dalam kalimat yang sama
        if ($categoriesCount >= 2) {
            return true;
        }
        
        // Jika terdapat tanda pemisah koma atau kata hubung 'terus/lalu/dan' dengan kriteria rasa/tipe yang terpisah
        if (str_contains($text, ',') || str_contains($text, ' terus ') || str_contains($text, ' lalu ')) {
            $tasteSignals = ['pedas', 'manis', 'segar', 'gurih', 'hangat', 'ringan', 'asin'];
            $hits = 0;
            foreach ($tasteSignals as $signal) {
                if (str_contains($text, $signal)) {
                    $hits++;
                }
            }
            if ($hits >= 2) {
                return true;
            }
        }
        
        return false;
    }

    private function extractCriteria(string $text): array
    {
        $category = null;
        if (str_contains($text, 'minum') || str_contains($text, 'segar')) {
            $category = 'Minuman';
        } elseif (str_contains($text, 'cemilan') || str_contains($text, 'ringan')) {
            $category = 'Cemilan';
        } elseif (str_contains($text, 'makan')) {
            $category = 'Makanan';
        }
        
        $taste = [];
        if (str_contains($text, 'pedas')) {
            $taste[] = 'pedas';
        }
        if (str_contains($text, 'manis')) {
            $taste[] = 'manis';
        }
        if (str_contains($text, 'segar')) {
            $taste[] = 'segar';
        }
        if (str_contains($text, 'gurih') || str_contains($text, 'asin')) {
            $taste[] = 'gurih';
        }
        
        $portion = null;
        if (str_contains($text, 'mengenyangkan')) {
            $portion = 'mengenyangkan';
        } elseif (str_contains($text, 'ringan')) {
            $portion = 'ringan';
        }
        
        $pricePreference = null;
        if (str_contains($text, 'murah')) {
            $pricePreference = 'murah';
        }
        
        $popularity = null;
        if (str_contains($text, 'populer')) {
            $popularity = 'populer';
        }
        
        $mood = null;
        if (str_contains($text, 'mengenyangkan') && (str_contains($text, 'laper') || str_contains($text, 'lapar'))) {
            $mood = 'lapar';
        } elseif (str_contains($text, 'bingung')) {
            $mood = 'bingung';
        } elseif (str_contains($text, 'hangat') && str_contains($text, 'hujan')) {
            $mood = 'hujan';
        }
        
        $tags = [];
        if (str_contains($text, 'hangat')) {
            $tags[] = 'hangat';
        }
        if (str_contains($text, 'sharing') || str_contains($text, 'rame') || str_contains($text, 'ramai')) {
            $tags[] = 'sharing_bersama';
        }
        if (str_contains($text, 'pedas')) {
            $tags[] = 'pedas';
        }
        if (str_contains($text, 'gurih')) {
            $tags[] = 'gurih';
        }
        if (str_contains($text, 'asin')) {
            $tags[] = 'asin';
        }
        if (str_contains($text, 'segar')) {
            $tags[] = 'segar';
        }
        
        $hasClearWord = $this->containsAny($text, ['kosongkan', 'kosongin', 'clear', 'bersihkan', 'reset']);
        $hasRemoveWord = $this->containsAny($text, ['hapus', 'kurang', 'turun', 'buang', 'delete', 'remove', 'kurangi']);
        $hasAllWord = $this->containsAny($text, ['semua', 'semuanya', 'all']);
        $clearCart = $hasClearWord || ($hasRemoveWord && $hasAllWord);

        $limitMode = 'default';
        if ($this->containsAny($text, ['semua', 'semuanya', 'daftar semua', 'lihat semua', 'tampilkan semua'])) {
            $limitMode = 'all';
        }
        
        return [
            'category' => $category,
            'tags' => array_values(array_unique($tags)),
            'taste' => array_values(array_unique($taste)),
            'portion' => $portion,
            'pricePreference' => $pricePreference,
            'popularity' => $popularity,
            'mood' => $mood,
            'menuName' => null,
            'clear_cart' => $clearCart,
            'limitMode' => $limitMode
        ];
    }

    private function extractLegacyEntities(string $text, string $intent): array
    {
        if ($intent === 'ADD_TO_CART' || $intent === 'REMOVE_FROM_CART') {
            $quantity = 1;
            if (preg_match('/\b(\d+)\b/u', $text, $matches) === 1) {
                $quantity = max(1, (int) $matches[1]);
            }

            $clean = preg_replace('/\b(saya|aku|dong|ya|tolong|di|ke|keranjang|cart|item|menu|tambah|tambahin|kurang|kurangi|hapus|satu|turunin|pesan|order|mau|beli|porsi)\b/u', ' ', $text);
            $clean = preg_replace('/\b\d+\b/u', ' ', (string) $clean);
            $menuName = trim((string) preg_replace('/\s+/', ' ', (string) $clean));

            return [
                'menu_name' => $menuName !== '' ? $menuName : null,
                'quantity' => $quantity,
            ];
        }

        if ($intent === 'RECOMMEND_MENU') {
            $criteria = $this->extractCriteria($text);
            $price = $this->extractPriceSignal($text);
            
            $legacy = [
                'taste' => in_array('pedas', $criteria['taste'], true) ? 'spicy' : (in_array('manis', $criteria['taste'], true) ? 'sweet' : (in_array('segar', $criteria['taste'], true) ? 'fresh' : null)),
                'taste_intensity' => 'normal',
                'category' => $criteria['category'] === 'Makanan' ? 'makanan utama' : ($criteria['category'] === 'Minuman' ? 'minuman' : ($criteria['category'] === 'Cemilan' ? 'cemilan' : null)),
                'light' => $criteria['portion'] === 'ringan',
                'filling' => $criteria['portion'] === 'mengenyangkan',
                'required_tags' => in_array('sharing_bersama', $criteria['tags'], true) ? ['sharing_bersama'] : [],
                'preferred_tags' => $criteria['tags'],
                'query_text' => $text,
            ];

            if (is_array($price)) {
                $legacy = array_merge($legacy, $price);
            } else {
                if ($criteria['pricePreference'] === 'murah') {
                    $legacy['price_mode'] = 'cheap';
                    $legacy['max_price'] = 20000;
                }
            }

            return $legacy;
        }

        return [];
    }

    private function fromAction(string $action): array
    {
        if ($action === 'iya') {
            return [
                'intent' => 'ADD_TO_CART',
                'isRestaurantContext' => true,
                'confidence' => 1.0,
                'criteria' => [
                    'confirm_last_recommendation' => true,
                ],
                'recommendationSlots' => [],
                'entities' => [
                    'confirm_last_recommendation' => true,
                ]
            ];
        }

        if (in_array($action, ['minuman', 'cemilan', 'makanan', 'makanan utama'], true)) {
            $category = $action === 'makanan utama' ? 'Makanan' : ucfirst($action);
            return [
                'intent' => 'RECOMMEND_MENU',
                'isRestaurantContext' => true,
                'confidence' => 1.0,
                'criteria' => [
                    'category' => $category,
                ],
                'recommendationSlots' => [],
                'entities' => [
                    'category' => $action === 'makanan utama' ? 'makanan utama' : $action,
                    'query_text' => $action,
                ]
            ];
        }

        if (in_array($action, ['pedas', 'manis', 'segar', 'gurih', 'asin', 'hangat', 'sharing_bersama', 'mengenyangkan', 'ringan'], true)) {
            $criteria = [
                'category' => null,
                'tags' => [$action],
                'taste' => in_array($action, ['pedas', 'manis', 'segar', 'gurih', 'asin'], true) ? [$action] : [],
                'portion' => in_array($action, ['mengenyangkan', 'ringan'], true) ? $action : null,
                'pricePreference' => null,
                'popularity' => null,
                'mood' => null,
                'menuName' => null
            ];
            return [
                'intent' => 'RECOMMEND_MENU',
                'isRestaurantContext' => true,
                'confidence' => 1.0,
                'criteria' => $criteria,
                'recommendationSlots' => [],
                'entities' => [
                    'preferred_tags' => [$action],
                    'query_text' => $action,
                ]
            ];
        }

        if ($action === 'di bawah 20000') {
            return [
                'intent' => 'RECOMMEND_MENU',
                'isRestaurantContext' => true,
                'confidence' => 1.0,
                'criteria' => [
                    'pricePreference' => 'murah',
                ],
                'recommendationSlots' => [],
                'entities' => [
                    'price_mode' => 'cheap',
                    'max_price' => 20000,
                    'query_text' => 'di bawah 20000',
                ]
            ];
        }

        if ($action === 'greeting_order') {
            return ['intent' => 'ADD_TO_CART', 'entities' => [], 'criteria' => null, 'recommendationSlots' => []];
        }

        if ($action === 'greeting_tracking') {
            return ['intent' => 'CHECK_ORDER', 'entities' => [], 'criteria' => null, 'recommendationSlots' => []];
        }

        if ($action === 'greeting_recommendation') {
            return ['intent' => 'RECOMMEND_MENU', 'entities' => [], 'criteria' => null, 'recommendationSlots' => []];
        }

        if ($action === 'greeting_view_cart') {
            return ['intent' => 'VIEW_CART', 'entities' => [], 'criteria' => null, 'recommendationSlots' => []];
        }

        if (str_starts_with($action, 'qty_')) {
            $payload = substr($action, strlen('qty_'));
            $parts = explode(':', (string) $payload);
            $qty = isset($parts[0]) ? (int) $parts[0] : 1;
            $menuId = trim((string) ($parts[1] ?? ''));

            return [
                'intent' => 'ADD_TO_CART',
                'entities' => [
                    'quantity' => max(1, $qty),
                    'menu_id' => $menuId,
                    'menu_name' => '',
                ],
                'criteria' => null,
                'recommendationSlots' => []
            ];
        }

        if (str_starts_with($action, 'suggest_menu:')) {
            $menuId = trim(substr($action, strlen('suggest_menu:')));
            return [
                'intent' => 'ADD_TO_CART',
                'entities' => [
                    'menu_id' => $menuId,
                    'menu_name' => '',
                    'quantity' => null,
                ],
                'criteria' => null,
                'recommendationSlots' => []
            ];
        }

        if ($action === 'confirm_checkout') {
            return ['intent' => 'confirm_checkout', 'entities' => [], 'criteria' => null, 'recommendationSlots' => []];
        }

        if (str_starts_with($action, 'checkout_type:')) {
            $type = trim(substr($action, strlen('checkout_type:')));
            return ['intent' => 'checkout_type_select', 'entities' => ['checkout_type' => $type], 'criteria' => null, 'recommendationSlots' => []];
        }

        if (str_starts_with($action, 'confirm_cancel:')) {
            $orderId = trim(substr($action, strlen('confirm_cancel:')));
            return ['intent' => 'confirm_cancel', 'entities' => ['order_id' => $orderId], 'criteria' => null, 'recommendationSlots' => []];
        }

        if (str_starts_with($action, 'cart_increase:')) {
            $payload = substr($action, strlen('cart_increase:'));
            $parts = explode(':', (string) $payload);
            return [
                'intent' => 'ADD_TO_CART',
                'entities' => [
                    'menu_id' => trim((string) ($parts[0] ?? '')),
                    'quantity' => max(1, (int) ($parts[1] ?? 1)),
                ],
                'criteria' => null,
                'recommendationSlots' => []
            ];
        }

        if (str_starts_with($action, 'cart_decrease:')) {
            $payload = substr($action, strlen('cart_decrease:'));
            $parts = explode(':', (string) $payload);
            return [
                'intent' => 'REMOVE_FROM_CART',
                'entities' => [
                    'menu_id' => trim((string) ($parts[0] ?? '')),
                    'quantity' => max(1, (int) ($parts[1] ?? 1)),
                ],
                'criteria' => null,
                'recommendationSlots' => []
            ];
        }

        if ($action === 'clear_cart_now') {
            return ['intent' => 'REMOVE_FROM_CART', 'entities' => [], 'criteria' => [ 'clear_cart' => true ], 'recommendationSlots' => []];
        }

        if (str_starts_with($action, 'recommend_relax_price:')) {
            $payload = substr($action, strlen('recommend_relax_price:'));
            $parts = explode(':', (string) $payload);
            $category = $this->mapCategoryTokenToValue((string) ($parts[0] ?? ''));
            $minPrice = max(0, (int) ($parts[1] ?? 0));
            $maxPrice = max(0, (int) ($parts[2] ?? 0));

            return [
                'intent' => 'RECOMMEND_MENU',
                'entities' => [
                    'category' => $category,
                    'price_mode' => 'range',
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'query_text' => '',
                ],
                'criteria' => [
                    'category' => $category === 'makanan utama' ? 'Makanan' : ($category === 'minuman' ? 'Minuman' : ($category === 'cemilan' ? 'Cemilan' : null)),
                    'price_mode' => 'range',
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                ],
                'recommendationSlots' => []
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
                'intent' => 'RECOMMEND_MENU',
                'entities' => [
                    'category' => $category,
                    'price_mode' => 'around',
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'target_price' => $targetPrice,
                    'query_text' => '',
                ],
                'criteria' => [
                    'category' => $category === 'makanan utama' ? 'Makanan' : ($category === 'minuman' ? 'Minuman' : ($category === 'cemilan' ? 'Cemilan' : null)),
                    'price_mode' => 'around',
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'target_price' => $targetPrice,
                ],
                'recommendationSlots' => []
            ];
        }

        if (str_starts_with($action, 'recommend_category:')) {
            $category = $this->mapCategoryTokenToValue(substr($action, strlen('recommend_category:')));
            return [
                'intent' => 'RECOMMEND_MENU',
                'entities' => [
                    'category' => $category,
                    'query_text' => '',
                ],
                'criteria' => [
                    'category' => $category === 'makanan utama' ? 'Makanan' : ($category === 'minuman' ? 'Minuman' : ($category === 'cemilan' ? 'Cemilan' : null)),
                ],
                'recommendationSlots' => []
            ];
        }

        if (str_starts_with($action, 'recommend_tag:')) {
            $payload = substr($action, strlen('recommend_tag:'));
            $parts = explode(':', (string) $payload);
            $category = $this->mapCategoryTokenToValue((string) ($parts[0] ?? ''));
            $tag = trim(strtolower((string) ($parts[1] ?? '')));
            return [
                'intent' => 'RECOMMEND_MENU',
                'entities' => [
                    'category' => $category,
                    'preferred_tags' => $tag !== '' ? [$tag] : [],
                    'query_text' => '',
                ],
                'criteria' => [
                    'category' => $category === 'makanan utama' ? 'Makanan' : ($category === 'minuman' ? 'Minuman' : ($category === 'cemilan' ? 'Cemilan' : null)),
                    'tags' => $tag !== '' ? [$tag] : [],
                ],
                'recommendationSlots' => []
            ];
        }

        return ['intent' => 'unknown_or_ambiguous', 'entities' => [], 'criteria' => null, 'recommendationSlots' => []];
    }

    private function extractPriceSignal(string $text): ?array
    {
        $range = $this->extractPriceRange($text);
        if (is_array($range)) {
            return $range;
        }

        $price = $this->extractPriceNumber($text);

        $isAround = $this->containsAny($text, ['sekitar', 'kisaran', 'kira-kira', 'kurang lebih', 'harganya sekitar']);
        $isMax = $this->containsAny($text, ['harga maksimal', 'maksimal', 'max', 'budget', 'di bawah', 'dibawah', 'kurang dari', 'under']);
        $isCheap = $this->containsAny($text, ['murah', 'termurah', 'yang murah']);

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

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function hasPriceContextSignal(string $text): bool
    {
        if ($this->containsAny($text, [
            'harga',
            'budget',
            'di bawah',
            'dibawah',
            'kurang dari',
            'kurang lebih',
            'tidak lebih dari',
            'ga lebih dari',
            'gak lebih dari',
            'maksimal',
            'max ',
            'sekitar',
            'kisaran',
            'kira-kira',
        ])) {
            return true;
        }

        if (preg_match('/\d+\s*(rb|ribu|k)\b/u', $text) === 1) {
            return true;
        }

        return false;
    }
}
