<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Domains\Payment\Services\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function list()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Payment list retrieved',
            'data' => $this->paymentService->listPayments(),
        ]);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
            'finish_redirect_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $result = $this->paymentService->createTransaction(
            (string) $request->input('order_id'),
            null,
            $request->filled('finish_redirect_url')
                ? (string) $request->input('finish_redirect_url')
                : null
        );

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], (int) ($result['status'] ?? 500));
    }

    public function continuePending(Request $request, string $orderId)
    {
        $order = $this->findCustomerOrder($request, $orderId);
        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order tidak ditemukan.',
            ], 404);
        }

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if (in_array($paymentStatus, ['PAID', 'SUCCESS', 'SETTLEMENT'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembayaran sudah lunas.',
            ], 409);
        }

        // Smart reuse: jika snap token Midtrans masih valid (< 24 jam), langsung kembalikan redirect_url yang ada
        $existingPaymentUrl      = trim((string) ($order->payment_url ?? ''));
        $existingMidtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));

        if ($existingPaymentUrl !== '' && $existingMidtransOrderId !== '') {
            $tokenTimestamp = $this->extractTimestampFromMidtransOrderId($existingMidtransOrderId);
            $isTokenValid   = $tokenTimestamp > 0 && (now()->timestamp - $tokenTimestamp) < 86400;

            if ($isTokenValid) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment transaction reused (valid)',
                    'data' => [
                        'order_id' => (string) $order->_id,
                        'midtrans_order_id' => $existingMidtransOrderId,
                        'redirect_url' => $existingPaymentUrl,
                    ],
                ], 200);
            }
        }

        // Jika expired atau belum ada transaksi, batalkan transaksi lama jika ada
        if ($existingMidtransOrderId !== '') {
            $this->paymentService->cancelTransaction($existingMidtransOrderId, false);
        }

        $finishRedirectUrl = $request->filled('finish_redirect_url')
            ? (string) $request->input('finish_redirect_url')
            : null;

        $result = $this->paymentService->createTransaction(
            (string) $order->_id,
            null,
            $finishRedirectUrl,
            true // forceNewTransaction = true karena reuse sudah kita handle di atas
        );

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], (int) ($result['status'] ?? 500));
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

    public function checkStatus(Request $request, string $orderId)
    {
        $order = $this->findCustomerOrder($request, $orderId);
        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order tidak ditemukan.',
            ], 404);
        }

        $result = $this->paymentService->syncStatusByOrderId((string) $order->_id);

        return response()->json([
            'status'  => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
            'data'    => $result['data'] ?? null,
        ], (int) ($result['status'] ?? 500));
    }

    public function changeMethod(Request $request, string $orderId)
    {
        $order = $this->findCustomerOrder($request, $orderId);
        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order tidak ditemukan.',
            ], 404);
        }

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if (in_array($paymentStatus, ['PAID', 'SUCCESS', 'SETTLEMENT'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembayaran sudah lunas.',
            ], 409);
        }

        $existingMidtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));
        if ($existingMidtransOrderId !== '') {
            $this->paymentService->cancelTransaction($existingMidtransOrderId, false);
        }

        $finishRedirectUrl = $request->filled('finish_redirect_url')
            ? (string) $request->input('finish_redirect_url')
            : null;

        $result = $this->paymentService->createTransaction(
            (string) $order->_id,
            null,
            $finishRedirectUrl,
            true
        );

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], (int) ($result['status'] ?? 500));
    }

    public function cancelPending(Request $request, string $orderId)
    {
        $order = $this->findCustomerOrder($request, $orderId);
        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order tidak ditemukan.',
            ], 404);
        }

        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        if ($paymentStatus !== 'PENDING') {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembayaran ini sudah tidak bisa dibatalkan.',
            ], 409);
        }

        $midtransOrderId = trim((string) ($order->midtrans_order_id ?? ''));
        if ($midtransOrderId === '') {
            $result = $this->paymentService->cancelPendingOrderLocally((string) $order->_id);
            return response()->json([
                'status' => $result['ok'] ? 'success' : 'error',
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ], (int) ($result['status'] ?? 500));
        }

        $result = $this->paymentService->cancelTransaction($midtransOrderId);
        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], (int) ($result['status'] ?? 500));
    }

    private function findCustomerOrder(Request $request, string $orderId): ?Order
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        return Order::where('_id', $orderId)
            ->where('customer_id', (string) $user->_id)
            ->first();
    }
}
