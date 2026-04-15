<?php

namespace App\Domains\Payment\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class PaymentService
{
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];
    private const FAILED_STATUSES = ['FAILED', 'CANCELED', 'EXPIRED'];

    public function listPayments()
    {
        return Order::orderBy('_id', 'desc')->get()->map(function (Order $order) {
            return [
                'order_id' => (string) $order->_id,
                'midtrans_order_id' => $order->midtrans_order_id ?? null,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_type' => $order->payment_type ?? null,
                'total_price' => (float) ($order->total_price ?? 0),
            ];
        });
    }

    public function createTransaction(string $orderId, ?array $customerDetails = null, ?string $finishRedirectUrlOverride = null): array
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $isProduction = (bool) config('services.midtrans.is_production', false);
        $callbackUrl = trim((string) config('services.midtrans.callback_url', ''));
        $finishRedirectUrl = trim((string) ($finishRedirectUrlOverride ?? config('services.midtrans.finish_redirect_url', '')));

        if ($finishRedirectUrl === '') {
            $appUrl = rtrim((string) config('app.url', ''), '/');
            if ($appUrl !== '') {
                $finishRedirectUrl = $appUrl . '/frontliner/pembayaran/selesai';
            }
        }

        if ($serverKey === '') {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Midtrans server key is not configured',
            ];
        }

        $order = Order::find($orderId);

        if (!$order) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Order not found',
            ];
        }

        $grossAmount = (int) round((float) ($order->total_price ?? 0));

        if ($grossAmount <= 0) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Order total must be greater than 0',
            ];
        }

        $customer = null;
        if (!empty($order->customer_id)) {
            $customer = User::find((string) $order->customer_id);
        }

        $customerName = (string) ($customerDetails['name'] ?? $customer->name ?? 'Customer');
        $customerEmail = (string) ($customerDetails['email'] ?? (($customer->email ?? null) ?: ($customer->username ?? 'customer@example.com')));
        $customerPhone = (string) ($customerDetails['phone'] ?? $customer->no_telp ?? '');

        $midtransOrderId = $order->midtrans_order_id ?: ('ORDER-' . (string) $order->_id . '-' . now()->timestamp);

        $payload = [
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $customerName,
                'email' => $customerEmail,
                'phone' => $customerPhone,
            ],
            'item_details' => [
                [
                    'id' => (string) $order->_id,
                    'name' => 'Order #' . strtoupper(substr((string) $order->_id, -6)),
                    'price' => $grossAmount,
                    'quantity' => 1,
                ],
            ],
            'enabled_payments' => ['qris', 'gopay', 'bank_transfer', 'echannel', 'cstore'],
        ];

        if ($finishRedirectUrl !== '') {
            $payload['callbacks'] = [
                'finish' => $finishRedirectUrl,
                'pending' => $finishRedirectUrl,
                'error' => $finishRedirectUrl,
            ];
        }

        $snapUrl = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $request = Http::withBasicAuth($serverKey, '')->acceptJson();

        if ($callbackUrl !== '') {
            $request = $request->withHeaders([
                'X-Override-Notification' => $callbackUrl,
            ]);
        }

        $response = $request->post($snapUrl, $payload);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'status' => 502,
                'message' => 'Failed to create Midtrans transaction',
                'data' => $response->json() ?: ['raw' => $response->body()],
            ];
        }

        $snapData = $response->json();

        $order->update([
            'midtrans_order_id' => $midtransOrderId,
            'payment_status' => 'PENDING',
            'payment_url' => $snapData['redirect_url'] ?? null,
            'payment_payload' => $this->sanitizePaymentPayload($snapData),
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Payment transaction created',
            'data' => [
                'order_id' => (string) $order->_id,
                'midtrans_order_id' => $midtransOrderId,
                'snap_token' => $snapData['token'] ?? null,
                'redirect_url' => $snapData['redirect_url'] ?? null,
            ],
        ];
    }

    public function processWebhook(array $payload): array
    {
        $midtransOrderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');
        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));
        $paymentType = (string) ($payload['payment_type'] ?? '');

        $serverKey = (string) config('services.midtrans.server_key');
        $expectedSignature = hash('sha512', $midtransOrderId . $statusCode . $grossAmount . $serverKey);

        if ($signatureKey === '' || !hash_equals($expectedSignature, $signatureKey)) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Invalid signature',
            ];
        }

        $order = Order::where('midtrans_order_id', $midtransOrderId)->first();

        if (!$order && str_starts_with($midtransOrderId, 'ORDER-')) {
            $parts = explode('-', $midtransOrderId);
            if (count($parts) >= 3) {
                $fallbackId = (string) ($parts[1] ?? '');
                if ($fallbackId !== '') {
                    $order = Order::find($fallbackId);
                }
            }
        }

        if (!$order) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Order not found',
            ];
        }

        $paymentStatus = $this->mapMidtransStatus($transactionStatus, $fraudStatus);

        $this->applyPaymentUpdate($order, [
            'midtrans_order_id' => $midtransOrderId,
            'payment_status' => $paymentStatus,
            'payment_type' => $paymentType,
            'payment_payload' => $this->sanitizePaymentPayload($payload),
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Webhook processed',
            'data' => [
                'order_id' => (string) $order->_id,
                'payment_status' => $paymentStatus,
            ],
        ];
    }

    public function syncTransactionStatus(string $midtransOrderId): array
    {
        $midtransOrderId = trim($midtransOrderId);

        if ($midtransOrderId === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'order_id is required',
            ];
        }

        $serverKey = (string) config('services.midtrans.server_key');
        $isProduction = (bool) config('services.midtrans.is_production', false);

        if ($serverKey === '') {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Midtrans server key is not configured',
            ];
        }

        $statusUrl = ($isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com')
            . '/v2/' . urlencode($midtransOrderId) . '/status';

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->get($statusUrl);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'status' => 502,
                'message' => 'Failed to fetch Midtrans transaction status',
                'data' => $response->json() ?: ['raw' => $response->body()],
            ];
        }

        $payload = (array) $response->json();
        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));
        $paymentType = (string) ($payload['payment_type'] ?? '');

        $order = Order::where('midtrans_order_id', $midtransOrderId)->first();

        if (!$order && str_starts_with($midtransOrderId, 'ORDER-')) {
            $parts = explode('-', $midtransOrderId);
            if (count($parts) >= 3) {
                $fallbackId = (string) ($parts[1] ?? '');
                if ($fallbackId !== '') {
                    $order = Order::find($fallbackId);
                }
            }
        }

        if (!$order) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Order not found',
                'data' => $payload,
            ];
        }

        $paymentStatus = $this->mapMidtransStatus($transactionStatus, $fraudStatus);

        $this->applyPaymentUpdate($order, [
            'midtrans_order_id' => $midtransOrderId,
            'payment_status' => $paymentStatus,
            'payment_type' => $paymentType,
            'payment_payload' => $this->sanitizePaymentPayload($payload),
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Transaction status synchronized',
            'data' => [
                'order_id' => (string) $order->_id,
                'midtrans_order_id' => $midtransOrderId,
                'payment_status' => $paymentStatus,
            ],
        ];
    }

    private function mapMidtransStatus(string $transactionStatus, string $fraudStatus): string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge' ? 'PENDING' : 'PAID',
            'settlement' => 'PAID',
            'pending' => 'PENDING',
            'deny' => 'FAILED',
            'cancel' => 'CANCELED',
            'expire' => 'EXPIRED',
            default => strtoupper($transactionStatus),
        };
    }

    private function applyPaymentUpdate(Order $order, array $attributes): void
    {
        $paymentStatus = strtoupper((string) ($attributes['payment_status'] ?? $order->payment_status ?? 'PENDING'));
        $currentStatus = strtoupper((string) ($order->status ?? ''));

        if (in_array($paymentStatus, self::PAID_STATUSES, true)) {
            $attributes['status'] = $currentStatus === '' || $currentStatus === 'PENDING_PAYMENT'
                ? 'CONFIRMED'
                : $order->status;

            $attributes['paid_at'] = $order->paid_at ?? now();
        } elseif (in_array($paymentStatus, self::FAILED_STATUSES, true)) {
            $attributes['status'] = $currentStatus === '' ? 'PENDING_PAYMENT' : $order->status;
            $attributes['paid_at'] = $order->paid_at;
        }

        $order->update($attributes);
    }

    private function sanitizePaymentPayload(array $payload): array
    {
        $allowedKeys = [
            'order_id',
            'status_code',
            'gross_amount',
            'transaction_status',
            'fraud_status',
            'payment_type',
            'transaction_time',
            'settlement_time',
            'signature_key',
            'token',
            'redirect_url',
            'va_numbers',
            'permata_va_number',
            'bill_key',
            'biller_code',
            'store',
            'payment_code',
            'expiry_time',
        ];

        $sanitized = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $sanitized[$key] = $payload[$key];
            }
        }

        return $sanitized;
    }
}
