<?php

namespace App\Domains\Cart\Services;

use App\Models\CartItem;
use App\Models\MenuItem;
use App\Domains\Order\Services\OrderService;
use App\Domains\Table\Services\TableService;

class CartService
{
    public function __construct(
        private readonly TableService $tableService,
        private readonly OrderService $orderService
    ) {
    }

    public function addOrUpdateItem(string $userId, string $menuItemId, int $quantity): array
    {
        if (!$this->isValidMongoId($menuItemId)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Invalid menu item id format',
            ];
        }

        $menuItem = MenuItem::find($menuItemId);
        if (!$menuItem) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Menu item not found',
            ];
        }

        $stock = (int) ($menuItem->stock ?? 0);
        if ($stock <= 0) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Menu item is out of stock',
            ];
        }

        if ($quantity > $stock) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Requested quantity exceeds available stock',
            ];
        }

        $existing = CartItem::where('customer_id', $userId)
            ->where('menu_item_id', $menuItemId)
            ->first();

        if ($existing) {
            $existing->update(['quantity' => $quantity]);
        } else {
            CartItem::create([
                'customer_id' => $userId,
                'menu_item_id' => $menuItemId,
                'quantity' => $quantity,
            ]);
        }

        return ['ok' => true];
    }

    public function getCartData(string $userId)
    {
        $cartItems = CartItem::with('menuItem')
            ->where('customer_id', $userId)
            ->get();

        return $cartItems->map(function ($item) {
            $menu = $item->menuItem;
            if (!$menu) {
                return null;
            }

            return [
                'menuId' => (string) $menu->_id,
                'name' => $menu->name,
                'description' => $menu->description,
                'price' => $menu->price,
                'category' => $menu->category,
                'quantity' => $item->quantity,
                'subtotal' => $menu->price * $item->quantity,
                'imageUrl' => $menu->image_url,
            ];
        })->filter()->values();
    }

    public function removeItem(string $userId, string $menuItemId): array
    {
        if (!$this->isValidMongoId($menuItemId)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Invalid menu item id format',
            ];
        }

        $existing = CartItem::where('customer_id', $userId)
            ->where('menu_item_id', $menuItemId)
            ->first();

        if (!$existing) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Item not found in cart',
            ];
        }

        $existing->delete();
        return ['ok' => true];
    }

    public function checkout($user, ?int $tableNumber): array
    {
        if (!$tableNumber) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Table number is required',
            ];
        }

        if (!$this->tableService->isKnownTable($tableNumber)) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Selected table does not exist',
            ];
        }

        if (!$this->tableService->isTableAvailable($tableNumber)) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Selected table is not available',
            ];
        }

        $cartItems = CartItem::with('menuItem')
            ->where('customer_id', $user->_id)
            ->get();

        if ($cartItems->isEmpty()) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Cart is empty',
            ];
        }

        $totalPrice = 0;
        $itemsResponse = [];
        $orderMenuItems = [];

        foreach ($cartItems as $cartItem) {
            $menu = $cartItem->menuItem;
            if (!$menu) {
                continue;
            }

            $quantity = $cartItem->quantity;
            $stock = (int) ($menu->stock ?? 0);

            if ($stock <= 0) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Menu "' . (string) $menu->name . '" is out of stock',
                ];
            }

            if ($quantity > $stock) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Requested quantity for "' . (string) $menu->name . '" exceeds available stock',
                ];
            }

            $subtotal = $menu->price * $quantity;

            $itemsResponse[] = [
                'menuId' => (string) $menu->_id,
                'name' => $menu->name,
                'price' => $menu->price,
                'category' => $menu->category,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'imageUrl' => $menu->image_url,
            ];

            $totalPrice += $subtotal;

            for ($i = 0; $i < $quantity; $i++) {
                $orderMenuItems[] = [
                    'menu_id' => (string) $menu->_id,
                    'name' => $menu->name,
                    'price' => $menu->price,
                ];
            }
        }

        $order = $this->orderService->createConfirmedOrder(
            (string) $user->_id,
            $tableNumber,
            $orderMenuItems,
            $totalPrice
        );

        CartItem::where('customer_id', $user->_id)->delete();

        return [
            'ok' => true,
            'data' => [
                'orderId' => (string) $order->_id,
                'customerName' => $user->name,
                'tableNumber' => $order->table_number,
                'items' => $itemsResponse,
                'paymentStatus' => $order->payment_status,
                'queueNumber' => $order->queue_number,
                'status' => $order->status,
                'totalPrice' => $order->total_price,
            ],
        ];
    }

    private function isValidMongoId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{24}$/i', $id) === 1;
    }

}
