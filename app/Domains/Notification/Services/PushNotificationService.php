<?php

namespace App\Domains\Notification\Services;

use App\Models\DeviceToken;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function sendOrderStatusChanged(Order $order, string $previousStatus, string $nextStatus): void
    {
        if ($previousStatus === $nextStatus) {
            return;
        }

        [$title, $body] = $this->buildOrderStatusMessage($order, $nextStatus);

        $this->sendToOrderOwner(
            $order,
            $title,
            $body,
            [
                'type' => 'order_status',
                'order_id' => (string) $order->_id,
                'previous_status' => $previousStatus,
                'current_status' => $nextStatus,
            ]
        );
    }

    public function sendPaymentStatusChanged(Order $order, string $previousStatus, string $nextStatus): void
    {
        if ($previousStatus === $nextStatus) {
            return;
        }

        $title = 'Status Pembayaran Diperbarui';
        $body = 'Pembayaran pesanan #' . strtoupper(substr((string) $order->_id, -6)) . ' sekarang ' . $this->humanizePaymentStatus($nextStatus) . '.';

        $this->sendToOrderOwner(
            $order,
            $title,
            $body,
            [
                'type' => 'payment_status',
                'order_id' => (string) $order->_id,
                'previous_payment_status' => $previousStatus,
                'current_payment_status' => $nextStatus,
            ]
        );
    }

    private function sendToOrderOwner(Order $order, string $title, string $body, array $data = []): void
    {
        $userId = trim((string) ($order->customer_id ?? ''));
        if ($userId === '') {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->pluck('token')
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '')
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $token) {
            $this->sendSingleToken($token, $title, $body, $data);
        }
    }

    private function sendSingleToken(string $token, string $title, string $body, array $data = []): void
    {
        $projectId = trim((string) config('services.fcm.project_id', ''));
        $serviceAccountPath = trim((string) config('services.fcm.service_account_json', ''));
        if ($projectId === '' || $serviceAccountPath === '') {
            return;
        }

        try {
            $accessToken = $this->getAccessToken($serviceAccountPath);
            if ($accessToken === null) {
                return;
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->post('https://fcm.googleapis.com/v1/projects/' . urlencode($projectId) . '/messages:send', [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $this->stringifyData($data),
                        'android' => [
                            'priority' => 'high',
                        ],
                        'apns' => [
                            'headers' => [
                                'apns-priority' => '10',
                            ],
                        ],
                    ],
                ]);

            if ($response->status() === 401) {
                Cache::forget($this->accessTokenCacheKey($serviceAccountPath));
                return;
            }

            if ($response->status() === 404 || $response->status() === 400) {
                $body = strtolower((string) $response->body());
                if (str_contains($body, 'unregistered') || str_contains($body, 'invalid_argument')) {
                    DeviceToken::query()->where('token', $token)->delete();
                }
            }

            if (!$response->successful()) {
                Log::warning('FCM push failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('FCM push exception', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function getAccessToken(string $serviceAccountPath): ?string
    {
        $cacheKey = $this->accessTokenCacheKey($serviceAccountPath);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $serviceAccount = null;
        if (str_starts_with(trim($serviceAccountPath), '{')) {
            $serviceAccount = json_decode($serviceAccountPath, true);
            if (!is_array($serviceAccount)) {
                Log::warning('FCM service account JSON string invalid');
                return null;
            }
        } else {
            if (!is_file($serviceAccountPath) || !is_readable($serviceAccountPath)) {
                Log::warning('FCM service account file is not readable', [
                    'path' => $serviceAccountPath,
                ]);
                return null;
            }

            $raw = @file_get_contents($serviceAccountPath);
            if ($raw === false || trim($raw) === '') {
                Log::warning('FCM service account file is empty', ['path' => $serviceAccountPath]);
                return null;
            }

            $serviceAccount = json_decode($raw, true);
            if (!is_array($serviceAccount)) {
                Log::warning('FCM service account JSON invalid', ['path' => $serviceAccountPath]);
                return null;
            }
        }

        $clientEmail = trim((string) ($serviceAccount['client_email'] ?? ''));
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');
        if ($clientEmail === '' || $privateKey === '') {
            Log::warning('FCM service account JSON missing required fields', ['path' => $serviceAccountPath]);
            return null;
        }

        $now = time();
        $claims = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $jwt = $this->buildJwt($claims, $privateKey);
        if ($jwt === null) {
            return null;
        }

        $tokenResponse = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$tokenResponse->successful()) {
            Log::warning('Failed to get FCM OAuth token', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);
            return null;
        }

        $json = (array) $tokenResponse->json();
        $accessToken = trim((string) ($json['access_token'] ?? ''));
        $expiresIn = (int) ($json['expires_in'] ?? 3600);
        if ($accessToken === '') {
            return null;
        }

        $ttl = max(60, $expiresIn - 120);
        Cache::put($cacheKey, $accessToken, now()->addSeconds($ttl));
        return $accessToken;
    }

    private function buildJwt(array $claims, string $privateKey): ?string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));
        if ($encodedHeader === null || $encodedPayload === null) {
            return null;
        }

        $toSign = $encodedHeader . '.' . $encodedPayload;
        $signature = '';
        $ok = openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            Log::warning('Failed to sign FCM JWT');
            return null;
        }

        return $toSign . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): ?string
    {
        $encoded = base64_encode($value);
        if ($encoded === false) {
            return null;
        }

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    private function stringifyData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = (string) $value;
        }

        return $result;
    }

    private function accessTokenCacheKey(string $serviceAccountPath): string
    {
        return 'fcm_http_v1_access_token_' . sha1($serviceAccountPath);
    }

    private function humanizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PENDING_PAYMENT' => 'Menunggu Pembayaran',
            'PAYMENT_FAILED' => 'Pembayaran Gagal',
            'CONFIRMED' => 'Dikonfirmasi',
            'IN_QUEUE' => 'Dalam Antrean',
            'IN_PROGRESS' => 'Sedang Diproses',
            'DELIVERED' => 'Sudah Disajikan',
            default => ucwords(strtolower(str_replace('_', ' ', $status))),
        };
    }

    private function buildOrderStatusMessage(Order $order, string $nextStatus): array
    {
        $statusLabel = $this->humanizeStatus($nextStatus);
        $orderType = strtolower(trim((string) ($order->order_type ?? '')));

        // Booking dine-in and dine-in langsung share the same dine-in dining experience.
        $isDineIn = in_array($orderType, ['dine_in', 'booking_dine_in'], true);
        $isPickup = in_array($orderType, ['pickup', 'takeaway', 'take_away'], true);
        $isDelivered = strtoupper($nextStatus) === 'DELIVERED';

        if ($isDelivered && $isDineIn) {
            return [
                "Status pesanan Anda $statusLabel",
                'Selamat Menikmati!',
            ];
        }

        if ($isDelivered && $isPickup) {
            return [
                'Status pesanan Anda sudah siap',
                'Silahkan ambil pesanan anda',
            ];
        }

        return [
            "Status pesanan Anda $statusLabel",
            'Status pesanan Anda berhasil diperbarui.',
        ];
    }

    private function humanizePaymentStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PENDING' => 'Menunggu',
            'PAID', 'SUCCESS', 'SETTLEMENT' => 'Lunas',
            'FAILED' => 'Gagal',
            'CANCELED' => 'Dibatalkan',
            'EXPIRED' => 'Kedaluwarsa',
            default => ucwords(strtolower(str_replace('_', ' ', $status))),
        };
    }
}
