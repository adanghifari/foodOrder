<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Http\Controllers\Controller;
use App\Domains\Order\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
	public function __construct(private readonly OrderService $orderService)
	{
	}

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

		$result = $this->orderService->createFromItems(
			(string) $request->user()->_id,
			$request->input('items'),
			(int) $request->input('tableNumber')
		);

		if (!$result['ok']) {
			return response()->json([
				'status' => 'error',
				'message' => $result['message']
			], $result['status']);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Order created',
			'data' => $this->orderService->buildOrderResponse($result['order'], clone $request->user())
		]);
	}

	public function myOrders(Request $request)
	{
		$user = $request->user();
		$data = $this->orderService->myOrders((string) $user->_id, $user);

		return response()->json([
			'status' => 'success',
			'message' => 'Orders retrieved',
			'data' => $data
		]);
	}

	public function downloadReceiptPdf(Request $request, string $orderId)
	{
		$user = $request->user();
		$order = \App\Models\Order::with('customer')->where('_id', $orderId)
			->where('customer_id', (string) $user->_id)
			->first();

		if (!$order) {
			return response()->json([
				'status' => 'error',
				'message' => 'Order tidak ditemukan.',
			], 404);
		}

		$paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
		if (!in_array($paymentStatus, ['PAID', 'SUCCESS', 'SETTLEMENT'], true)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Struk PDF hanya bisa diunduh setelah pembayaran lunas.',
			], 400);
		}

		$orderStatus = strtoupper((string) ($order->status ?? 'CONFIRMED'));
		$paymentPayload = is_array($order->payment_payload ?? null) ? $order->payment_payload : [];
		$paymentTypeRaw = trim((string) ($order->payment_type ?? ($paymentPayload['payment_type'] ?? '')));
		$paymentTypeLabel = match (strtolower($paymentTypeRaw)) {
			'bank_transfer' => 'Bank Transfer',
			'echannel' => 'Mandiri Bill',
			'cstore' => 'Convenience Store',
			'gopay' => 'GoPay',
			'qris' => 'QRIS',
			default => $paymentTypeRaw !== '' ? ucwords(str_replace('_', ' ', $paymentTypeRaw)) : '-',
		};

		$customerName = trim((string) ($order->customer_name ?? ($order->customer?->name ?? $user->name ?? '-')));
		if ($customerName === '') {
			$customerName = '-';
		}
		$customerEmail = trim((string) ($order->customer_email ?? ($order->customer?->email ?? $order->customer?->username ?? $user->email ?? $user->username ?? '-')));
		if ($customerEmail === '') {
			$customerEmail = '-';
		}

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

		$paymentLabel = 'LUNAS';

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
			? $order->paid_at->copy()->setTimezone(config('app.timezone', 'UTC'))->format('d M Y, H.i')
			: '-';

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

		$pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('frontliner.pembayaran.struk-pdf', [
			'order' => $order,
			'items' => $items,
			'subtotal' => $subtotal,
			'serviceFee' => $serviceFee,
			'extraCharge' => $extraCharge,
			'total' => $total,
			'displayOrderId' => $displayOrderId,
			'customerName' => $customerName,
			'customerEmail' => $customerEmail,
			'paymentTypeLabel' => $paymentTypeLabel,
			'vaNumber' => $vaNumber,
			'paymentLabel' => $paymentLabel,
			'orderLabel' => $orderLabel,
			'paidAtLabel' => $paidAtLabel,
			'invoiceCount' => 1,
			'invoiceIndex' => 0,
		])->setPaper('a4', 'portrait');

		$filename = 'struk-' . $displayOrderId . '-' . now()->format('Ymd-His') . '.pdf';
		return $pdf->download($filename);
	}
}
