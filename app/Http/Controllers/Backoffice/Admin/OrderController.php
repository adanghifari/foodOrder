<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Order\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
	private $allowedStatuses = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS', 'DELIVERED'];

	public function __construct(private readonly OrderService $orderService)
	{
	}

	public function indexPage()
	{
		$orders = collect($this->orderService->adminList());
		$detailOrderId = request()->query('detail');
		$selectedOrder = null;

		if (!empty($detailOrderId)) {
			$selectedOrder = $orders->firstWhere('orderId', (string) $detailOrderId);
		}

		$summary = [
			'total' => $orders->count(),
			'waiting' => $orders->whereIn('status', ['CONFIRMED', 'IN_QUEUE'])->count(),
			'processing' => $orders->where('status', 'IN_PROGRESS')->count(),
			'delivered' => $orders->where('status', 'DELIVERED')->count(),
		];

		return view('backoffice.order.index', [
			'orders' => $orders,
			'summary' => $summary,
			'selectedOrder' => $selectedOrder,
			'statusOptions' => $this->allowedStatuses,
		]);
	}

	public function updateStatusPage(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required|string|in:' . implode(',', $this->allowedStatuses),
		]);

		if ($validator->fails()) {
			return redirect()->back()->withErrors($validator)->withInput();
		}

		$updated = $this->orderService->updateStatus((string) $id, (string) $request->input('status'));

		if (!$updated) {
			return redirect()->back()->with('error', 'Order tidak ditemukan.');
		}

		return redirect()->back()->with('success', 'Status order berhasil diperbarui.');
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
