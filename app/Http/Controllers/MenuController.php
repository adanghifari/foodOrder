<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    /**
     * List all menu items.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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

        $items = MenuItem::orderBy('_id', 'asc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Menu items retrieved',
            'data' => $items
        ]);
    }

    /**
     * Search menu items by name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $name = $request->query('name');

        if (!$name || trim($name) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Search query cannot be empty'
            ], 422);
        }
        
        $items = MenuItem::where('name', 'like', "%{$name}%")
            ->orderBy('_id', 'asc')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'message' => 'Menu items retrieved',
            'data' => $items
        ]);
    }

    /**
     * Filter menu items by category.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filter(Request $request)
    {
        $category = $request->query('category');

        if (!$category || trim($category) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Category query cannot be empty'
            ], 422);
        }
        
        $items = MenuItem::where('category', $category)
            ->orderBy('_id', 'asc')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'message' => 'Menu items retrieved',
            'data' => $items
        ]);
    }

    /**
     * Create a new menu item.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:255',
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

        $item = MenuItem::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Menu item created',
            'data' => $item
        ], 201);
    }

    /**
     * Update an existing menu item.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $item = MenuItem::find($id);
        
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
            'category' => 'sometimes|required|string|max:255',
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

        $item->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Menu item updated',
            'data' => $item
        ]);
    }

    /**
     * Remove a menu item.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove($id)
    {
        $item = MenuItem::find($id);
        
        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Menu item not found'
            ], 404);
        }

        $this->deleteStoredMenuImage($item->image_url);

        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Menu item deleted',
            'data' => ['deleted' => true]
        ]);
    }

    /**
     * Upload an image for a menu item.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(Request $request, $id)
    {
        $item = MenuItem::find($id);
        
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
            
            $this->deleteStoredMenuImage($item->image_url);
            
            // Ensure public disk is configured correctly or use standard storage
            $path = $request->file('image')->store('menu', 'public');
            $imageUrl = '/storage/' . $path;

            $item->update(['image_url' => $imageUrl]);

            return response()->json([
                'status' => 'success',
                'message' => 'Menu image uploaded',
                'data' => [
                    'image_url' => $imageUrl,
                    'item' => $item
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
        $item = MenuItem::find($id);

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

        $this->deleteStoredMenuImage($item->image_url);

        $item->update(['image_url' => null]);

        return response()->json([
            'status' => 'success',
            'message' => 'Menu image deleted',
            'data' => ['deleted' => true]
        ]);
    }

    /**
     * Get the count of menu items.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function count()
    {
        $count = MenuItem::count();
        return response()->json([
            'status' => 'success',
            'message' => 'Menu count retrieved',
            'data' => ['count' => $count]
        ]);
    }

    public function hidangan()
    {
        $menus = MenuItem::where('category', 'Hidangan')->get();
        return view('menu', ['menus' => $menus]);
    }

    public function cemilan()
    {
        $menus = MenuItem::where('category', 'Cemilan')->get();
        return view('menu', ['menus' => $menus]);
    }

    public function minuman()
    {
        $menus = MenuItem::where('category', 'Minuman')->get();
        return view('menu', ['menus' => $menus]);
    }

    private function deleteStoredMenuImage(?string $imageUrl): void
    {
        if (!$imageUrl) {
            return;
        }

        // Only allow deletion under the expected menu storage directory.
        if (!str_starts_with($imageUrl, '/storage/menu/')) {
            return;
        }

        $oldPath = ltrim(str_replace('/storage/', '', $imageUrl), '/');
        Storage::disk('public')->delete($oldPath);
    }

}
