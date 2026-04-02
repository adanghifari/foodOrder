<?php

namespace App\Domains\Menu\Services;

use App\Models\MenuItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MenuService
{
    public function create(array $validated): MenuItem
    {
        return MenuItem::create($validated);
    }

    public function findById(string $id): ?MenuItem
    {
        return MenuItem::find($id);
    }

    public function update(MenuItem $item, array $validated): MenuItem
    {
        $item->update($validated);
        return $item;
    }

    public function remove(MenuItem $item): void
    {
        $this->deleteStoredMenuImage($item->image_url);
        $item->delete();
    }

    public function uploadImage(MenuItem $item, UploadedFile $image): array
    {
        $this->deleteStoredMenuImage($item->image_url);

        $path = $image->store('menu', 'public');
        $imageUrl = '/storage/' . $path;

        $item->update(['image_url' => $imageUrl]);

        return [
            'image_url' => $imageUrl,
            'item' => $item,
        ];
    }

    public function deleteImage(MenuItem $item): void
    {
        $this->deleteStoredMenuImage($item->image_url);
        $item->update(['image_url' => null]);
    }

    public function count(): int
    {
        return MenuItem::count();
    }

    public function listPaginated(int $perPage)
    {
        return MenuItem::orderBy('_id', 'asc')->paginate($perPage);
    }

    public function listAll()
    {
        return MenuItem::orderBy('_id', 'asc')->get();
    }

    public function searchByName(string $name)
    {
        return MenuItem::where('name', 'like', "%{$name}%")
            ->orderBy('_id', 'asc')
            ->get();
    }

    public function filterByCategory(string $category)
    {
        return MenuItem::where('category', $category)
            ->orderBy('_id', 'asc')
            ->get();
    }

    private function deleteStoredMenuImage(?string $imageUrl): void
    {
        if (!$imageUrl) {
            return;
        }

        if (!str_starts_with($imageUrl, '/storage/menu/')) {
            return;
        }

        $oldPath = ltrim(str_replace('/storage/', '', $imageUrl), '/');
        Storage::disk('public')->delete($oldPath);
    }
}
