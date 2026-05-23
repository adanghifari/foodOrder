<?php

namespace App\Domains\Order\Services;

use App\Domains\Notification\Services\PushNotificationService;
use App\Domains\Table\Services\TableService;
use App\Models\MenuItem;
use App\Models\Order;

class OrderService
{
    private const INITIAL_UNPAID_ORDER_STATUS = 'PENDING_PAYMENT';

    public function createFromItems(string $userId, array $itemIds, int $tableNumber): array
    {
        $quantityMap = [];
        foreach ($itemIds as $id) {
            if (!isset($quantityMap[$id])) {
                $quantityMap[$id] = 0;
            }
            $quantityMap[$id]++;
        }

        $uniqueIds = array_keys($quantityMap);
        $menuItems = MenuItem::whereIn('_id', $uniqueIds)->get();

        if ($menuItems->count() !== count($uniqueIds)) {
            $foundIds = $menuItems->pluck('_id')->map(function ($id) {
                return (string) $id;
            })->toArray();
            $notFound = array_diff($uniqueIds, $foundIds);

            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Some menu items not found. Please verify the IDs: ' . implode(', ', $notFound),
            ];
        }

        $totalPrice = 0;
        $orderMenuItems = [];

        foreach ($menuItems as $item) {
            $qty = $quantityMap[(string) $item->_id];

            if ((int) ($item->stock ?? 0) <= 0) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Menu "' . (string) $item->name . '" sedang habis dan tidak bisa dipesan.',
                ];
            }

            if ($qty > (int) ($item->stock ?? 0)) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Stok menu "' . (string) $item->name . '" tidak mencukupi. Sisa stok: ' . (int) ($item->stock ?? 0) . '.',
                ];
            }

            $totalPrice += $item->price * $qty;

            for ($i = 0; $i < $qty; $i++) {
                $orderMenuItems[] = [
                    'menu_id' => (string) $item->_id,
                    'name' => $item->name,
                    'price' => $item->price,
                ];
            }
        }

        $order = $this->createConfirmedOrder($userId, $tableNumber, $orderMenuItems, $totalPrice);

        return [
            'ok' => true,
            'order' => $order,
        ];
    }

    public function createConfirmedOrder(
        string $userId,
        ?int $tableNumber,
        array $orderMenuItems,
        float|int $totalPrice,
        string $orderType = 'dine_in',
        ?string $bookingStartAt = null,
        ?int $durationHours = null
    ): Order
    {
        $lastOrder = Order::orderBy('queue_number', 'desc')->first();
        $queueNumber = $lastOrder ? $lastOrder->queue_number + 1 : 1;

        $order = Order::create([
            'customer_id' => $userId,
            'order_type' => $orderType,
            'table_number' => $tableNumber,
            'booking_start_at' => $bookingStartAt,
            'duration_hours' => $durationHours,
            'status' => self::INITIAL_UNPAID_ORDER_STATUS,
            'payment_status' => 'PENDING',
            'table_cleared_at' => null,
            'queue_number' => $queueNumber,
            'total_price' => $totalPrice,
            'items' => $orderMenuItems,
        ]);

        app(TableService::class)->syncTableOccupanciesFromOrders();

        return $order;
    }

    public function myOrders(string $userId, $user)
    {
        $orders = Order::where('customer_id', $userId)
            ->orderBy('_id', 'desc')
            ->get();

        return $orders->map(function ($order) use ($user) {
            return $this->buildOrderResponse($order, $user);
        });
    }

    public function adminList()
    {
        $orders = Order::with('customer')->orderBy('_id', 'desc')->get();

        return $orders->map(function ($order) {
            return $this->buildOrderResponse($order, $order->customer);
        });
    }

    public function updateStatus(string $id, string $status): bool
    {
        $order = Order::find($id);
        if (!$order) {
            return false;
        }

        $previousStatus = strtoupper((string) ($order->status ?? ''));

        $payload = ['status' => $status];

        if ($status === 'DELIVERED') {
            $payload['delivered_at'] = now();
            $payload['table_cleared_at'] = null;
        }

        $order->update($payload);
        $order->refresh();
        $nextStatus = strtoupper((string) ($order->status ?? ''));
        app(PushNotificationService::class)->sendOrderStatusChanged($order, $previousStatus, $nextStatus);
        app(TableService::class)->syncTableOccupanciesFromOrders();
        return true;
    }

    public function count(): int
    {
        return Order::count();
    }

    public function buildOrderResponse($order, $customer = null): array
    {
        $fallbackName = (string) ($order->customer_name ?? '');
        $fallbackEmail = (string) ($order->customer_email ?? '');

        $quantityMap = [];
        $itemLookup = [];

        if (is_array($order->items) || is_object($order->items)) {
            foreach ($order->items as $item) {
                $menuId = is_array($item) ? $item['menu_id'] : $item->menu_id;

                if (!isset($quantityMap[$menuId])) {
                    $quantityMap[$menuId] = 0;
                }
                $quantityMap[$menuId]++;

                if (!isset($itemLookup[$menuId])) {
                    $itemLookup[$menuId] = is_array($item) ? $item : (array) $item;
                }
            }
        }

        $itemsResponse = [];
        foreach ($quantityMap as $menuId => $qty) {
            $itemData = $itemLookup[$menuId];
            $menuModel = MenuItem::find($menuId);

            $itemsResponse[] = [
                'menuId' => $menuId,
                'name' => $itemData['name'],
                'description' => $menuModel ? $menuModel->description : null,
                'category' => $menuModel ? $menuModel->category : null,
                'quantity' => $qty,
                'price' => $itemData['price'] * $qty,
                'unitPrice' => $itemData['price'],
                'imageUrl' => $menuModel ? $menuModel->image_url : null,
            ];
        }

        $customerData = null;
        if ($customer) {
            $customerData = [
                'id' => (string) $customer->_id,
                'name' => $customer->name ?: $fallbackName,
                'username' => $customer->username,
                'email' => $customer->email ?? ($customer->username ?? $fallbackEmail),
            ];
        } elseif ($order->customer) {
            $customerData = [
                'id' => (string) $order->customer->_id,
                'name' => $order->customer->name ?: $fallbackName,
                'username' => $order->customer->username,
                'email' => $order->customer->email ?? ($order->customer->username ?? $fallbackEmail),
            ];
        } elseif ($fallbackName !== '' || $fallbackEmail !== '') {
            $customerData = [
                'id' => null,
                'name' => $fallbackName,
                'username' => $fallbackEmail,
                'email' => $fallbackEmail,
            ];
        }

        $paymentPayload = is_array($order->payment_payload ?? null) ? $order->payment_payload : [];
        $paymentTypeRaw = trim((string) ($order->payment_type ?? ''));
        $paymentMethod = $this->mapPaymentMethodLabel($paymentTypeRaw, $paymentPayload);
        $vaNumber = $this->extractVaNumber($paymentPayload);
        $paymentExpiry = trim((string) ($paymentPayload['expiry_time'] ?? ''));
        $qrisImageUrl = $this->extractQrisImageUrl($paymentPayload);

        $rawOrderType = trim((string) ($order->order_type ?? ''));
        $normalizedOrderType = $rawOrderType !== ''
            ? strtolower($rawOrderType)
            : (((int) ($order->table_number ?? 0)) > 0 ? 'dine_in' : 'pickup');

        return [
            'orderId' => (string) $order->_id,
            'customer' => $customerData,
            'orderType' => $normalizedOrderType,
            'tableNumber' => $order->table_number,
            'bookingStartAt' => $order->booking_start_at,
            'durationHours' => $order->duration_hours,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'paymentType' => $order->payment_type,
            'paymentMethod' => $paymentMethod,
            'vaNumber' => $vaNumber,
            'paymentExpiry' => $paymentExpiry,
            'qrisImageUrl' => $qrisImageUrl,
            'paymentUrl' => $order->payment_url,
            'midtransOrderId' => $order->midtrans_order_id,
            'paidAt' => optional($order->paid_at)?->toDateTimeString(),
            'createdAt' => optional($order->created_at)?->toDateTimeString(),
            'orderDeletedAt' => optional($order->order_deleted_at)?->toDateTimeString(),
            'queueNumber' => $order->queue_number,
            'totalPrice' => $order->total_price,
            'items' => $itemsResponse,
        ];
    }

    private function mapPaymentMethodLabel(string $paymentTypeRaw, array $paymentPayload): string
    {
        $type = strtolower($paymentTypeRaw);
        $channel = $this->resolvePaymentChannel($paymentPayload);

        return match ($type) {
            'bank_transfer' => $channel !== '' ? "Bank Transfer ($channel)" : 'Bank Transfer',
            'echannel' => $channel !== '' ? "Mandiri Bill ($channel)" : 'Mandiri Bill',
            'qris' => $channel !== '' ? "QRIS ($channel)" : 'QRIS',
            'gopay' => $channel !== '' ? "GoPay ($channel)" : 'GoPay',
            'cstore' => $channel !== '' ? "Convenience Store ($channel)" : 'Convenience Store',
            default => $paymentTypeRaw !== '' ? ucwords(str_replace('_', ' ', $paymentTypeRaw)) : '-',
        };
    }

    private function resolvePaymentChannel(array $paymentPayload): string
    {
        $bankFromVa = '';
        $vaNumbers = $paymentPayload['va_numbers'] ?? null;
        if (is_array($vaNumbers) && !empty($vaNumbers)) {
            $first = $vaNumbers[0] ?? null;
            if (is_array($first)) {
                $bankFromVa = strtoupper(trim((string) ($first['bank'] ?? '')));
            }
        }

        if ($bankFromVa !== '') {
            return $bankFromVa;
        }

        $bank = strtoupper(trim((string) ($paymentPayload['bank'] ?? '')));
        if ($bank !== '') {
            return $bank;
        }

        $store = strtoupper(trim((string) ($paymentPayload['store'] ?? '')));
        if ($store !== '') {
            return $store;
        }

        $acquirer = strtoupper(trim((string) ($paymentPayload['acquirer'] ?? '')));
        if ($acquirer !== '') {
            return $acquirer;
        }

        return '';
    }

    private function extractVaNumber(array $paymentPayload): string
    {
        $vaNumbers = $paymentPayload['va_numbers'] ?? null;
        if (is_array($vaNumbers) && !empty($vaNumbers)) {
            $first = $vaNumbers[0] ?? null;
            if (is_array($first)) {
                $value = trim((string) ($first['va_number'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $permataVa = trim((string) ($paymentPayload['permata_va_number'] ?? ''));
        if ($permataVa !== '') {
            return $permataVa;
        }

        $billKey = trim((string) ($paymentPayload['bill_key'] ?? ''));
        $billerCode = trim((string) ($paymentPayload['biller_code'] ?? ''));
        if ($billKey !== '' && $billerCode !== '') {
            return $billerCode . ' / ' . $billKey;
        }

        $storePaymentCode = trim((string) ($paymentPayload['payment_code'] ?? ''));
        if ($storePaymentCode !== '') {
            return $storePaymentCode;
        }

        return '-';
    }

    private function extractQrisImageUrl(array $paymentPayload): string
    {
        $actions = $paymentPayload['actions'] ?? null;
        if (!is_array($actions) || empty($actions)) {
            return '';
        }

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $name = strtolower(trim((string) ($action['name'] ?? '')));
            $url = trim((string) ($action['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            if ($name === 'generate-qr-code' || str_contains(strtolower($url), 'qr')) {
                return $url;
            }
        }

        return '';
    }
}
