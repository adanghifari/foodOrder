<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Add an item to the cart or update its quantity.
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'menuItemId' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $userId = $request->user()->_id;
        $menuItemId = $request->input('menuItemId');
        $quantity = $request->input('quantity');

        $menuItem = MenuItem::find($menuItemId);
        if (!$menuItem) {
            return response()->json(['status' => 'error', 'message' => 'Menu item not found'], 400);
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

        return response()->json([
            'status' => 'success',
            'message' => 'Item quantity updated in cart',
            'data' => ['updated' => true]
        ]);
    }

    /**
     * Get user's cart items.
     */
    public function get(Request $request)
    {
        $userId = $request->user()->_id;
        
        $cartItems = CartItem::with('menuItem')
            ->where('customer_id', $userId)
            ->get();

        $data = $cartItems->map(function ($item) {
            $menu = $item->menuItem;
            // Handle edge case if menu item is deleted
            if (!$menu) return null;

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

        return response()->json([
            'status' => 'success',
            'message' => 'Cart retrieved',
            'data' => $data
        ]);
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'menuItemId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $userId = $request->user()->_id;
        $menuItemId = $request->input('menuItemId');

        $existing = CartItem::where('customer_id', $userId)
            ->where('menu_item_id', $menuItemId)
            ->first();

        if (!$existing) {
            return response()->json(['status' => 'error', 'message' => 'Item not found in cart'], 404);
        }

        $existing->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Item removed from cart',
            'data' => ['deleted' => true]
        ]);
    }

    /**
     * Checkout the cart and create an order.
     */
    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tableNumber' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $tableNumber = $request->input('tableNumber');

        $cartItems = CartItem::with('menuItem')
            ->where('customer_id', $user->_id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Cart is empty'], 400);
        }

        $totalPrice = 0;
        $itemsResponse = [];
        $orderMenuItems = []; // Embedded documents for MongoDB

        foreach ($cartItems as $cartItem) {
            $menu = $cartItem->menuItem;
            if (!$menu) continue;

            $quantity = $cartItem->quantity;
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

        // Keep transaction simple or rely on sequence count if possible
        $queueNumber = Order::count() + 1;

        $order = Order::create([
            'customer_id' => $user->_id,
            'table_number' => $tableNumber,
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID', // Based on the reference code
            'queue_number' => $queueNumber,
            'total_price' => $totalPrice,
            'items' => $orderMenuItems // Embedded documents paradigm
        ]);

        // Clear user cart
        CartItem::where('customer_id', $user->_id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Checkout success',
            'data' => [
                'orderId' => (string) $order->_id,
                'customerName' => $user->name,
                'tableNumber' => $order->table_number,
                'items' => $itemsResponse,
                'paymentStatus' => $order->payment_status,
                'queueNumber' => $order->queue_number,
                'status' => $order->status,
                'totalPrice' => $order->total_price,
            ]
        ]);
    }
}
