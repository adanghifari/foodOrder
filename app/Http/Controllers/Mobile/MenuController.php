<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Domains\Menu\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
	public function __construct(private readonly MenuService $menuService)
	{
	}

	public function list(Request $request)
	{
		$validator = Validator::make($request->query(), [
			'per_page' => 'nullable|integer|min:1|max:100',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$perPage = (int) $request->query('per_page', 10);

		$items = $this->menuService->listPaginated($perPage);

		return response()->json([
			'status' => 'success',
			'message' => 'Menu items retrieved',
			'data' => $items
		]);
	}

	public function search(Request $request)
	{
		$name = $request->query('name');

		if (!$name || trim($name) === '') {
			return response()->json([
				'status' => 'error',
				'message' => 'Search query cannot be empty'
			], 422);
		}

		$items = $this->menuService->searchByName($name);

		return response()->json([
			'status' => 'success',
			'message' => 'Menu items retrieved',
			'data' => $items
		]);
	}

	public function filter(Request $request)
	{
		$validator = Validator::make($request->query(), [
			'category' => 'required|string|in:makanan utama,cemilan,minuman',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$category = (string) $request->query('category');

		$items = $this->menuService->filterByCategory($category);

		return response()->json([
			'status' => 'success',
			'message' => 'Menu items retrieved',
			'data' => $items
		]);
	}
}
