<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Http\Controllers\Controller;
use App\Domains\Cart\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
	public function __construct(private readonly CartService $cartService)
	{
	}

	public function add(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'menuItemId' => 'required|string',
			'quantity' => 'required|integer|min:1',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$result = $this->cartService->addOrUpdateItem(
			(string) $request->user()->_id,
			(string) $request->input('menuItemId'),
			(int) $request->input('quantity')
		);

		if (!$result['ok']) {
			return response()->json([
				'status' => 'error',
				'message' => $result['message']
			], $result['status']);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Item quantity updated in cart',
			'data' => ['updated' => true]
		]);
	}

	public function get(Request $request)
	{
		$data = $this->cartService->getCartData((string) $request->user()->_id);

		return response()->json([
			'status' => 'success',
			'message' => 'Cart retrieved',
			'data' => $data
		]);
	}

	public function remove(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'menuItemId' => 'required|string',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$result = $this->cartService->removeItem(
			(string) $request->user()->_id,
			(string) $request->input('menuItemId')
		);

		if (!$result['ok']) {
			return response()->json([
				'status' => 'error',
				'message' => $result['message']
			], $result['status']);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Item removed from cart',
			'data' => ['deleted' => true]
		]);
	}

	public function checkout(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'orderType' => 'required|string|in:booking_dine_in,dine_in,pickup',
			'tableNumber' => 'nullable|integer|min:1',
			'bookingStartAt' => 'nullable|string',
			'durationHours' => 'nullable|integer|in:2,4,6,8',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$user = $request->user();
		$orderType = (string) $request->input('orderType');
		$tableNumber = $request->filled('tableNumber')
			? (int) $request->input('tableNumber')
			: null;
		$bookingStartAt = $request->filled('bookingStartAt')
			? (string) $request->input('bookingStartAt')
			: null;
		$durationHours = $request->filled('durationHours')
			? (int) $request->input('durationHours')
			: null;

		$result = $this->cartService->checkout(
			$user,
			$orderType,
			$tableNumber,
			$bookingStartAt,
			$durationHours
		);
		if (!$result['ok']) {
			return response()->json([
				'status' => 'error',
				'message' => $result['message']
			], $result['status']);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Checkout success',
			'data' => $result['data']
		]);
	}
}
