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

}
