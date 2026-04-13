<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Menu\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
	private array $allowedCategories = ['makanan utama', 'cemilan', 'minuman'];

	public function __construct(private readonly MenuService $menuService)
	{
	}

	public function indexPage(Request $request)
	{
		$menus = $this->menuService->listAll()->map(function ($item) {
			$item->stock = (int) ($item->stock ?? 0);
			return $item;
		});

		$selectedMenu = null;
		$selectedEditMenu = null;
		$showCreateModal = $request->query('create') === '1';
		$detailId = (string) $request->query('detail', '');
		$editId = (string) $request->query('edit', '');

		if ($detailId !== '') {
			$selectedMenu = $this->menuService->findById($detailId);
			if ($selectedMenu) {
				$selectedMenu->stock = (int) ($selectedMenu->stock ?? 0);
			}
		}

		if ($editId !== '') {
			$selectedEditMenu = $this->menuService->findById($editId);
			if ($selectedEditMenu) {
				$selectedEditMenu->stock = (int) ($selectedEditMenu->stock ?? 0);
			}
		}

		return view('backoffice.menu.index', [
			'menus' => $menus,
			'selectedMenu' => $selectedMenu,
			'selectedEditMenu' => $selectedEditMenu,
			'showCreateModal' => $showCreateModal,
			'allowedCategories' => $this->allowedCategories,
		]);
	}

	public function createPage(): RedirectResponse
	{
		return redirect('/backoffice/daftar_menu?create=1');
	}

	public function storePage(Request $request): RedirectResponse
	{
		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
			'price' => 'required|numeric|min:0',
			'stock' => 'required|integer|min:0',
			'category' => 'required|string|in:' . implode(',', $this->allowedCategories),
			'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
		]);

		if ($validator->fails()) {
			return redirect('/backoffice/daftar_menu?create=1')
				->withErrors($validator)
				->withInput();
		}

		$validated = $validator->safe()->except('image');
		$validated['stock'] = (int) ($validated['stock'] ?? 0);

		$item = $this->menuService->create($validated);

		if ($request->hasFile('image')) {
			$this->menuService->uploadImage($item, $request->file('image'));
		}

		return redirect('/backoffice/daftar_menu')->with('success', 'Menu baru berhasil ditambahkan.');
	}

	public function showPage(string $id): RedirectResponse
	{
		return redirect('/backoffice/daftar_menu?detail=' . urlencode($id));
	}

	public function editPage(string $id): RedirectResponse
	{
		$menu = $this->menuService->findById($id);

		if (!$menu) {
			abort(404);
		}

		return redirect('/backoffice/daftar_menu?edit=' . urlencode($id));
	}

	public function updatePage(Request $request, string $id): RedirectResponse
	{
		$item = $this->menuService->findById($id);

		if (!$item) {
			abort(404);
		}

		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
			'price' => 'required|numeric|min:0',
			'stock' => 'required|integer|min:0',
			'category' => 'required|string|in:' . implode(',', $this->allowedCategories),
			'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
			'remove_image' => 'nullable|boolean',
		]);

		if ($validator->fails()) {
			return redirect('/backoffice/daftar_menu?edit=' . urlencode($id))
				->withErrors($validator)
				->withInput();
		}

		$validated = $validator->safe()->except(['image', 'remove_image']);
		$validated['stock'] = (int) ($validated['stock'] ?? 0);
		$removeImage = $request->boolean('remove_image');

		$this->menuService->update($item, $validated);

		if ($request->hasFile('image')) {
			$this->menuService->uploadImage($item, $request->file('image'));
		} elseif ($removeImage && $item->image_url) {
			$this->menuService->deleteImage($item);
		}

		return redirect('/backoffice/daftar_menu')->with('success', 'Menu berhasil diperbarui.');
	}

	public function deletePage(string $id): RedirectResponse
	{
		$item = $this->menuService->findById($id);

		if (!$item) {
			return redirect('/backoffice/daftar_menu')->with('error', 'Menu tidak ditemukan.');
		}

		$this->menuService->remove($item);

		return redirect('/backoffice/daftar_menu')->with('success', 'Menu berhasil dihapus.');
	}

	public function create(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
			'price' => 'required|numeric|min:0',
			'stock' => 'sometimes|integer|min:0',
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
		$validated['stock'] = (int) ($validated['stock'] ?? 0);
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
			'stock' => 'sometimes|required|integer|min:0',
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
