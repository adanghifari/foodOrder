<?php

namespace App\Domains\Chatbot\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\MenuItem;

class GeminiNluService
{
    public function detectIntent(string $message): ?array
    {
        $apiKey = trim((string) config('services.gemini.api_key'));
        $primaryModel = trim((string) config('services.gemini.model', 'gemini-2.0-flash-lite'));
        $fallbackModel = trim((string) config('services.gemini.fallback_model', ''));
        $cacheTtl = max(0, (int) config('services.gemini.nlu_cache_ttl_seconds', 900));

        if ($apiKey === '' || trim($message) === '') {
            return null;
        }

        $normalizedMessage = $this->normalizeMessageForCache($message);
        $cacheKey = 'chatbot:gemini_nlu:' . sha1($normalizedMessage);
        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $models = array_values(array_filter([$primaryModel, $fallbackModel], fn ($m) => trim((string) $m) !== ''));
        $lastError = null;
        foreach ($models as $index => $model) {
            $payload = $this->detectIntentWithModel($message, $apiKey, (string) $model);
            if (is_array($payload) && !isset($payload['error_reason'])) {
                $payload['nlu_model_used'] = (string) $model;
                if ($cacheTtl > 0) {
                    Cache::put($cacheKey, $payload, now()->addSeconds($cacheTtl));
                }
                return $payload;
            }

            $errorReason = trim((string) ($payload['error_reason'] ?? 'upstream_error'));
            $lastError = $errorReason;
            $isLastModel = $index === count($models) - 1;
            if (!$isLastModel && $this->shouldTryFallbackModel($errorReason)) {
                continue;
            }
            break;
        }

        return ['error_reason' => $lastError ?? 'upstream_error'];
    }

    private function detectIntentWithModel(string $message, string $apiKey, string $model): ?array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . urlencode($model)
            . ':generateContent?key='
            . urlencode($apiKey);

        $prompt = $this->buildPrompt($message);

        try {
            $response = Http::timeout(8)->acceptJson()->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Gemini connection error: ' . $e->getMessage());
            return [
                'error_reason' => 'network_error',
            ];
        }

