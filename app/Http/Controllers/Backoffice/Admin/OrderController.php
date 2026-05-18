<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Order\Services\OrderService;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
	private $allowedStatuses = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS', 'DELIVERED'];
	private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];

	public function __construct(private readonly OrderService $orderService)
	{
	}

	public function indexPage()
	{
		$paidOrders = collect($this->orderService->adminList())
			->filter(function ($order) {
				return empty($order['orderDeletedAt']);
			})
			->filter(function ($order) {
				return in_array(strtoupper((string) ($order['paymentStatus'] ?? '')), self::PAID_STATUSES, true);
			})
			->values();

		$bookingSnapshots = Booking::with('customer')
			->orderBy('booking_start_at', 'asc')
			->orderBy('_id', 'desc')
			->get()
			->map(function (Booking $booking) {
				$mappedStatus = match (strtoupper((string) ($booking->status ?? ''))) {
					'CONFIRMED' => 'CONFIRMED',
					'SEATED' => 'IN_PROGRESS',
					'COMPLETED' => 'DELIVERED',
					default => null,
				};

				if ($mappedStatus === null) {
					return null;
				}

				$customer = $booking->customer;
				$customerName = (string) ($customer?->name ?? $customer?->username ?? '-');
				$customerEmail = (string) ($customer?->email ?? $customer?->username ?? '-');
				$bookingId = (string) $booking->_id;

				return [
					'orderId' => 'BOOKING:' . $bookingId,
					'displayId' => 'BKG-' . strtoupper(substr($bookingId, -6)),
					'sourceType' => 'BOOKING',
					'customer' => [
						'name' => $customerName,
						'username' => $customerName,
						'email' => $customerEmail,
					],
					'tableNumber' => (int) ($booking->table_number ?? 0),
					'status' => $mappedStatus,
					'paymentStatus' => 'SUCCESS',
					'paidAt' => optional($booking->booking_start_at)?->toDateTimeString(),
					'queueNumber' => 0,
					'totalPrice' => (int) ($booking->extra_charge ?? 0),
				];
			})
			->filter()
			->values();

		$orders = $paidOrders
			->map(function (array $order) {
				$order['sourceType'] = 'ORDER';
				return $order;
			})
			->concat($bookingSnapshots)
			->values();

		$businessTimezone = 'Asia/Jakarta';
		$todayStart = Carbon::now($businessTimezone)->startOfDay();
		$todayEnd = Carbon::now($businessTimezone)->endOfDay();

		$todayOrders = $orders->filter(function ($order) use ($todayStart, $todayEnd, $businessTimezone) {
			$paidAt = (string) ($order['paidAt'] ?? '');

			if ($paidAt === '') {
				return false;
			}

			try {
				$paidAtDate = Carbon::parse($paidAt)->setTimezone($businessTimezone);
			} catch (\Throwable $exception) {
				return false;
			}

			return $paidAtDate->between($todayStart, $todayEnd);
		})->values();

		$todayDeliveredOrders = $todayOrders
			->filter(function ($order) {
				return strtoupper((string) ($order['status'] ?? '')) === 'DELIVERED';
			})
			->values();

		$todayQueueOrders = $todayOrders
			->reject(function ($order) {
				return strtoupper((string) ($order['status'] ?? '')) === 'DELIVERED';
			})
			->values();

		$previousOrders = $orders->reject(function ($order) use ($todayOrders) {
			return $todayOrders->contains('orderId', (string) ($order['orderId'] ?? ''));
		})->values();

		$detailOrderId = request()->query('detail');
		$selectedOrder = null;

		if (!empty($detailOrderId)) {
			$selectedOrder = $orders->firstWhere('orderId', (string) $detailOrderId);
		}

		$summary = [
			'total' => $todayOrders->count(),
			'waiting' => $todayQueueOrders->whereIn('status', ['CONFIRMED', 'IN_QUEUE'])->count(),
			'processing' => $todayQueueOrders->where('status', 'IN_PROGRESS')->count(),
			'delivered' => $todayDeliveredOrders->count(),
		];

		return view('backoffice.order.index', [
			'todayOrders' => $todayQueueOrders,
			'todayDeliveredOrders' => $todayDeliveredOrders,
			'previousOrders' => $previousOrders,
			'summary' => $summary,
			'selectedOrder' => $selectedOrder,
			'statusOptions' => $this->allowedStatuses,
			'businessDateLabel' => $todayStart->translatedFormat('d M Y'),
		]);
	}

	public function updateStatusPage(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required|string|in:' . implode(',', $this->allowedStatuses),
		]);

		if ($validator->fails()) {
			if ($request->expectsJson() || $request->ajax()) {
				return response()->json([
					'status' => 'error',
					'message' => 'Validasi status tidak sesuai.',
					'errors' => $validator->errors(),
				], 422);
			}

			return redirect()->back()->withErrors($validator)->withInput();
		}

		$updated = $this->orderService->updateStatus((string) $id, (string) $request->input('status'));

		if (!$updated) {
			if ($request->expectsJson() || $request->ajax()) {
				return response()->json([
					'status' => 'error',
					'message' => 'Order tidak ditemukan.',
				], 404);
			}

			return redirect()->back()->with('error', 'Order tidak ditemukan.');
		}

		if ($request->expectsJson() || $request->ajax()) {
			return response()->json([
				'status' => 'success',
				'message' => 'Status pesanan berhasil diperbarui.',
			]);
		}

		return redirect()->back()->with('success', 'Status order berhasil diperbarui.');
	}

	public function deletePage(string $id)
	{
		$order = \App\Models\Order::find($id);

		if (!$order) {
			return redirect('/backoffice/daftar_pesanan')->with('error', 'Order tidak ditemukan.');
		}

		$order->update([
			'order_deleted_at' => now(),
		]);

		$displayId = 'ORD-' . strtoupper(substr((string) $order->_id, -6));

		return redirect('/backoffice/daftar_pesanan')->with(
			'success',
			'Order ' . $displayId . ' berhasil dihapus dari daftar pesanan. Riwayat pembayaran tetap tersimpan.'
		);
	}

	public function list()
	{
		$data = $this->orderService->adminList();

		return response()->json([
			'status' => 'success',
			'message' => 'Orders retrieved',
			'data' => $data
		]);
	}

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

		$updated = $this->orderService->updateStatus((string) $id, (string) $request->input('status'));
		if (!$updated) {
			return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Order status updated',
			'data' => 'Order status updated'
		]);
	}

	public function count()
	{
		return response()->json([
			'status' => 'success',
			'message' => 'Order count retrieved',
			'data' => ['count' => $this->orderService->count()]
		]);
	}
}
