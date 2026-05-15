<?php

namespace App\Domains\Menu\Services;

use App\Models\MenuItem;
use App\Models\Order;
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

    public function topByCategory(): array
    {
        $orders = Order::whereIn('payment_status', ['PAID', 'SUCCESS'])
            ->whereNull('order_deleted_at')
            ->get(['items']);

        $quantityByMenuId = [];
        foreach ($orders as $order) {
            foreach ((array) $order->items as $item) {
                $menuId = (string) (is_array($item) ? ($item['menu_id'] ?? '') : ($item->menu_id ?? ''));
                if ($menuId === '') {
                    continue;
                }

                if (!isset($quantityByMenuId[$menuId])) {
                    $quantityByMenuId[$menuId] = 0;
                }
                $quantityByMenuId[$menuId]++;
            }
        }

        $menus = MenuItem::orderBy('_id', 'asc')->get();
        $grouped = [
            'makanan utama' => [],
            'cemilan' => [],
            'minuman' => [],
        ];

        foreach ($menus as $menu) {
            $category = strtolower((string) ($menu->category ?? ''));
            if (!array_key_exists($category, $grouped)) {
                continue;
            }

            $menuId = (string) $menu->_id;
            $grouped[$category][] = [
                '_id' => $menuId,
                'name' => (string) ($menu->name ?? ''),
                'description' => (string) ($menu->description ?? ''),
                'price' => (float) ($menu->price ?? 0),
                'stock' => (int) ($menu->stock ?? 0),
                'category' => (string) ($menu->category ?? ''),
                'image_url' => (string) ($menu->image_url ?? ''),
                'total_ordered' => (int) ($quantityByMenuId[$menuId] ?? 0),
            ];
        }

        $result = [];
        foreach ($grouped as $category => $items) {
            usort($items, fn (array $a, array $b) => $b['total_ordered'] <=> $a['total_ordered']);
            if (count($items) > 0) {
                $result[] = [
                    'category' => $category,
                    'item' => $items[0],
                ];
            }
        }

        return $result;
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
