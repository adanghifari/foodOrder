<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Carbon;

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
                    'paymentType' => (string) ($order->payment_type ?? ''),
                    'paymentPayload' => is_array($order->payment_payload) ? $order->payment_payload : [],
                    'totalPrice' => (float) ($order->total_price ?? 0),
                    'items' => is_array($order->items) ? $order->items : [],
                    'createdAt' => optional($order->created_at)?->toDateTimeString(),
                    'paidAt' => optional($order->paid_at)?->toDateTimeString(),
                    'effectiveAt' => optional($order->paid_at ?? $order->created_at)?->toDateTimeString(),
                ];
            })
            ->values();

        $businessTimezone = 'Asia/Jakarta';
        $todayStart = Carbon::now($businessTimezone)->startOfDay();
        $todayEnd = Carbon::now($businessTimezone)->endOfDay();

        $todayPayments = $payments->filter(function ($payment) use ($todayStart, $todayEnd, $businessTimezone) {
            $effectiveAt = (string) ($payment['effectiveAt'] ?? '');

            if ($effectiveAt === '') {
                return false;
            }

            try {
                $effectiveAtDate = Carbon::parse($effectiveAt)->setTimezone($businessTimezone);
            } catch (\Throwable $exception) {
                return false;
            }

            return $effectiveAtDate->between($todayStart, $todayEnd);
        })->values();

        $previousPayments = $payments->reject(function ($payment) use ($todayPayments) {
            return $todayPayments->contains('orderId', (string) ($payment['orderId'] ?? ''));
        })->values();

        $summary = [
            'total' => $todayPayments->count(),
            'paid' => $todayPayments->whereIn('paymentStatus', ['PAID', 'SUCCESS', 'SETTLEMENT'])->count(),
            'pending' => $todayPayments->whereIn('paymentStatus', ['PENDING', 'UNPAID'])->count(),
            'failed' => $todayPayments->filter(function ($payment) {
                return in_array(strtoupper((string) ($payment['paymentStatus'] ?? '')), ['FAILED', 'DENY', 'CANCELED', 'CANCEL', 'EXPIRED', 'EXPIRE'], true);
            })->count(),
        ];

        $detailOrderId = request()->query('detail');
        $selectedPayment = null;

        if (!empty($detailOrderId)) {
            $selectedPayment = $payments->firstWhere('orderId', (string) $detailOrderId);
        }

        return view('backoffice.payment.index', [
            'todayPayments' => $todayPayments,
            'previousPayments' => $previousPayments,
            'summary' => $summary,
            'selectedPayment' => $selectedPayment,
            'businessDateLabel' => $todayStart->translatedFormat('d M Y'),
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
