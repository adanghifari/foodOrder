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
    public function list()
    {
        $items = MenuItem::orderBy('_id', 'asc')->get();
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
            'price' => 'required|numeric',
            'category' => 'required|string|max:255',
            'image_url' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $item = MenuItem::create($request->all());

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
            'price' => 'sometimes|required|numeric',
            'category' => 'sometimes|required|string|max:255',
            'image_url' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $item->update($request->all());

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
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('image')) {
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
}