        if (!$response->successful()) {
            $statusCode = (int) $response->status();
            $statusText = strtoupper((string) data_get($response->json(), 'error.status', ''));
            $messageText = strtoupper((string) data_get($response->json(), 'error.message', ''));

            if ($statusCode === 429 || str_contains($statusText, 'RESOURCE_EXHAUSTED') || str_contains($messageText, 'QUOTA')) {
                return [
                    'error_reason' => 'quota_exhausted',
                ];
            }

            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'error_reason' => 'auth_error',
                ];
            }

            return [
                'error_reason' => 'upstream_error',
            ];
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            return [
                'error_reason' => 'invalid_ai_payload',
            ];
        }

        // Parse legacy entities structure from criteria
        $legacyEntities = $this->mapCriteriaToLegacyEntities($decoded['criteria'] ?? null);

        return [
            'intent' => (string) ($decoded['intent'] ?? ''),
            'isRestaurantContext' => (bool) ($decoded['isRestaurantContext'] ?? true),
            'confidence' => (float) ($decoded['confidence'] ?? 0),
            'criteria' => is_array($decoded['criteria'] ?? null) ? $decoded['criteria'] : null,
            'recommendationSlots' => is_array($decoded['recommendationSlots'] ?? null) ? $decoded['recommendationSlots'] : [],
            'entities' => $legacyEntities
        ];
    }

    private function mapCriteriaToLegacyEntities(?array $criteria): array
    {
        if (empty($criteria)) {
            return [];
        }

        $taste = null;
        if (!empty($criteria['taste'])) {
            $t = $criteria['taste'][0];
            $taste = $t === 'pedas' ? 'spicy' : ($t === 'manis' ? 'sweet' : ($t === 'segar' ? 'fresh' : null));
        }

        $category = null;
        if (!empty($criteria['category'])) {
            $cat = strtolower(trim($criteria['category']));
            if ($cat === 'makanan' || $cat === 'makanan utama') {
                $category = 'makanan utama';
            } elseif ($cat === 'minuman') {
                $category = 'minuman';
            } elseif ($cat === 'cemilan') {
                $category = 'cemilan';
            }
        }

        $priceMode = null;
        $maxPrice = null;
        if (($criteria['pricePreference'] ?? '') === 'murah') {
            $priceMode = 'cheap';
            $maxPrice = 20000;
        }

        return [
            'taste' => $taste,
            'taste_intensity' => 'normal',
            'category' => $category,
            'light' => ($criteria['portion'] ?? '') === 'ringan',
            'filling' => ($criteria['portion'] ?? '') === 'mengenyangkan',
            'required_tags' => $criteria['tags'] ?? [],
            'preferred_tags' => $criteria['tags'] ?? [],
            'price_mode' => $priceMode,
            'max_price' => $maxPrice,
            'query_text' => $criteria['menuName'] ?? '',
            'menu_name' => $criteria['menuName'] ?? null,
        ];
    }

    public function generateNaturalResponse(string $userMessage, array $backendResult, string $intent): ?string
    {
        $apiKey = trim((string) config('services.gemini.api_key'));
        $model = trim((string) config('services.gemini.model', 'gemini-2.0-flash-lite'));

        if ($apiKey === '') {
            return null;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . urlencode($model)
            . ':generateContent?key='
            . urlencode($apiKey);

        $backendJson = json_encode($backendResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Kamu adalah KedaiBot, chatbot ramah dan santai dari KedaiKlik.
Tugasmu adalah membuat balasan (reply) percakapan dalam bahasa Indonesia yang natural dan santai berdasarkan hasil backend restoran kami.

Gaya bahasa:
- Maksimal 2-3 kalimat. Singkat dan manusiawi.
- Jangan terlalu formal atau kaku. Gunakan bahasa santai ("aku", "kamu", "ya", "dong", "nih").
- Sebutkan alasan singkat mengapa menu tersebut direkomendasikan.
- Jangan mengulang-ulang kalimat template yang sama.
- Jika tidak ada menu yang cocok persis, berikan alternatif terdekat.
- Jika user bingung atau detail maunya belum jelas, tawarkan pilihan kategori (makanan utama, cemilan, minuman).
- Jangan pernah mengarang menu, stok, atau harga di luar data backend yang diberikan!

Data dari backend:
{$backendJson}

Intent: {$intent}
Pesan customer sebelumnya: "{$userMessage}"

Balas HANYA dengan string teks balasan chatbot saja, tanpa penjelasan tambahan, tanpa format markdown, tanpa JSON, tanpa tanda kutip di luar teks.
PROMPT;

        try {
            $response = Http::timeout(8)->acceptJson()->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                ],
            ]);

            if ($response->successful()) {
                $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
                return trim($text) !== '' ? trim($text) : null;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to generate natural response from Gemini: ' . $e->getMessage());
        }

        return null;
    }

    private function normalizeMessageForCache(string $message): string
    {
        $text = strtolower(trim($message));
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return (string) $text;
    }

    private function shouldTryFallbackModel(string $errorReason): bool
    {
        return in_array($errorReason, [
            'network_error',
            'upstream_error',
            'invalid_ai_payload',
            'quota_exhausted',
        ], true);
    }

    private function buildPrompt(string $message): string
    {
        $categoryTags = config('menu_taxonomy.category_tags', []);

        // Dinamisasi kategori dari config taxonomy
        $categoriesList = array_map(function($c) {
            return ucwords(trim($c)); // e.g. "Makanan Utama", "Minuman", "Cemilan"
        }, array_keys($categoryTags));
        $validCategories = implode(', ', $categoriesList);

        $allowedTags = implode(', ', config('menu_taxonomy.allowed_tags', []));

        // Ambil menu summary aktif (stock > 0)
        $activeMenus = [];
        try {
            $activeMenus = MenuItem::where('stock', '>', 0)
                ->get(['_id', 'name', 'category', 'tags', 'price'])
                ->map(fn($item) => [
                    'id' => (string) $item->_id,
                    'name' => $item->name,
                    'category' => ucwords($item->category),
                    'tags' => $item->tags ?? [],
                    'price' => (int) $item->price
                ])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch active menus for Gemini NLU prompt: ' . $e->getMessage());
        }
        $activeMenuSummaryJson = json_encode($activeMenus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Kamu adalah NLU parser untuk chatbot restoran bernama KedaiBot.

Tugasmu bukan menjawab customer secara bebas.
Tugasmu hanya mengubah pesan customer menjadi JSON intent, criteria, dan recommendationSlots untuk pencarian menu.

Selalu anggap pesan customer masih berhubungan dengan restoran, makanan, minuman, rekomendasi, rasa, harga, porsi, mood makan, keranjang, atau pemesanan, kecuali benar-benar jelas di luar konteks.

Gunakan hanya kategori yang tersedia:
[{$validCategories}]

Gunakan hanya tags/metadata yang tersedia:
[{$allowedTags}]

Daftar menu aktif yang tersedia saat ini (stock > 0):
{$activeMenuSummaryJson}

Intent yang valid:
- RECOMMEND_MENU
- ASK_MENU_DETAIL
- ASK_PRICE
- ASK_CATEGORY
- ADD_TO_CART
- REMOVE_FROM_CART
- VIEW_CART
- CHECK_ORDER
- SMALL_TALK_RESTAURANT
- OUT_OF_SCOPE

Output harus JSON valid saja, tanpa markdown, tanpa penjelasan tambahan.

Format output:
{
  "intent": "...",
  "isRestaurantContext": true,
  "confidence": 0.0,
  "criteria": {
    "category": null,
    "tags": [],
    "taste": [],
    "portion": null,
    "pricePreference": null,
    "popularity": null,
    "mood": null,
    "menuName": null
  },
  "recommendationSlots": []
}

Aturan:
- Jika customer meminta satu kebutuhan saja, isi criteria.
- Jika customer meminta beberapa kebutuhan sekaligus, isi recommendationSlots (dan set criteria ke null).
- Jangan gabungkan beberapa kebutuhan berbeda menjadi satu criteria yang mustahil.
- Contoh multi-slot:
  'makanan pedas, cemilan gurih, minuman segar'
  harus menjadi 3 slot di recommendationSlots:
  1. {"slotName": "makanan_pedas", "category": "Makanan Utama", "criteria": {"tags": ["pedas"], "taste": ["pedas"]}}
  2. {"slotName": "cemilan_gurih", "category": "Cemilan", "criteria": {"tags": ["gurih"], "taste": ["gurih"]}}
  3. {"slotName": "minuman_segar", "category": "Minuman", "criteria": {"tags": ["segar"], "taste": ["segar"]}}
- Jika customer berkata 'aku laper', 'makan siang', atau 'makan berat', arahkan ke portion: "mengenyangkan".
- Jika customer berkata 'lagi hujan', arahkan ke mood: "hujan" atau tags: ["hangat"].
- Jika customer berkata 'yang aman', 'yang enak', 'rekomendasiin', atau 'bingung', arahkan ke popularity: "populer" atau mood: "bingung".
- Jika customer berkata 'bokek', 'hemat', 'ga mahal', arahkan ke pricePreference: "murah".
- Jika customer berkata 'seger', 'adem', 'dingin', arahkan ke category: "Minuman" atau taste: ["segar"].
- Jika customer berkata 'cuaca panas', 'lagi panas', 'panas banget', atau 'panas gini', arahkan ke category: "Minuman" atau taste: ["segar"] atau tags: ["segar", "dingin", "cocok_cuaca_panas"].
- Jangan mengarang menu di luar daftar menu aktif yang diberikan.
- Jika customer menyebutkan menu yang ada di daftar menu aktif (atau sangat mirip/typo/singkatan), isi criteria.menuName dengan nama menu persis yang ada di daftar menu aktif (misal customer bilang "gepreknya ada?" maka menuName diset "Nasi Ayam Geprek Joss").
- Jangan mengarang tags di luar daftar tags yang tersedia.
- Jangan mengarang kategori di luar daftar kategori yang tersedia.
- Jika tidak yakin tapi masih konteks restoran, tetap pilih RECOMMEND_MENU atau SMALL_TALK_RESTAURANT dengan confidence sedang/rendah.
- Gunakan OUT_OF_SCOPE hanya jika pesan benar-benar tidak berkaitan dengan restoran (misalnya pertanyaan politik, coding, berita umum).

Pesan customer:
{$message}
PROMPT;
    }
}
