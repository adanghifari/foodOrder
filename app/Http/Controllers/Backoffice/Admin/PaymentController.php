<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

class PaymentController extends Controller
{
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];

    public function indexPage()
    {
        $payments = Order::with('customer')
            ->whereIn('payment_status', self::PAID_STATUSES)
            ->orderBy('_id', 'desc')
            ->get()
            ->map(function (Order $order) {
                $customer = $order->customer;
                $paymentStatus = (string) ($order->payment_status ?? 'PENDING');
                $fallbackName = (string) ($order->customer_name ?? '-');
                $fallbackEmail = (string) ($order->customer_email ?? '-');

                return [
                    'orderId' => (string) $order->_id,
                    'displayId' => 'ORD-' . strtoupper(substr((string) $order->_id, -6)),
                    'customerName' => (string) ($customer->name ?? $fallbackName),
                    'customerEmail' => (string) (($customer->email ?? null) ?: ($customer->username ?? $fallbackEmail)),
                    'tableNumber' => (int) ($order->table_number ?? 0),
                    'orderStatus' => (string) ($order->status ?? 'UNKNOWN'),
                    'paymentStatus' => $paymentStatus,
                    'paymentType' => (string) ($order->payment_type ?? ''),
                    'paymentPayload' => is_array($order->payment_payload) ? $order->payment_payload : [],
                    'totalPrice' => (float) ($order->total_price ?? 0),
                    'items' => is_array($order->items) ? $order->items : [],
                    'createdAt' => optional($order->created_at)?->toDateTimeString(),
                    'paidAt' => optional($order->paid_at)?->toDateTimeString(),
                ];
            })
            ->values();

        $summary = [
            'total' => $payments->count(),
            'revenue' => (float) $payments->sum('totalPrice'),
            'average' => (float) ($payments->count() > 0 ? ($payments->sum('totalPrice') / $payments->count()) : 0),
            'tables' => $payments->pluck('tableNumber')->filter(fn ($tableNumber) => (int) $tableNumber > 0)->unique()->count(),
        ];

        $detailOrderId = request()->query('detail');
        $selectedPayment = null;

        if (!empty($detailOrderId)) {
            $selectedPayment = $payments->firstWhere('orderId', (string) $detailOrderId);
        }

        return view('backoffice.payment.index', [
            'payments' => $payments,
            'summary' => $summary,
            'selectedPayment' => $selectedPayment,
        ]);
    }

    public function delete(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return redirect('/backoffice/pembayaran')->with('error', 'Data pembayaran tidak ditemukan.');
        }

        $displayId = 'ORD-' . strtoupper(substr((string) $order->_id, -6));
        $order->delete();

        return redirect('/backoffice/pembayaran')->with('success', 'Data pembayaran ' . $displayId . ' berhasil dihapus.');
    }
}
