<?php

namespace App\Domains\Payment\Services;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\Response;
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

    public function createTransaction(
        string $orderId,
        ?array $customerDetails = null,
        ?string $finishRedirectUrlOverride = null,
        bool $forceNewTransaction = false
    ): array
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $isProduction = (bool) config('services.midtrans.is_production', false);
        $callbackUrl = trim((string) config('services.midtrans.callback_url', ''));
        $finishRedirectUrl = trim((string) ($finishRedirectUrlOverride ?? config('services.midtrans.finish_redirect_url', '')));

        if ($finishRedirectUrl === '') {
            $appUrl = rtrim((string) config('app.url', ''), '/');
            if ($appUrl !== '') {
                $finishRedirectUrl = $appUrl . '/kedai/pembayaran/selesai';
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

        $reserveResult = $this->reserveStockForOrder($order);
        if (!($reserveResult['ok'] ?? false)) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => (string) ($reserveResult['message'] ?? 'Stok tidak mencukupi untuk memproses pembayaran'),
            ];
        }

        $customer = null;
        if (!empty($order->customer_id)) {
            $customer = User::find((string) $order->customer_id);
        }

        $customerName = (string) ($customerDetails['name'] ?? $customer->name ?? 'Customer');
        $customerEmail = (string) ($customerDetails['email'] ?? (($customer->email ?? null) ?: ($customer->username ?? 'customer@example.com')));
        $customerPhone = (string) ($customerDetails['phone'] ?? $customer->no_telp ?? '');

        $midtransOrderId = $forceNewTransaction
            ? ('ORDER-' . (string) $order->_id . '-' . now()->timestamp)
            : ($order->midtrans_order_id ?: ('ORDER-' . (string) $order->_id . '-' . now()->timestamp));

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
            if (($reserveResult['reserved_now'] ?? false) === true) {
                $this->restoreStockForOrder($order, true);
            }

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
            'status' => 'PENDING_PAYMENT',
            'payment_status' => 'PENDING',
            'payment_type' => null,
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

    public function cancelTransaction(string $midtransOrderId, bool $syncLocal = true): array
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

        $response = $this->postMidtransTransactionAction($serverKey, $isProduction, $midtransOrderId, 'cancel');
        $midtransAction = 'cancel';
        $cancelError = null;

        if (!$response->successful()) {
            $cancelError = $response->json() ?: ['raw' => $response->body()];

            // Some pending payment channels can no longer be canceled directly
            // once a method is chosen. For those cases, expire as a fallback.
            $expireResponse = $this->postMidtransTransactionAction($serverKey, $isProduction, $midtransOrderId, 'expire');

            if (!$expireResponse->successful()) {
                return [
                    'ok' => false,
                    'status' => 502,
                    'message' => 'Failed to cancel Midtrans transaction',
                    'data' => [
                        'cancel' => $cancelError,
                        'expire' => $expireResponse->json() ?: ['raw' => $expireResponse->body()],
                    ],
                ];
            }

            $response = $expireResponse;
            $midtransAction = 'expire';
        }

        $payload = (array) $response->json();
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

        if ($order && $syncLocal) {
            $mergedPayload = array_merge(
                is_array($order->payment_payload ?? null) ? $order->payment_payload : [],
                $this->sanitizePaymentPayload($payload)
            );

            $this->applyPaymentUpdate($order, [
                'midtrans_order_id' => $midtransOrderId,
                'payment_status' => 'CANCELED',
                'payment_type' => (string) ($payload['payment_type'] ?? $order->payment_type ?? ''),
                'payment_payload' => $mergedPayload,
            ]);
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Transaction canceled',
            'data' => [
                'order_id' => (string) ($order?->_id ?? ''),
                'midtrans_order_id' => $midtransOrderId,
                'payment_status' => $syncLocal ? 'CANCELED' : (string) ($order?->payment_status ?? 'PENDING'),
                'midtrans_action' => $midtransAction,
                'cancel_response' => $payload,
                'cancel_error' => $cancelError,
            ],
        ];
    }

    private function postMidtransTransactionAction(
        string $serverKey,
        bool $isProduction,
        string $midtransOrderId,
        string $action
    ): Response {
        $actionUrl = ($isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com')
            . '/v2/' . urlencode($midtransOrderId) . '/' . $action;

        return Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->post($actionUrl);
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
            $nextStatus = $currentStatus === '' || $currentStatus === 'PENDING_PAYMENT'
                ? 'CONFIRMED'
                : $order->status;
            $attributes['status'] = $nextStatus;

            $attributes['paid_at'] = $order->paid_at ?? now();
        } elseif (in_array($paymentStatus, self::FAILED_STATUSES, true)) {
            $attributes['status'] = in_array($currentStatus, ['', 'PENDING_PAYMENT', 'PAYMENT_FAILED'], true)
                ? 'PAYMENT_FAILED'
                : $order->status;
            $attributes['paid_at'] = $order->paid_at;
        }

        $order->update($attributes);

        // Reservation is released only on explicit cancel.
        if ($paymentStatus === 'CANCELED') {
            $this->restoreStockForOrder($order);
        }
    }

    private function reserveStockForOrder(Order $order): array
    {
        if (!empty($order->stock_reserved_at)) {
            return [
                'ok' => true,
                'reserved_now' => false,
            ];
        }

        $quantities = $this->buildOrderItemQuantities($order);
        if ($quantities === []) {
            return [
                'ok' => false,
                'message' => 'Item order tidak valid untuk reservasi stok',
            ];
        }

        $reserved = [];
        foreach ($quantities as $menuId => $qty) {
            $updated = MenuItem::where('_id', $menuId)
                ->where('stock', '>=', $qty)
                ->decrement('stock', $qty);

            if ($updated !== 1) {
                foreach ($reserved as $reservedMenuId => $reservedQty) {
                    MenuItem::where('_id', $reservedMenuId)->increment('stock', $reservedQty);
                }

                $menuName = (string) optional(MenuItem::find($menuId))->name;
                $menuLabel = $menuName !== '' ? $menuName : $menuId;

                return [
                    'ok' => false,
                    'message' => 'Stok menu "' . $menuLabel . '" tidak mencukupi.',
                ];
            }

            $reserved[$menuId] = $qty;
        }

        $order->update([
            'stock_reserved_at' => now(),
            'stock_restored_at' => null,
        ]);

        return [
            'ok' => true,
            'reserved_now' => true,
        ];
    }

    private function restoreStockForOrder(Order $order, bool $force = false): void
    {
        if (empty($order->stock_reserved_at)) {
            return;
        }

        if (!$force && !empty($order->stock_restored_at)) {
            return;
        }

        $quantities = $this->buildOrderItemQuantities($order);
        foreach ($quantities as $menuId => $qty) {
            MenuItem::where('_id', $menuId)->increment('stock', $qty);
        }

        $order->update([
            'stock_restored_at' => now(),
        ]);
    }

    private function buildOrderItemQuantities(Order $order): array
    {
        $items = is_array($order->items ?? null) ? $order->items : [];
        $quantities = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $menuId = trim((string) ($item['menu_id'] ?? ''));
            if ($menuId === '') {
                continue;
            }

            if (!isset($quantities[$menuId])) {
                $quantities[$menuId] = 0;
            }
            $quantities[$menuId]++;
        }

        return $quantities;
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
