<?php

namespace App\Domains\Order\Services;

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

    public function createConfirmedOrder(string $userId, int $tableNumber, array $orderMenuItems, float|int $totalPrice): Order
    {
        $lastOrder = Order::orderBy('queue_number', 'desc')->first();
        $queueNumber = $lastOrder ? $lastOrder->queue_number + 1 : 1;

        return Order::create([
            'customer_id' => $userId,
            'table_number' => $tableNumber,
            'status' => self::INITIAL_UNPAID_ORDER_STATUS,
            'payment_status' => 'PENDING',
            'table_cleared_at' => null,
            'queue_number' => $queueNumber,
            'total_price' => $totalPrice,
            'items' => $orderMenuItems,
        ]);
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

        $payload = ['status' => $status];

        if ($status === 'DELIVERED') {
            $payload['delivered_at'] = now();
            $payload['table_cleared_at'] = null;
        }

        $order->update($payload);
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

        return [
            'orderId' => (string) $order->_id,
            'customer' => $customerData,
            'tableNumber' => $order->table_number,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'paidAt' => optional($order->paid_at)?->toDateTimeString(),
            'orderDeletedAt' => optional($order->order_deleted_at)?->toDateTimeString(),
            'queueNumber' => $order->queue_number,
            'totalPrice' => $order->total_price,
            'items' => $itemsResponse,
        ];
    }
}
