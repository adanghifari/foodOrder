<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Http\Controllers\Controller;
use App\Domains\Menu\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
	public function __construct(private readonly MenuService $menuService)
	{
	}

	private function withStock($item): array
	{
		$data = $item->toArray();
		$data['stock'] = (int) ($data['stock'] ?? 0);

		return $data;
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
		$items->setCollection(
			$items->getCollection()->map(fn ($item) => $this->withStock($item))
		);

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
		$items = $items->map(fn ($item) => $this->withStock($item));

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
		$items = $items->map(fn ($item) => $this->withStock($item));

		return response()->json([
			'status' => 'success',
			'message' => 'Menu items retrieved',
			'data' => $items
		]);
	}
}
