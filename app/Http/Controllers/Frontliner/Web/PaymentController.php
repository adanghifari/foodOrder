<?php

namespace App\Http\Controllers\Frontliner\Web;

use App\Domains\Payment\Services\PaymentService;
use App\Domains\Table\Services\TableService;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'orderType' => 'nullable|string|in:dine_in,take_away',
            'tableNumber' => 'nullable|integer|min:1|max:999',
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
        $orderType = strtolower((string) ($validated['orderType'] ?? 'dine_in'));

        if ($orderType === 'dine_in' && empty($validated['tableNumber'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor meja wajib diisi untuk dine in.',
            ], 422);
        }

        if ($orderType === 'dine_in' && ! $this->tableService->isKnownTable((int) $validated['tableNumber'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor meja tidak terdaftar.',
            ], 422);
        }

        $tableNumber = $orderType === 'dine_in' ? (int) $validated['tableNumber'] : 0;
        $browserSessionId = $request->hasSession() ? (string) $request->session()->getId() : null;
        $sessionTableId = $request->hasSession() ? (int) $request->session()->get('table_id', 0) : null;
        $receiptTableId = $request->hasSession() ? (int) $request->session()->get('frontliner_receipt_table_id', 0) : null;

        if ($orderType === 'dine_in') {
            if (! $this->tableService->canPlaceOrderForSession(
                $tableNumber,
                (string) $validated['customerName'],
                (string) $validated['customerEmail'],
                $browserSessionId,
                $sessionTableId,
                $receiptTableId
            )) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Meja masih terisi oleh session atau pemesan lain. Gunakan device/browser yang sama atau nama dan email pemesan yang sama untuk menambah order di meja ini.',
                ], 409);
            }
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
            'service_fee' => $serviceFee,
            'extra_charge' => 0,
            'total_price' => $totalPrice,
            'items' => $embeddedItems,
        ]);

        // Customer started a new order cycle; refresh session anchor.
        if ($orderType === 'dine_in') {
            $request->session()->put('table_id', $tableNumber);
            $request->session()->put('order_type', 'DINE_IN');
        } else {
            $request->session()->forget('table_id');
            $request->session()->put('order_type', 'TAKE_AWAY');
        }
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
                'order_type' => $orderType,
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

        // Smart reuse: jika snap token Midtrans masih valid (< 24 jam), redirect langsung
        // tanpa perlu cancel + buat transaksi baru. Ini menjaga payment method yang sudah
        // dipilih user tetap ada di halaman Midtrans.
        $existingPaymentUrl      = trim((string) ($order->payment_url ?? ''));
        $existingMidtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));

        if ($existingPaymentUrl !== '' && $existingMidtransOrderId !== '') {
            $tokenTimestamp = $this->extractTimestampFromMidtransOrderId($existingMidtransOrderId);
            $isTokenValid   = $tokenTimestamp > 0 && (now()->timestamp - $tokenTimestamp) < 86400;

            if ($isTokenValid) {
                // Token masih valid — redirect langsung, tidak ada HTTP call ke Midtrans.
                return redirect()->away($existingPaymentUrl);
            }
        }

        // Token sudah expired atau belum ada transaksi — buat transaksi baru.
        if ($existingMidtransOrderId !== '') {
            $this->paymentService->cancelTransaction($existingMidtransOrderId, false);
        }

        $finishRedirectUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/kedai/pembayaran/selesai';
        $result = $this->paymentService->createTransaction((string) $order->_id, [
            'name'  => (string) ($order->customer_name ?? 'Customer'),
            'email' => (string) ($order->customer_email ?? 'customer@example.com'),
            'phone' => null,
        ], $finishRedirectUrl, true);

        if (!($result['ok'] ?? false) || empty($result['data']['redirect_url'])) {
            return redirect('/kedai/pembayaran/struk')->with('error', 'Link pembayaran belum bisa dibuka. Coba lagi sebentar.');
        }

        return redirect()->away((string) $result['data']['redirect_url']);
    }

    /**
     * Paksa ganti metode pembayaran — selalu cancel transaksi lama dan buat baru.
     * Dipanggil hanya saat user eksplisit ingin mengganti metode yang sudah pernah dipilih.
     */
    public function forceChangePaymentMethod(Request $request, string $id)
    {
        $order = Order::find($id);

        if (! $order || ! $this->canAccessReceiptOrder($request, $order)) {
            return redirect('/kedai/pembayaran/struk')->with('error', 'Order pembayaran tidak ditemukan untuk sesi ini.');
        }

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if ($paymentStatus !== 'PENDING') {
            return redirect('/kedai/pembayaran/struk')->with('error', 'Pembayaran ini sudah tidak bisa diubah.');
        }

        $existingMidtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));
        if ($existingMidtransOrderId !== '') {
            $this->paymentService->cancelTransaction($existingMidtransOrderId, false);
        }

        $finishRedirectUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/kedai/pembayaran/selesai';
        $result = $this->paymentService->createTransaction((string) $order->_id, [
            'name'  => (string) ($order->customer_name ?? 'Customer'),
            'email' => (string) ($order->customer_email ?? 'customer@example.com'),
            'phone' => null,
        ], $finishRedirectUrl, true);

        if (!($result['ok'] ?? false) || empty($result['data']['redirect_url'])) {
            return redirect('/kedai/pembayaran/struk')->with('error', 'Gagal membuat transaksi baru. Coba lagi sebentar.');
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
            $result = $this->paymentService->cancelPendingOrderLocally((string) $order->_id);
            if (!($result['ok'] ?? false)) {
                return redirect('/kedai/pembayaran/struk')->with('error', $result['message'] ?? 'Gagal membatalkan pembayaran.');
            }

            return redirect('/kedai/pembayaran/struk')->with('success', 'Pembayaran berhasil dibatalkan.');
        }

        $result = $this->paymentService->cancelTransaction($midtransOrderId);
        if (!($result['ok'] ?? false)) {
            return redirect('/kedai/pembayaran/struk')->with('error', $result['message'] ?? 'Gagal membatalkan pembayaran.');
        }

        return redirect('/kedai/pembayaran/struk')->with('success', 'Pembayaran berhasil dibatalkan.');
    }

    public function receipt(Request $request)
    {
        $receiptData = $this->resolveReceiptData($request);
        if (!$receiptData['ok']) {
            return $this->emptyReceiptView($receiptData['message']);
        }

        // Auto-sync: jika PENDING + punya midtrans_order_id + payment_type belum tercatat,
        // berarti user kemungkinan sudah memilih metode di Midtrans lalu kembali via back browser
        // (bukan via tombol "X" Midtrans yang sudah redirect ke finishRedirectUrl).
        // Sync diam-diam agar label tombol dan informasi metode pembayaran selalu akurat.
        $order           = $receiptData['order'];
        $paymentStatus   = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        $midtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));
        $paymentType     = trim((string) ($order->payment_type ?? ''));

        if ($paymentStatus === 'PENDING' && $midtransOrderId !== '' && $paymentType === '') {
            try {
                $this->paymentService->syncTransactionStatus($midtransOrderId);
                $order->refresh(); // reload data terbaru dari DB setelah sync
            } catch (\Throwable) {
                // Best-effort — gagal sync tidak mengganggu tampilan struk
            }
        }

        return view('frontliner.pembayaran.struk', [
            'order'              => $order,
            'items'              => $receiptData['items'],
            'subtotal'           => $receiptData['subtotal'],
            'serviceFee'         => $receiptData['serviceFee'],
            'extraCharge'        => $receiptData['extraCharge'] ?? 0,
            'total'              => $receiptData['total'],
            'emptyReceiptMessage' => null,
            'invoiceCount'       => $receiptData['invoiceCount'],
            'invoiceIndex'       => $receiptData['invoiceIndex'],
            'allowDownloadPdf'   => true,
        ]);
    }

    public function receiptFromEmailLink(Request $request, string $id)
    {
        $order = Order::find($id);
        if (!$order) {
            abort(404);
        }

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if (!in_array($paymentStatus, ['PAID', 'SUCCESS', 'SETTLEMENT'], true)) {
            return $this->emptyReceiptView('Link struk tidak tersedia untuk pembayaran yang belum lunas.');
        }

        $payload = $this->buildReceiptPayloadFromOrder($order);

        return view('frontliner.pembayaran.struk', [
            'order' => $order,
            'items' => $payload['items'],
            'subtotal' => $payload['subtotal'],
            'serviceFee' => $payload['serviceFee'],
            'extraCharge' => $payload['extraCharge'] ?? 0,
            'total' => $payload['total'],
            'emptyReceiptMessage' => null,
            'invoiceCount' => 1,
            'invoiceIndex' => 0,
            'allowDownloadPdf' => false,
            'showBackToMenu' => false,
        ]);
    }

    public function downloadReceiptPdf(Request $request)
    {
        $receiptData = $this->resolveReceiptData($request);
        if (!$receiptData['ok']) {
            return redirect('/kedai/pembayaran/struk')->with('error', $receiptData['message'] ?? 'Struk tidak tersedia.');
        }

        $order = $receiptData['order'];
        $items = $receiptData['items'];
        $subtotal = $receiptData['subtotal'];
        $serviceFee = $receiptData['serviceFee'];
        $extraCharge = $receiptData['extraCharge'] ?? 0;
        $total = $receiptData['total'];

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if (!in_array($paymentStatus, ['PAID', 'SUCCESS', 'SETTLEMENT'], true)) {
            return redirect('/kedai/pembayaran/struk?invoice_index=' . (int) ($receiptData['invoiceIndex'] ?? 0))
                ->with('error', 'Struk PDF hanya bisa diunduh setelah pembayaran lunas.');
        }

        $orderStatus = strtoupper((string) ($order->status ?? 'CONFIRMED'));
        $paymentPayload = is_array($order->payment_payload ?? null) ? $order->payment_payload : [];
        $paymentTypeRaw = trim((string) ($order->payment_type ?? ''));
        $paymentTypeLabel = match (strtolower($paymentTypeRaw)) {
            'bank_transfer' => 'Bank Transfer',
            'echannel' => 'Mandiri Bill',
            'cstore' => 'Convenience Store',
            'gopay' => 'GoPay',
            'qris' => 'QRIS',
            default => $paymentTypeRaw !== '' ? ucwords(str_replace('_', ' ', $paymentTypeRaw)) : '-',
        };

        $vaNumber = '-';
        if (!empty($paymentPayload['va_numbers']) && is_array($paymentPayload['va_numbers'])) {
            $firstVa = $paymentPayload['va_numbers'][0] ?? null;
            if (is_array($firstVa) && !empty($firstVa['va_number'])) {
                $bankLabel = !empty($firstVa['bank']) ? strtoupper((string) $firstVa['bank']) . ' ' : '';
                $vaNumber = $bankLabel . (string) $firstVa['va_number'];
            }
        } elseif (!empty($paymentPayload['permata_va_number'])) {
            $vaNumber = 'PERMATA ' . (string) $paymentPayload['permata_va_number'];
        } elseif (!empty($paymentPayload['bill_key']) || !empty($paymentPayload['biller_code'])) {
            $billerCode = (string) ($paymentPayload['biller_code'] ?? '-');
            $billKey = (string) ($paymentPayload['bill_key'] ?? '-');
            $vaNumber = trim($billerCode . ' / ' . $billKey, ' /');
        } elseif (!empty($paymentPayload['payment_code'])) {
            $vaNumber = (string) $paymentPayload['payment_code'];
        }

        $paymentLabel = match ($paymentStatus) {
            'PAID', 'SUCCESS', 'SETTLEMENT' => 'LUNAS',
            'FAILED' => 'GAGAL',
            'CANCELED' => 'DIBATALKAN',
            'EXPIRED' => 'KEDALUWARSA',
            default => 'MENUNGGU',
        };

        $orderLabel = match ($orderStatus) {
            'PENDING_PAYMENT' => 'Menunggu Pembayaran',
            'PAYMENT_FAILED' => 'Pembayaran Gagal',
            'CONFIRMED' => 'Terkonfirmasi',
            'IN_QUEUE' => 'Dalam Antrean',
            'IN_PROGRESS' => 'Sedang Diproses',
            'DELIVERED' => 'Disajikan',
            default => ucwords(strtolower(str_replace('_', ' ', $orderStatus))),
        };

        $displayOrderId = 'ORD-' . strtoupper(substr((string) $order->_id, -6));
        $paidAtLabel = $order->paid_at
            ? $order->paid_at->copy()->setTimezone(config('app.timezone'))->format('d M Y, H.i')
            : '-';

        $pdf = Pdf::loadView('frontliner.pembayaran.struk-pdf', [
            'order' => $order,
            'items' => $items,
            'subtotal' => $subtotal,
            'serviceFee' => $serviceFee,
            'extraCharge' => $extraCharge,
            'total' => $total,
            'displayOrderId' => $displayOrderId,
            'paymentTypeLabel' => $paymentTypeLabel,
            'vaNumber' => $vaNumber,
            'paymentLabel' => $paymentLabel,
            'orderLabel' => $orderLabel,
            'paidAtLabel' => $paidAtLabel,
            'invoiceCount' => $receiptData['invoiceCount'],
            'invoiceIndex' => $receiptData['invoiceIndex'],
        ])->setPaper('a4', 'portrait');

        $filename = 'struk-' . $displayOrderId . '-' . now()->format('Ymd-His') . '.pdf';
        return $pdf->download($filename);
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
            'allowDownloadPdf' => false,
        ]);
    }

    /**
     * Ekstrak unix timestamp dari midtrans_order_id dengan format ORDER-{orderId}-{timestamp}.
     * Digunakan untuk mengecek apakah snap token Midtrans (berlaku 24 jam) masih valid.
     */
    private function extractTimestampFromMidtransOrderId(string $midtransOrderId): int
    {
        $parts = explode('-', $midtransOrderId);
        if (count($parts) < 3) {
            return 0;
        }
        $last = (int) end($parts);
        return $last > 0 ? $last : 0;
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
        $sessionOrderType = strtoupper((string) $request->session()->get('order_type', 'DINE_IN'));
        $orderId = (string) $order->_id;
        $orderTableNumber = (int) ($order->table_number ?? 0);
        $isTakeAwaySession = $sessionOrderType === 'TAKE_AWAY';

        if ($isTakeAwaySession) {
            return $sessionOrderIds->contains($orderId)
                && $sessionReceiptTableId === 0
                && $orderTableNumber === 0;
        }

        return $sessionOrderIds->contains($orderId)
            && $sessionTableId > 0
            && $sessionReceiptTableId > 0
            && $orderTableNumber === $sessionTableId
            && $orderTableNumber === $sessionReceiptTableId;
    }

    private function resolveReceiptData(Request $request): array
    {
        $this->tableService->clearTableSessionIfInactive($request);

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
        $sessionOrderType = strtoupper((string) $request->session()->get('order_type', 'DINE_IN'));
        $isTakeAwaySession = $sessionOrderType === 'TAKE_AWAY';

        if ($sessionOrderIds->isEmpty()) {
            return ['ok' => false, 'message' => null];
        }

        if (!$isTakeAwaySession && $sessionReceiptTableId <= 0) {
            return ['ok' => false, 'message' => null];
        }

        $effectiveSessionTableId = $sessionTableId > 0 ? $sessionTableId : $sessionReceiptTableId;

        $ordersById = Order::whereIn('_id', $sessionOrderIds->all())
            ->get()
            ->keyBy(fn ($order) => (string) $order->_id);

        $validOrderIds = $sessionOrderIds->filter(function ($id) use ($ordersById, $effectiveSessionTableId, $sessionReceiptTableId, $isTakeAwaySession) {
            $order = $ordersById->get($id);
            if (!$order) {
                return false;
            }

            $tableNumber = (int) ($order->table_number ?? 0);
            if ($isTakeAwaySession) {
                return $tableNumber === 0 && $sessionReceiptTableId === 0;
            }

            return $tableNumber === $effectiveSessionTableId && $tableNumber === $sessionReceiptTableId;
        })->values();

        if ($validOrderIds->isEmpty()) {
            return ['ok' => false, 'message' => null];
        }

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
            return ['ok' => false, 'message' => null];
        }

        $request->session()->put('frontliner_receipt_order_id', $selectedOrderId);

        $orderStatus = strtoupper((string) ($order->status ?? ''));
        $deliveredAt = $order->delivered_at ?? $order->updated_at;
        if ($orderStatus === 'DELIVERED' && $deliveredAt && now()->gte($deliveredAt->copy()->addMinutes(150))) {
            $this->tableService->clearTableSession($request);
            return [
                'ok' => false,
                'message' => 'Sesi anda sudah berakhir. Silakan scan ulang QR meja jika ingin memesan lagi.',
            ];
        }

        $receiptPayload = $this->buildReceiptPayloadFromOrder($order);

        return [
            'ok' => true,
            'order' => $order,
            'items' => $receiptPayload['items'],
            'subtotal' => $receiptPayload['subtotal'],
            'serviceFee' => $receiptPayload['serviceFee'],
            'extraCharge' => $receiptPayload['extraCharge'],
            'total' => $receiptPayload['total'],
            'invoiceCount' => $validOrderIds->count(),
            'invoiceIndex' => $index,
        ];
    }

    private function buildReceiptPayloadFromOrder(Order $order): array
    {
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
        $extraCharge = (float) ($order->extra_charge ?? 0);
        $serviceFee = max(0, $total - $subtotal - $extraCharge);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'serviceFee' => $serviceFee,
            'extraCharge' => $extraCharge,
            'total' => $total,
        ];
    }
}
