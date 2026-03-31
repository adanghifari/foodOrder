<?php

namespace App\Http\Controllers\Mobile\Customer;

use App\Http\Controllers\Controller;
use App\Services\CartService;
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
			'tableNumber' => 'nullable|integer|min:1',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$user = $request->user();
		$sessionTableId = $request->hasSession() ? $request->session()->get('table_id') : null;
		$tableNumber = $request->input('tableNumber', $sessionTableId);

		$result = $this->cartService->checkout($user, $tableNumber ? (int) $tableNumber : null);
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
