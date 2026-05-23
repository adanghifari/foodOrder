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

        if (!$response->successful()) {
            return null;
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            return null;
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
