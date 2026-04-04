<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

class PaymentController extends Controller
{
    public function indexPage()
    {
        $payments = Order::with('customer')
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
                    'totalPrice' => (float) ($order->total_price ?? 0),
                    'items' => is_array($order->items) ? $order->items : [],
                    'createdAt' => optional($order->created_at)?->toDateTimeString(),
                ];
            })
            ->values();

        $summary = [
            'total' => $payments->count(),
            'paid' => $payments->whereIn('paymentStatus', ['PAID', 'SUCCESS'])->count(),
            'pending' => $payments->whereIn('paymentStatus', ['PENDING', 'UNPAID'])->count(),
            'failed' => $payments->whereIn('paymentStatus', ['FAILED', 'CANCELED', 'EXPIRED'])->count(),
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
}
