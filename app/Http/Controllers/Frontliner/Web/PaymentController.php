<?php

namespace App\Http\Controllers\Frontliner\Web;

use App\Domains\Payment\Services\PaymentService;
use App\Domains\Table\Services\TableService;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    private const SERVICE_FEE = 5000;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly TableService $tableService
    )
    {
    }

    public function createFromCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tableNumber' => 'required|integer|min:1|max:999',
            'customerName' => 'required|string|max:255',
            'customerEmail' => 'required|email|max:255',
            'items' => 'required|array|min:1',
            'items.*.menuId' => 'required|string',
            'items.*.qty' => 'required|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (! $this->tableService->isKnownTable((int) $validated['tableNumber'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor meja tidak terdaftar.',
            ], 422);
        }

        $tableNumber = (int) $validated['tableNumber'];
        $browserSessionId = $request->hasSession() ? (string) $request->session()->getId() : null;
        $sessionTableId = $request->hasSession() ? (int) $request->session()->get('table_id', 0) : null;
        $receiptTableId = $request->hasSession() ? (int) $request->session()->get('frontliner_receipt_table_id', 0) : null;

        if (! $this->tableService->canPlaceOrderForSession(
            $tableNumber,
            (string) $validated['customerName'],
            $browserSessionId,
            $sessionTableId,
            $receiptTableId
        )) {
            return response()->json([
                'status' => 'error',
                'message' => 'Meja masih terisi oleh session atau pemesan lain. Gunakan device/browser yang sama atau nama pemesan yang sama untuk menambah order di meja ini.',
            ], 409);
        }

        $rawItems = collect($validated['items']);

        $menuIds = $rawItems->pluck('menuId')->map(fn ($id) => (string) $id)->unique()->values()->all();
        $menuItems = MenuItem::whereIn('_id', $menuIds)->get()->keyBy(fn ($item) => (string) $item->_id);

        if ($menuItems->count() !== count($menuIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sebagian menu tidak ditemukan. Silakan refresh halaman menu.',
            ], 422);
        }

        $quantityMap = $rawItems
            ->groupBy(fn ($item) => (string) $item['menuId'])
            ->map(fn ($group) => $group->sum(fn ($item) => (int) $item['qty']));

        foreach ($quantityMap as $menuId => $requestedQty) {
            $menu = $menuItems->get((string) $menuId);
            $stock = (int) ($menu->stock ?? 0);

            if ($stock <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => sprintf('Menu "%s" sedang habis dan tidak bisa dipesan.', (string) ($menu->name ?? 'Unknown')),
                ], 422);
            }

            if ((int) $requestedQty > $stock) {
                return response()->json([
                    'status' => 'error',
                    'message' => sprintf('Stok menu "%s" tidak mencukupi. Sisa stok: %d.', (string) ($menu->name ?? 'Unknown'), $stock),
                ], 422);
            }
        }

        $embeddedItems = [];
        $subtotal = 0;

        foreach ($rawItems as $rawItem) {
            $menuId = (string) $rawItem['menuId'];
            $qty = (int) $rawItem['qty'];
            $menu = $menuItems->get($menuId);
            $unitPrice = (float) ($menu->price ?? 0);

            $subtotal += $unitPrice * $qty;

            for ($i = 0; $i < $qty; $i++) {
                $embeddedItems[] = [
                    'menu_id' => $menuId,
                    'name' => (string) $menu->name,
                    'price' => $unitPrice,
                ];
            }
        }

        if ($subtotal <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Total pembayaran tidak valid.',
            ], 422);
        }

        $serviceFee = self::SERVICE_FEE;
        $totalPrice = $subtotal + $serviceFee;

        $lastOrder = Order::orderBy('queue_number', 'desc')->first();
        $queueNumber = $lastOrder ? ((int) $lastOrder->queue_number + 1) : 1;

        $order = Order::create([
            'customer_id' => null,
            'customer_name' => (string) $validated['customerName'],
            'customer_email' => (string) $validated['customerEmail'],
            'browser_session_id' => $browserSessionId,
            'table_number' => $tableNumber,
            'status' => 'PENDING_PAYMENT',
            'payment_status' => 'PENDING',
            'table_cleared_at' => null,
            'queue_number' => $queueNumber,
            'total_price' => $totalPrice,
            'items' => $embeddedItems,
        ]);

        // Customer started a new order cycle on this table; refresh session anchor.
        $request->session()->put('table_id', $tableNumber);
        $request->session()->put('table_session_started_at', now()->toDateTimeString());

        $receiptOrderIds = collect($request->session()->get('frontliner_receipt_order_ids', []))
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $id !== '')
            ->values();

        $receiptOrderIds = $receiptOrderIds
            ->reject(fn ($id) => $id === (string) $order->_id)
            ->prepend((string) $order->_id)
            ->take(15)
            ->values();

        $request->session()->put('frontliner_receipt_order_ids', $receiptOrderIds->all());
        $request->session()->put('frontliner_receipt_order_id', (string) $order->_id);
        $request->session()->put('frontliner_receipt_table_id', $tableNumber);
        $request->session()->put('frontliner_receipt_bound_at', now()->toDateTimeString());

        $finishRedirectUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/kedai/pembayaran/selesai';

        $result = $this->paymentService->createTransaction((string) $order->_id, [
            'name' => (string) $validated['customerName'],
            'email' => (string) $validated['customerEmail'],
            'phone' => null,
        ], $finishRedirectUrl);

        if (!$result['ok']) {
            $order->delete();

            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ], (int) ($result['status'] ?? 500));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payment transaction created',
            'data' => [
                ...($result['data'] ?? []),
                'table_number' => $tableNumber,
                'subtotal' => $subtotal,
                'service_fee' => $serviceFee,
                'total_payment' => $totalPrice,
            ],
        ]);
    }

    public function finishRedirect(Request $request)
    {
        $paymentState = 'processing';
        $midtransOrderId = (string) $request->query('order_id', '');

        if ($midtransOrderId !== '') {
            $sync = $this->paymentService->syncTransactionStatus($midtransOrderId);

            if (($sync['ok'] ?? false) && isset($sync['data']['payment_status'])) {
                $status = strtoupper((string) $sync['data']['payment_status']);
                $paymentState = match ($status) {
                    'PAID' => 'success',
                    'FAILED', 'CANCELED', 'EXPIRED' => 'failed',
                    default => 'processing',
                };
            }
        }

        if ($paymentState === 'success') {
            return redirect('/kedai/pembayaran/struk');
        }

        return redirect('/menu?payment=' . $paymentState);
    }

    public function resumePendingPayment(Request $request, string $id)
    {
        $order = Order::find($id);

        if (! $order || ! $this->canAccessReceiptOrder($request, $order)) {
            return redirect('/kedai/pembayaran/struk')->with('error', 'Order pembayaran tidak ditemukan untuk sesi ini.');
        }

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if ($paymentStatus !== 'PENDING') {
            return redirect('/kedai/pembayaran/struk');
        }

        $existingMidtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));
        if ($existingMidtransOrderId !== '') {
            $this->paymentService->cancelTransaction($existingMidtransOrderId, false);
        }

        $finishRedirectUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/kedai/pembayaran/selesai';
        $result = $this->paymentService->createTransaction((string) $order->_id, [
            'name' => (string) ($order->customer_name ?? 'Customer'),
            'email' => (string) ($order->customer_email ?? 'customer@example.com'),
            'phone' => null,
        ], $finishRedirectUrl, true);

        if (!($result['ok'] ?? false) || empty($result['data']['redirect_url'])) {
            return redirect('/kedai/pembayaran/struk')->with('error', 'Link pembayaran belum bisa dibuka. Coba lagi sebentar.');
        }

        return redirect()->away((string) $result['data']['redirect_url']);
    }

    public function cancelPendingPayment(Request $request, string $id)
    {
        $order = Order::find($id);

        if (! $order || ! $this->canAccessReceiptOrder($request, $order)) {
            return redirect('/kedai/pembayaran/struk')->with('error', 'Order pembayaran tidak ditemukan untuk sesi ini.');
        }

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if ($paymentStatus !== 'PENDING') {
            return redirect('/kedai/pembayaran/struk')->with('error', 'Pembayaran ini sudah tidak bisa dibatalkan.');
        }

        $midtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));
        if ($midtransOrderId === '') {
            return redirect('/kedai/pembayaran/struk')->with('error', 'ID transaksi Midtrans tidak ditemukan.');
        }

        $result = $this->paymentService->cancelTransaction($midtransOrderId);
        if (!($result['ok'] ?? false)) {
            return redirect('/kedai/pembayaran/struk')->with('error', $result['message'] ?? 'Gagal membatalkan pembayaran.');
        }

        return redirect('/kedai/pembayaran/struk')->with('success', 'Pembayaran berhasil dibatalkan.');
    }

    public function receipt(Request $request)
    {
        $sessionCleared = $this->tableService->clearTableSessionIfInactive($request);
        if ($sessionCleared) {
            return $this->emptyReceiptView('Sesi struk sudah berakhir. Silakan scan ulang QR meja jika ingin memesan lagi.');
        }

        $sessionOrderId = (string) $request->session()->get('frontliner_receipt_order_id', '');
        $sessionOrderIds = collect($request->session()->get('frontliner_receipt_order_ids', []))
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $id !== '')
            ->values();

        if ($sessionOrderIds->isEmpty() && $sessionOrderId !== '') {
            $sessionOrderIds = collect([$sessionOrderId]);
        }

        $sessionTableId = (int) $request->session()->get('table_id', 0);
        $sessionReceiptTableId = (int) $request->session()->get('frontliner_receipt_table_id', 0);

        if ($sessionOrderIds->isEmpty() || $sessionTableId <= 0 || $sessionReceiptTableId <= 0) {
            return $this->emptyReceiptView();
        }

        $ordersById = Order::whereIn('_id', $sessionOrderIds->all())
            ->get()
            ->keyBy(fn ($order) => (string) $order->_id);

        $validOrderIds = $sessionOrderIds->filter(function ($id) use ($ordersById, $sessionTableId, $sessionReceiptTableId) {
            $order = $ordersById->get($id);
            if (!$order) {
                return false;
            }

            $tableNumber = (int) ($order->table_number ?? 0);

            return $tableNumber === $sessionTableId && $tableNumber === $sessionReceiptTableId;
        })->values();

        if ($validOrderIds->isEmpty()) {
            return $this->emptyReceiptView();
        }

        // Keep session in sync with only valid orders for current table context.
        $request->session()->put('frontliner_receipt_order_ids', $validOrderIds->all());

        $index = (int) $request->query('invoice_index', 0);
        if ($index < 0) {
            $index = 0;
        }
        if ($index > $validOrderIds->count() - 1) {
            $index = $validOrderIds->count() - 1;
        }

        $selectedOrderId = (string) $validOrderIds->get($index);
        $order = $ordersById->get($selectedOrderId);

        if (!$order) {
            return $this->emptyReceiptView();
        }

        $request->session()->put('frontliner_receipt_order_id', $selectedOrderId);

        $orderStatus = strtoupper((string) ($order->status ?? ''));
        $deliveredAt = $order->delivered_at ?? $order->updated_at;
        if ($orderStatus === 'DELIVERED' && $deliveredAt && now()->gte($deliveredAt->copy()->addMinutes(150))) {
            $this->tableService->clearTableSession($request);
            return $this->emptyReceiptView('Sesi anda sudah berakhir. Silakan scan ulang QR meja jika ingin memesan lagi.');
        }

        $items = collect(is_array($order->items) ? $order->items : [])
            ->groupBy(fn ($item) => (string) ($item['name'] ?? '-'))
            ->map(function ($group, $name) {
                $qty = $group->count();
                $unitPrice = (float) ($group->first()['price'] ?? 0);

                return [
                    'name' => $name,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * $qty,
                ];
            })
            ->values();

        $subtotal = (float) $items->sum('line_total');
        $total = (float) ($order->total_price ?? 0);
        $serviceFee = max(0, $total - $subtotal);

        return view('frontliner.pembayaran.struk', [
            'order' => $order,
            'items' => $items,
            'subtotal' => $subtotal,
            'serviceFee' => $serviceFee,
            'total' => $total,
            'emptyReceiptMessage' => null,
            'invoiceCount' => $validOrderIds->count(),
            'invoiceIndex' => $index,
        ]);
    }

    private function emptyReceiptView(?string $message = null)
    {
        return view('frontliner.pembayaran.struk', [
            'order' => null,
            'items' => collect(),
            'subtotal' => 0,
            'serviceFee' => 0,
            'total' => 0,
            'emptyReceiptMessage' => $message ?? 'Belum ada struk aktif di browser ini.',
            'invoiceCount' => 0,
            'invoiceIndex' => 0,
        ]);
    }

    private function canAccessReceiptOrder(Request $request, Order $order): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $sessionOrderIds = collect($request->session()->get('frontliner_receipt_order_ids', []))
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($storedId) => $storedId !== '')
            ->values();

        $sessionOrderId = (string) $request->session()->get('frontliner_receipt_order_id', '');
        if ($sessionOrderId !== '' && !$sessionOrderIds->contains($sessionOrderId)) {
            $sessionOrderIds = $sessionOrderIds->push($sessionOrderId);
        }

        $sessionTableId = (int) $request->session()->get('table_id', 0);
        $sessionReceiptTableId = (int) $request->session()->get('frontliner_receipt_table_id', 0);
        $orderId = (string) $order->_id;
        $orderTableNumber = (int) ($order->table_number ?? 0);

        return $sessionOrderIds->contains($orderId)
            && $sessionTableId > 0
            && $sessionReceiptTableId > 0
            && $orderTableNumber === $sessionTableId
            && $orderTableNumber === $sessionReceiptTableId;
    }
}
