<?php

namespace App\Http\Controllers\Admin;

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
