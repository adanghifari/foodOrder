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
			'categoryTagMap' => config('menu_taxonomy.category_tags', []),
			'categoryMetadataMap' => config('menu_taxonomy.category_metadata', []),
			'calorieLevels' => config('menu_taxonomy.calorie_levels', []),
		]);
	}

	public function createPage(): RedirectResponse
	{
		return redirect('/backoffice/daftar_menu?create=1');
	}

	public function storePage(Request $request)
	{
		$this->normalizeIncomingMetadataKeys($request);
		$this->normalizeIncomingCategory($request);
		$validator = Validator::make($request->all(), $this->menuValidationRules(false, true));
		if ($validator->fails()) {
			if ($request->expectsJson() || $request->ajax()) {
				return response()->json([
					'status' => 'error',
					'message' => 'Validation error',
					'errors' => $validator->errors(),
				], 422);
			}

			return redirect('/backoffice/daftar_menu?create=1')
				->withErrors($validator)
				->withInput();
		}

		$validated = $validator->safe()->except('image');
		$validated['stock'] = (int) ($validated['stock'] ?? 0);
		$validated = $this->normalizeMetadataFields($validated);

		$item = $this->menuService->create($validated);

		if ($request->hasFile('image')) {
			$this->menuService->uploadImage($item, $request->file('image'));
		}

		if ($request->expectsJson() || $request->ajax()) {
			$freshItem = $this->menuService->findById((string) $item->_id);

			return response()->json([
				'status' => 'success',
				'message' => 'Menu baru berhasil ditambahkan.',
				'data' => [
					'id' => (string) ($freshItem->_id ?? $item->_id),
					'name' => (string) ($freshItem->name ?? $item->name ?? ''),
					'category' => (string) ($freshItem->category ?? $item->category ?? ''),
					'price' => (float) ($freshItem->price ?? $item->price ?? 0),
					'stock' => (int) ($freshItem->stock ?? $item->stock ?? 0),
					'imageUrl' => (string) ($freshItem->image_url ?? $item->image_url ?? ''),
				],
			]);
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

	public function updatePage(Request $request, string $id)
	{
		$this->normalizeIncomingMetadataKeys($request);
		$this->normalizeIncomingCategory($request);
		$item = $this->menuService->findById($id);

		if (!$item) {
			abort(404);
		}

		$validator = Validator::make($request->all(), $this->menuValidationRules(false, true, true));

		if ($validator->fails()) {
			if ($request->expectsJson() || $request->ajax()) {
				return response()->json([
					'status' => 'error',
					'message' => 'Validation error',
					'errors' => $validator->errors(),
				], 422);
			}

			return redirect('/backoffice/daftar_menu?edit=' . urlencode($id))
				->withErrors($validator)
				->withInput();
		}

		$validated = $validator->safe()->except(['image', 'remove_image']);
		$validated['stock'] = (int) ($validated['stock'] ?? 0);
		$validated = $this->normalizeMetadataFields($validated);
		$removeImage = $request->boolean('remove_image');

		$this->menuService->update($item, $validated);

		if ($request->hasFile('image')) {
			$this->menuService->uploadImage($item, $request->file('image'));
		} elseif ($removeImage && $item->image_url) {
			$this->menuService->deleteImage($item);
		}

		if ($request->expectsJson() || $request->ajax()) {
			$freshItem = $this->menuService->findById((string) $item->_id);

			return response()->json([
				'status' => 'success',
				'message' => 'Menu berhasil diperbarui.',
				'data' => [
					'id' => (string) ($freshItem->_id ?? $item->_id),
					'name' => (string) ($freshItem->name ?? $item->name ?? ''),
					'category' => (string) ($freshItem->category ?? $item->category ?? ''),
					'price' => (float) ($freshItem->price ?? $item->price ?? 0),
					'stock' => (int) ($freshItem->stock ?? $item->stock ?? 0),
					'imageUrl' => (string) ($freshItem->image_url ?? $item->image_url ?? ''),
				],
			]);
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
		$this->normalizeIncomingMetadataKeys($request);
		$this->normalizeIncomingCategory($request);
		$validator = Validator::make($request->all(), $this->menuValidationRules(false, false));

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$validated = $validator->validated();
		$validated['stock'] = (int) ($validated['stock'] ?? 0);
		$validated = $this->normalizeMetadataFields($validated);
		$item = $this->menuService->create($validated);

		return response()->json([
			'status' => 'success',
			'message' => 'Menu item created',
			'data' => $item
		], 201);
	}

	public function update(Request $request, $id)
	{
		$this->normalizeIncomingMetadataKeys($request);
		$this->normalizeIncomingCategory($request);
		$item = $this->menuService->findById((string) $id);

		if (!$item) {
			return response()->json([
				'status' => 'error',
				'message' => 'Menu item not found'
			], 404);
		}

		$validator = Validator::make($request->all(), $this->menuValidationRules(true, false));

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$validated = $validator->validated();
		$validated = $this->normalizeMetadataFields($validated);

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

	private function menuValidationRules(bool $isPartialUpdate = false, bool $isBackofficePage = false, bool $allowRemoveImage = false): array
	{
		$categoryTagMap = (array) config('menu_taxonomy.category_tags', []);
		$allowedTags = collect($categoryTagMap)->flatten()->unique()->values()->all();
		$calorieLevels = config('menu_taxonomy.calorie_levels', []);

		$requiredPrefix = $isPartialUpdate ? 'sometimes|required' : 'required';
		$nullablePrefix = $isPartialUpdate ? 'sometimes|nullable' : 'nullable';
		$stockPrefix = $isPartialUpdate ? 'sometimes|required' : ($isBackofficePage ? 'required' : 'sometimes');

		$rules = [
			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
			'price' => 'required|numeric|min:0',
			'stock' => 'required|integer|min:0',
			'category' => 'required|string|in:' . implode(',', $this->allowedCategories),
			'image_url' => 'nullable|string|max:500',
			'tags' => ['sometimes', 'array', 'min:1', 'max:25'],
			'tags.*' => 'string|in:' . implode(',', $allowedTags),
			'spice_level' => $nullablePrefix . '|integer|between:0,5',
			'sweet_level' => $nullablePrefix . '|integer|between:0,5',
			'fresh_level' => $nullablePrefix . '|integer|between:0,5',
			'calorie_level' => $nullablePrefix . '|string|in:' . implode(',', $calorieLevels),
			'recommendation_note' => $nullablePrefix . '|string|max:500',
		];

		if ($isBackofficePage) {
			$rules['name'] = $requiredPrefix . '|string|max:255';
			$rules['description'] = 'nullable|string';
			$rules['price'] = $requiredPrefix . '|numeric|min:0';
			$rules['stock'] = $stockPrefix . '|integer|min:0';
			$rules['category'] = $requiredPrefix . '|string|in:' . implode(',', $this->allowedCategories);
			$rules['image'] = 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048';
		} else {
			$rules['name'] = $requiredPrefix . '|string|max:255';
			$rules['description'] = $nullablePrefix . '|string';
			$rules['price'] = $requiredPrefix . '|numeric|min:0';
			$rules['stock'] = $stockPrefix . '|integer|min:0';
			$rules['category'] = $requiredPrefix . '|string|in:' . implode(',', $this->allowedCategories);
			$rules['image_url'] = $nullablePrefix . '|string|max:500';
		}

		if ($allowRemoveImage) {
			$rules['remove_image'] = 'nullable|boolean';
		}

		$rules['tags'][] = function (string $attribute, mixed $value, \Closure $fail) use ($categoryTagMap) {
			$category = strtolower(trim((string) request()->input('category', '')));
			$allowedForCategory = (array) ($categoryTagMap[$category] ?? []);
			$selected = is_array($value) ? $value : [];

			foreach ($selected as $tag) {
				$normalizedTag = trim((string) $tag);
				if ($normalizedTag !== '' && !in_array($normalizedTag, $allowedForCategory, true)) {
					$fail('Tag "' . $normalizedTag . '" tidak valid untuk kategori ' . $category . '.');
					return;
				}
			}
		};

		return $rules;
	}

	private function normalizeMetadataFields(array $validated): array
	{
		if (array_key_exists('calorie_level', $validated) && $validated['calorie_level'] !== null && $validated['calorie_level'] !== '') {
			$validated['calorie_level'] = strtolower((string) $validated['calorie_level']);
		}

		if (array_key_exists('tags', $validated)) {
			$validated['tags'] = collect((array) $validated['tags'])
				->map(fn ($value) => trim((string) $value))
				->filter(fn ($value) => $value !== '')
				->unique()
				->values()
				->all();
		}

		if (array_key_exists('spice_level', $validated) && $validated['spice_level'] !== null && $validated['spice_level'] !== '') {
			$validated['spice_level'] = (int) $validated['spice_level'];
		}

		if (array_key_exists('sweet_level', $validated) && $validated['sweet_level'] !== null && $validated['sweet_level'] !== '') {
			$validated['sweet_level'] = (int) $validated['sweet_level'];
		}

		if (array_key_exists('fresh_level', $validated) && $validated['fresh_level'] !== null && $validated['fresh_level'] !== '') {
			$validated['fresh_level'] = (int) $validated['fresh_level'];
		}

		$category = strtolower(trim((string) ($validated['category'] ?? '')));
		if ($category === 'minuman') {
			$validated['spice_level'] = null;
		} else {
			$validated['fresh_level'] = null;
		}

		return $validated;
	}

	private function normalizeIncomingCategory(Request $request): void
	{
		$rawCategory = trim((string) $request->input('category', ''));
		if ($rawCategory === '') {
			return;
		}

		$normalized = strtolower(str_replace('_', ' ', $rawCategory));
		if (in_array($normalized, $this->allowedCategories, true)) {
			$request->merge(['category' => $normalized]);
		}
	}

	private function normalizeIncomingMetadataKeys(Request $request): void
	{
		$camelToSnake = [
			'spicyLevel' => 'spice_level',
			'sweetLevel' => 'sweet_level',
			'freshLevel' => 'fresh_level',
			'calorieLevel' => 'calorie_level',
		];

		$merged = [];
		foreach ($camelToSnake as $camel => $snake) {
			if ($request->has($camel) && !$request->has($snake)) {
				$merged[$snake] = $request->input($camel);
			}
		}

		if ($request->has('calorie_level')) {
			$merged['calorie_level'] = strtolower(trim((string) $request->input('calorie_level')));
		}

		if (!empty($merged)) {
			$request->merge($merged);
		}
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
