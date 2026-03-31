<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    private $allowedStatuses = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS', 'DELIVERED'];

    /**
     * Create an order (Direct creation from items).
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*' => 'required|string',
            'tableNumber' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $userId = $request->user()->_id;
        $itemIds = $request->input('items');
        $tableNumber = $request->input('tableNumber');

        // Count quantities for requested items
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
            $foundIds = $menuItems->pluck('_id')->map(function ($id) { return (string)$id; })->toArray();
            $notFound = array_diff($uniqueIds, $foundIds);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Some menu items not found. Please verify the IDs: ' . implode(', ', $notFound)
            ], 400);
        }

        $totalPrice = 0;
        $orderMenuItems = [];

        foreach ($menuItems as $item) {
            $qty = $quantityMap[(string)$item->_id];
            $totalPrice += $item->price * $qty;
            
            for ($i = 0; $i < $qty; $i++) {
                $orderMenuItems[] = [
                    'menu_id' => (string)$item->_id,
                    'name' => $item->name,
                    'price' => $item->price,
                ];
            }
        }

        $lastOrder = Order::orderBy('queue_number', 'desc')->first();
        $queueNumber = $lastOrder ? $lastOrder->queue_number + 1 : 1;

        $order = Order::create([
            'customer_id' => $userId,
            'table_number' => $tableNumber,
            'status' => 'CONFIRMED',
            'payment_status' => 'PENDING',
            'queue_number' => $queueNumber,
            'total_price' => $totalPrice,
            'items' => $orderMenuItems
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order created',
            'data' => $this->buildOrderResponse($order, clone $request->user())
        ]);
    }

    /**
     * List all orders (Admin).
     */
    public function list()
    {
        $orders = Order::with('customer')->orderBy('_id', 'desc')->get();
        
        $data = $orders->map(function ($order) {
            return $this->buildOrderResponse($order, $order->customer);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Orders retrieved',
            'data' => $data
        ]);
    }

    /**
     * List current user's orders (Customer).
     */
    public function myOrders(Request $request)
    {
        $user = $request->user();
        
        $orders = Order::where('customer_id', $user->_id)
            ->orderBy('_id', 'desc')
            ->get();
            
        $data = $orders->map(function ($order) use ($user) {
            return $this->buildOrderResponse($order, $user);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Orders retrieved',
            'data' => $data
        ]);
    }

    /**
     * Update order status (Admin).
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', $this->allowedStatuses),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $order = Order::find($id);
        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
        }

        $order->update(['status' => $request->input('status')]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order status updated',
            'data' => 'Order status updated'
        ]);
    }

    /**
     * Get count of all orders (Admin).
     */
    public function count()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Order count retrieved',
            'data' => ['count' => Order::count()]
        ]);
    }

    /**
     * Helper to format order response to match the Node.js implementation
     */
    private function buildOrderResponse($order, $customer = null)
    {
        $quantityMap = [];
        $itemLookup = [];
        
        if (is_array($order->items) || is_object($order->items)) {
            foreach ($order->items as $item) {
                // Determine if items are associative arrays or objects
                $menuId = is_array($item) ? $item['menu_id'] : $item->menu_id;
                
                if (!isset($quantityMap[$menuId])) {
                    $quantityMap[$menuId] = 0;
                }
                $quantityMap[$menuId]++;
                
                if (!isset($itemLookup[$menuId])) {
                    // Try to fetch full item details if we only embedded basic properties
                    // In a production app with heavy traffic, we might just use embedded data
                    // But here we do an active lookup or use embedded
                    $itemLookup[$menuId] = is_array($item) ? $item : (array)$item;
                }
            }
        }
        
        $itemsResponse = [];
        foreach ($quantityMap as $menuId => $qty) {
            $itemData = $itemLookup[$menuId];
            
            // Re-fetch category and image if not embedded.
            // For true MongoDB optimization, these should ideally be embedded upon creation.
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
                'name' => $customer->name,
                'username' => $customer->username,
            ];
        } elseif ($order->customer) {
            $customerData = [
                'id' => (string) $order->customer->_id,
                'name' => $order->customer->name,
                'username' => $order->customer->username,
            ];
        }

        return [
            'orderId' => (string) $order->_id,
            'customer' => $customerData,
            'tableNumber' => $order->table_number,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'queueNumber' => $order->queue_number,
            'totalPrice' => $order->total_price,
            'items' => $itemsResponse,
        ];
    }
}
