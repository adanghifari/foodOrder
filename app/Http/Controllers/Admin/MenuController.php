<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Menu\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
	private array $allowedCategories = ['makanan utama', 'cemilan', 'minuman'];

	public function __construct(private readonly MenuService $menuService)
	{
	}

	public function create(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
			'price' => 'required|numeric|min:0',
			'category' => 'required|string|in:' . implode(',', $this->allowedCategories),
			'image_url' => 'nullable|string|max:500',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$validated = $validator->validated();
		$item = $this->menuService->create($validated);

		return response()->json([
			'status' => 'success',
			'message' => 'Menu item created',
			'data' => $item
		], 201);
	}

	public function update(Request $request, $id)
	{
		$item = $this->menuService->findById((string) $id);

		if (!$item) {
			return response()->json([
				'status' => 'error',
				'message' => 'Menu item not found'
			], 404);
		}

		$validator = Validator::make($request->all(), [
			'name' => 'sometimes|required|string|max:255',
			'description' => 'sometimes|nullable|string',
			'price' => 'sometimes|required|numeric|min:0',
			'category' => 'sometimes|required|string|in:' . implode(',', $this->allowedCategories),
			'image_url' => 'sometimes|nullable|string|max:500',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$validated = $validator->validated();

		if (count($validated) === 0) {
			return response()->json([
				'status' => 'error',
				'message' => 'No valid fields provided for update'
			], 422);
		}

		$item = $this->menuService->update($item, $validated);

		return response()->json([
			'status' => 'success',
			'message' => 'Menu item updated',
			'data' => $item
		]);
	}

	public function remove($id)
	{
		$item = $this->menuService->findById((string) $id);

		if (!$item) {
			return response()->json([
				'status' => 'error',
				'message' => 'Menu item not found'
			], 404);
		}

		$this->menuService->remove($item);

		return response()->json([
			'status' => 'success',
			'message' => 'Menu item deleted',
			'data' => ['deleted' => true]
		]);
	}

	public function uploadImage(Request $request, $id)
	{
		$item = $this->menuService->findById((string) $id);

		if (!$item) {
			return response()->json([
				'status' => 'error',
				'message' => 'Menu item not found'
			], 404);
		}

		$validator = Validator::make($request->all(), [
			'image' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		if ($request->hasFile('image')) {
			$uploaded = $this->menuService->uploadImage($item, $request->file('image'));

			return response()->json([
				'status' => 'success',
				'message' => 'Menu image uploaded',
				'data' => [
					'image_url' => $uploaded['image_url'],
					'item' => $uploaded['item']
				]
			]);
		}

		return response()->json([
			'status' => 'error',
			'message' => 'Image upload failed'
		], 500);
	}

	public function deleteImage($id)
	{
		$item = $this->menuService->findById((string) $id);

		if (!$item) {
			return response()->json([
				'status' => 'error',
				'message' => 'Menu item not found'
			], 404);
		}

		if (!$item->image_url) {
			return response()->json([
				'status' => 'error',
				'message' => 'Menu item has no image'
			], 400);
		}

		$this->menuService->deleteImage($item);

		return response()->json([
			'status' => 'success',
			'message' => 'Menu image deleted',
			'data' => ['deleted' => true]
		]);
	}

	public function count()
	{
		$count = $this->menuService->count();
		return response()->json([
			'status' => 'success',
			'message' => 'Menu count retrieved',
			'data' => ['count' => $count]
		]);
	}
}
