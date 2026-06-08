<?php

namespace App\Domains\Chatbot\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
        } catch (\Throwable) {
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

        return [
            'scope' => (string) ($decoded['scope'] ?? ''),
            'intent' => (string) ($decoded['intent'] ?? ''),
            'confidence' => (float) ($decoded['confidence'] ?? 0),
            'reason_short' => (string) ($decoded['reason_short'] ?? ''),
            'conversational_reply' => (string) ($decoded['conversational_reply'] ?? ''),
            'needs_clarification' => (bool) ($decoded['needs_clarification'] ?? false),
            'entities' => is_array($decoded['entities'] ?? null) ? $decoded['entities'] : [],
        ];
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
        return <<<PROMPT
Klasifikasikan intent chat user untuk aplikasi food order.
Balas HANYA JSON valid (tanpa markdown), format:
{"scope":"...","intent":"...","confidence":0.0,"reason_short":"...","conversational_reply":"...","needs_clarification":false,"entities":{}}

scope yang diizinkan:
- restaurant_in_scope
- out_of_scope

Intent yang diizinkan:
- greeting
- small_talk
- out_of_scope
- order_menu
- tracking_order
- menu_recommendation
- view_cart
- checkout_request
- cancel_order_request
- unknown_or_ambiguous

Aturan:
- Jangan hasilkan intent konfirmasi transaksi.
- Untuk topik di luar konteks restoran/food ordering, gunakan out_of_scope.
- Jika topik di luar restoran, set scope=out_of_scope, intent=out_of_scope, dan reason_short singkat.
- Jika topik masih seputar restoran/food order, set scope=restaurant_in_scope.
- Jika tidak yakin, pakai unknown_or_ambiguous.
- conversational_reply:
  - Untuk scope restaurant_in_scope, isi dengan kalimat natural singkat (maks 2 kalimat) untuk membantu user.
  - Boleh berupa pertanyaan klarifikasi (contoh: preferensi rasa/kategori/budget).
  - Jangan menyebut data stok/harga spesifik yang tidak pasti.
  - Untuk out_of_scope boleh kosong.
- needs_clarification:
  - true jika user masih ambigu dan kamu perlu tanya balik dulu sebelum rekomendasi final.
  - false jika informasi sudah cukup untuk menampilkan rekomendasi.
- Untuk menu_recommendation, entities boleh memuat:
  - taste: spicy|sweet|fresh
  - taste_intensity: normal|high
  - category: makanan utama|cemilan|minuman
  - required_tags: array tag dari daftar [pedas,gurih,manis,asam,segar,ringan,mengenyangkan,renyah,berkuah,sarapan,makan_siang,makan_malam,ramah_anak,sharing_bersama]
  - light: boolean
  - filling: boolean
  - calorie_level: low|medium|high
  - max_price: integer
  - query_text: salin pesan user
- Jika user menyebut constraint sempit (contoh: "buat berbagi"), masukkan di required_tags sebagai filter WAJIB.
- Jangan isi field yang tidak relevan.
- entities juga bisa memuat menu_name, quantity untuk order.

User message: {$message}
PROMPT;
    }
}
