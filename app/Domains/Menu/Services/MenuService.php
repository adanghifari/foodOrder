<?php

namespace App\Domains\Menu\Services;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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

        $imageUrl = $this->isCloudinaryConfigured()
            ? $this->uploadToCloudinary($image)
            : $this->uploadToLocalStorage($image);

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
        $supportedCategories = ['makanan utama', 'cemilan', 'minuman'];

        $baseQuery = Order::whereIn('payment_status', ['PAID', 'SUCCESS'])
            ->whereNull('order_deleted_at');

        // Limit dataset to keep request time stable on large collections.
        $orders = (clone $baseQuery)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('_id', 'desc')
            ->limit(500)
            ->get(['items']);

        if ($orders->isEmpty()) {
            $orders = (clone $baseQuery)
                ->orderBy('_id', 'desc')
                ->limit(500)
                ->get(['items']);
        }

        $quantityByMenuId = [];
        foreach ($orders as $order) {
            foreach ((array) $order->items as $item) {
                $menuId = (string) (is_array($item) ? ($item['menu_id'] ?? '') : ($item->menu_id ?? ''));
                if ($menuId === '') {
                    continue;
                }

                $qty = is_array($item)
                    ? (int) ($item['quantity'] ?? 1)
                    : (int) ($item->quantity ?? 1);
                if ($qty < 1) {
                    $qty = 1;
                }

                if (!isset($quantityByMenuId[$menuId])) {
                    $quantityByMenuId[$menuId] = 0;
                }
                $quantityByMenuId[$menuId] += $qty;
            }
        }

        $menus = MenuItem::whereIn('category', $supportedCategories)
            ->orderBy('_id', 'asc')
            ->get(['name', 'description', 'price', 'stock', 'category', 'image_url']);
        $grouped = array_fill_keys($supportedCategories, []);

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

        if ($this->isCloudinaryUrl($imageUrl)) {
            $this->deleteFromCloudinary($imageUrl);
            return;
        }

        if (!str_starts_with($imageUrl, '/storage/menu/')) {
            return;
        }

        $oldPath = ltrim(str_replace('/storage/', '', $imageUrl), '/');
        Storage::disk('public')->delete($oldPath);
    }

    private function uploadToLocalStorage(UploadedFile $image): string
    {
        $path = $image->store('menu', 'public');
        return '/storage/' . $path;
    }

    private function isCloudinaryConfigured(): bool
    {
        return !empty(config('services.cloudinary.cloud_name'))
            && !empty(config('services.cloudinary.api_key'))
            && !empty(config('services.cloudinary.api_secret'));
    }

    private function uploadToCloudinary(UploadedFile $image): string
    {
        $cloudName = (string) config('services.cloudinary.cloud_name');
        $apiKey = (string) config('services.cloudinary.api_key');
        $apiSecret = (string) config('services.cloudinary.api_secret');
        $folder = (string) config('services.cloudinary.folder', 'kedaiklik/menu');
        $timestamp = time();

        $paramsToSign = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        $signature = $this->generateCloudinarySignature($paramsToSign, $apiSecret);

        $response = Http::timeout(30)
            ->asMultipart()
            ->attach('file', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                'api_key' => $apiKey,
                'timestamp' => $timestamp,
                'folder' => $folder,
                'signature' => $signature,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Upload gambar ke Cloudinary gagal: ' . $response->body());
        }

        $secureUrl = (string) $response->json('secure_url', '');
        if ($secureUrl === '') {
            throw new RuntimeException('Upload gambar ke Cloudinary gagal: secure_url kosong.');
        }

        return $secureUrl;
    }

    private function isCloudinaryUrl(string $imageUrl): bool
    {
        return str_contains($imageUrl, 'res.cloudinary.com');
    }

    private function deleteFromCloudinary(string $imageUrl): void
    {
        if (!$this->isCloudinaryConfigured()) {
            return;
        }

        $publicId = $this->extractCloudinaryPublicId($imageUrl);
        if ($publicId === null) {
            return;
        }

        $cloudName = (string) config('services.cloudinary.cloud_name');
        $apiKey = (string) config('services.cloudinary.api_key');
        $apiSecret = (string) config('services.cloudinary.api_secret');
        $timestamp = time();

        $paramsToSign = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $signature = $this->generateCloudinarySignature($paramsToSign, $apiSecret);

        $response = Http::timeout(20)
            ->asForm()
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'api_key' => $apiKey,
                'timestamp' => $timestamp,
                'public_id' => $publicId,
                'signature' => $signature,
            ]);

        if (!$response->successful()) {
            Log::warning('Cloudinary image destroy failed', [
                'public_id' => $publicId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function extractCloudinaryPublicId(string $imageUrl): ?string
    {
        $path = parse_url($imageUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $marker = '/image/upload/';
        $pos = strpos($path, $marker);
        if ($pos === false) {
            return null;
        }

        $resource = substr($path, $pos + strlen($marker));
        if ($resource === '') {
            return null;
        }

        $parts = explode('/', $resource);
        if (!empty($parts) && preg_match('/^v\d+$/', $parts[0])) {
            array_shift($parts);
        }

        $publicPath = implode('/', $parts);
        if ($publicPath === '') {
            return null;
        }

        $publicPath = rawurldecode($publicPath);
        return preg_replace('/\.[^.]+$/', '', $publicPath) ?: null;
    }

    private function generateCloudinarySignature(array $params, string $apiSecret): string
    {
        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return sha1(implode('&', $parts) . $apiSecret);
    }
}
