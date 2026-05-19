<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Order\Services\OrderService;
use App\Domains\Table\Services\TableService;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
	private $allowedStatuses = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS', 'DELIVERED'];
	private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];

	public function __construct(
		private readonly OrderService $orderService,
		private readonly TableService $tableService
	)
	{
	}

	public function indexPage()
	{
		$this->tableService->autoClearExpiredDeliveredAssignments();

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
					'orderType' => 'booking_dine_in',
					'customer' => [
						'name' => $customerName,
						'username' => $customerName,
						'email' => $customerEmail,
					],
					'tableNumber' => (int) ($booking->table_number ?? 0),
					'status' => $mappedStatus,
					'paymentStatus' => 'SUCCESS',
					'paidAt' => optional($booking->booking_start_at)?->toDateTimeString(),
					'bookingStartAt' => optional($booking->booking_start_at)?->toDateTimeString(),
					'durationHours' => (int) ($booking->duration_hours ?? 1),
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
		$resolveEventAt = function (array $order) use ($businessTimezone): ?Carbon {
			$sourceType = strtoupper((string) ($order['sourceType'] ?? 'ORDER'));
			$orderType = strtolower((string) ($order['orderType'] ?? ''));
			$isBookingDineIn = $sourceType === 'BOOKING' || $orderType === 'booking_dine_in';
			$eventRaw = $isBookingDineIn
				? (string) (($order['bookingStartAt'] ?? $order['booking_start_at'] ?? $order['paidAt'] ?? ''))
				: (string) (($order['paidAt'] ?? ''));

			if ($eventRaw === '') {
				return null;
			}

			try {
				return Carbon::parse($eventRaw)->setTimezone($businessTimezone);
			} catch (\Throwable $exception) {
				return null;
			}
		};

		$orders = $orders
			->map(function (array $order) use ($resolveEventAt) {
				$eventAt = $resolveEventAt($order);
				$order['eventAt'] = $eventAt?->toDateTimeString();
				$order['eventTs'] = $eventAt?->timestamp ?? 0;
				return $order;
			})
			->values();

		$todayOrders = $orders->filter(function ($order) use ($todayStart, $todayEnd, $resolveEventAt) {
			$eventAt = $resolveEventAt($order);
			if (!$eventAt) {
				return false;
			}
			return $eventAt->between($todayStart, $todayEnd);
		})->values();

		$bookingTotalCount = $todayOrders
			->filter(function ($order) {
				$sourceType = strtoupper((string) ($order['sourceType'] ?? 'ORDER'));
				$orderType = strtolower((string) ($order['orderType'] ?? ''));
				return $sourceType === 'BOOKING' || $orderType === 'booking_dine_in';
			})
			->count();

		$todayDeliveredOrders = $todayOrders
			->filter(function ($order) {
				return strtoupper((string) ($order['status'] ?? '')) === 'DELIVERED';
			})
			->values();

		$todayQueueOrders = $todayOrders
			->reject(function ($order) {
				return strtoupper((string) ($order['status'] ?? '')) === 'DELIVERED';
			})
			->sortBy('eventTs')
			->values();

		$previousOrders = $orders->filter(function ($order) use ($todayStart, $resolveEventAt) {
			$eventAt = $resolveEventAt($order);
			if (!$eventAt) {
				return false;
			}
			return $eventAt->lt($todayStart);
		})->values();

		$detailOrderId = request()->query('detail');
		$selectedOrder = null;

		if (!empty($detailOrderId)) {
			$selectedOrder = $orders->firstWhere('orderId', (string) $detailOrderId);
		}

		$summary = [
			'total' => $todayOrders->count(),
			'booking_total' => $bookingTotalCount,
			'confirmed' => $todayQueueOrders->where('status', 'CONFIRMED')->count(),
			'in_queue' => $todayQueueOrders->where('status', 'IN_QUEUE')->count(),
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
