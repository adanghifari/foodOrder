<?php

namespace App\Domains\Cart\Services;

use App\Domains\Booking\Services\BookingService;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Domains\Order\Services\OrderService;
use App\Domains\Table\Services\TableService;
use Illuminate\Support\Facades\Cache;

class CartService
{
    private const SERVICE_FEE = 5000;

    public function __construct(
        private readonly TableService $tableService,
        private readonly OrderService $orderService,
        private readonly BookingService $bookingService
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

    public function checkout(
        $user,
        string $orderType,
        ?int $tableNumber,
        ?string $bookingStartAt = null,
        ?int $durationHours = null,
        ?string $firstCustomerName = null
    ): array
    {
        if ($orderType === 'booking_dine_in') {
            if (!$tableNumber) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Table number is required for booking dine-in',
                ];
            }

            if (!$this->tableService->isKnownTable($tableNumber)) {
                return [
                    'ok' => false,
                    'status' => 404,
                    'message' => 'Selected table does not exist',
                ];
            }

            if (!$bookingStartAt || !$durationHours) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Booking start time and duration are required for booking dine-in',
                ];
            }

            $availability = $this->bookingService->getAvailability($bookingStartAt, (int) $durationHours);
            if (!($availability['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'status' => (int) ($availability['status'] ?? 422),
                    'message' => (string) ($availability['message'] ?? 'Gagal memeriksa ketersediaan meja.'),
                ];
            }

            $availableTables = collect($availability['data']['availableTables'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            if (! $availableTables->contains($tableNumber)) {
                return [
                    'ok' => false,
                    'status' => 409,
                    'message' => 'Meja tidak tersedia pada jam yang dipilih.',
                ];
            }
        } elseif ($orderType === 'dine_in') {
            if (!$tableNumber) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Table number is required for on the spot dine-in',
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
                $firstCustomerName = trim((string) ($firstCustomerName ?? ''));
                if ($firstCustomerName === '') {
                    return [
                        'ok' => false,
                        'status' => 409,
                        'message' => 'Meja sedang dipakai! Jika anda bagian dari pemesan pertama, silahkan masukkan nama pemesan pertama.',
                    ];
                }

                $canJoinOccupiedTable = $this->tableService->canPlaceOrderForSession(
                    $tableNumber,
                    $firstCustomerName,
                    (string) ($user->email ?? ''),
                    null,
                    null,
                    null
                );
                if (! $canJoinOccupiedTable) {
                    return [
                        'ok' => false,
                        'status' => 409,
                        'message' => 'Nama pemesan pertama salah',
                    ];
                }

                $reason = $this->tableService->getTableUnavailableReason($tableNumber);
                if ($reason === null || trim($reason) === '') {
                    $reason = 'Selected table is not available';
                }

                if (trim($reason) !== 'Meja sedang dipakai.') {
                    return [
                        'ok' => false,
                        'status' => 409,
                        'message' => $reason,
                    ];
                }
            }

            $bookingStartAt = null;
            $durationHours = null;
        } else {
            $tableNumber = null;
            $bookingStartAt = null;
            $durationHours = null;
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

        $serviceFee = self::SERVICE_FEE;
        $totalPrice += $serviceFee;

        if (($orderType === 'dine_in' || $orderType === 'booking_dine_in') && $tableNumber) {
            $lock = Cache::lock('checkout:table:' . (int) $tableNumber, 10);
            $lockAcquired = $lock->get();
            if (! $lockAcquired) {
                return [
                    'ok' => false,
                    'status' => 409,
                    'message' => 'Meja sedang diproses oleh pesanan lain. Silakan coba lagi.',
                ];
            }

            try {
                if ($orderType === 'dine_in' && ! $this->tableService->isTableAvailable($tableNumber)) {
                    $candidateName = trim((string) ($firstCustomerName ?? ''));
                    if ($candidateName === '') {
                        return [
                            'ok' => false,
                            'status' => 409,
                            'message' => 'Meja sedang dipakai! Jika anda bagian dari pemesan pertama, silahkan masukkan nama pemesan pertama.',
                        ];
                    }

                    if (! $this->tableService->canPlaceOrderForSession(
                        $tableNumber,
                        $candidateName,
                        (string) ($user->email ?? ''),
                        null,
                        null,
                        null
                    )) {
                        return [
                            'ok' => false,
                            'status' => 409,
                            'message' => 'Nama pemesan pertama salah',
                        ];
                    }
                }

                if ($orderType === 'booking_dine_in') {
                    $availability = $this->bookingService->getAvailability((string) $bookingStartAt, (int) $durationHours);
                    if (! ($availability['ok'] ?? false)) {
                        return [
                            'ok' => false,
                            'status' => (int) ($availability['status'] ?? 422),
                            'message' => (string) ($availability['message'] ?? 'Gagal memeriksa ketersediaan meja.'),
                        ];
                    }

                    $availableTables = collect($availability['data']['availableTables'] ?? [])
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn ($id) => $id > 0)
                        ->values();
                    if (! $availableTables->contains($tableNumber)) {
                        return [
                            'ok' => false,
                            'status' => 409,
                            'message' => 'Meja tidak tersedia pada jam yang dipilih. Silakan muat ulang ketersediaan meja.',
                        ];
                    }
                }

                $order = $this->orderService->createConfirmedOrder(
                    (string) $user->_id,
                    $tableNumber,
                    $orderMenuItems,
                    $totalPrice,
                    $orderType,
                    $bookingStartAt,
                    $durationHours
                );
            } finally {
                optional($lock)->release();
            }
        } else {
            $order = $this->orderService->createConfirmedOrder(
                (string) $user->_id,
                $tableNumber,
                $orderMenuItems,
                $totalPrice,
                $orderType,
                $bookingStartAt,
                $durationHours
            );
        }

        CartItem::where('customer_id', $user->_id)->delete();

        return [
            'ok' => true,
            'data' => [
                'orderId' => (string) $order->_id,
                'customerName' => $user->name,
                'orderType' => $orderType,
                'tableNumber' => $order->table_number,
                'bookingStartAt' => $order->booking_start_at,
                'durationHours' => $order->duration_hours,
                'items' => $itemsResponse,
                'serviceFee' => $serviceFee,
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
