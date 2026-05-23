<?php

namespace App\Domains\Chatbot\Services;

use Illuminate\Support\Facades\Http;

class GeminiNluService
{
    public function detectIntent(string $message): ?array
    {
        $apiKey = trim((string) config('services.gemini.api_key'));
        $model = trim((string) config('services.gemini.model', 'gemini-2.0-flash-lite'));

        if ($apiKey === '' || trim($message) === '') {
            return null;
        }

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
            'intent' => (string) ($decoded['intent'] ?? ''),
            'confidence' => (float) ($decoded['confidence'] ?? 0),
            'entities' => is_array($decoded['entities'] ?? null) ? $decoded['entities'] : [],
        ];
    }

    private function buildPrompt(string $message): string
    {
        return <<<PROMPT
Klasifikasikan intent chat user untuk aplikasi food order.
Balas HANYA JSON valid (tanpa markdown), format:
{"intent":"...","confidence":0.0,"entities":{}}

Intent yang diizinkan:
- greeting
- order_menu
- tracking_order
- menu_recommendation
- view_cart
- checkout_request
- cancel_order_request
- unknown_or_ambiguous

Aturan:
- Jangan hasilkan intent konfirmasi transaksi.
- Jika tidak yakin, pakai unknown_or_ambiguous.
- entities bisa memuat menu_name, quantity, taste, max_price.

User message: {$message}
PROMPT;
    }
}
