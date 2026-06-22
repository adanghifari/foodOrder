<?php

namespace Tests\Feature;

use App\Domains\Chatbot\Services\ChatbotService;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatbotIntegrationTest extends TestCase
{
    private User $user;
    private MenuItem $mieAyam;
    private MenuItem $esJeruk;
    private ChatbotService $chatbotService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use MongoDB connection for tests
        config(['database.default' => 'mongodb']);
        
        // Clean up test data if left over
        User::where('email', 'test_customer@example.com')->delete();
        MenuItem::whereIn('name', ['Mie Ayam', 'Es Jeruk'])->delete();

        // 1. Create a customer user
        $this->user = User::create([
            'username' => 'test_customer',
            'email' => 'test_customer@example.com',
            'password' => bcrypt('password123'),
            'name' => 'Test Customer',
            'role' => 'CUSTOMER',
        ]);

        CartItem::where('customer_id', (string) $this->user->_id)->delete();

        // 2. Create MenuItem records
        $this->mieAyam = MenuItem::create([
            'name' => 'Mie Ayam',
            'category' => 'makanan utama',
            'price' => 15000,
            'stock' => 10,
        ]);

        $this->esJeruk = MenuItem::create([
            'name' => 'Es Jeruk',
            'category' => 'minuman',
            'price' => 5000,
            'stock' => 10,
        ]);

        // Resolve ChatbotService from container
        $this->chatbotService = $this->app->make(ChatbotService::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->user)) {
            CartItem::where('customer_id', (string) $this->user->_id)->delete();
            $this->user->delete();
        }
        if (isset($this->mieAyam)) {
            $this->mieAyam->delete();
        }
        if (isset($this->esJeruk)) {
            $this->esJeruk->delete();
        }

        parent::tearDown();
    }

    public function test_hapus_request_with_one_item_in_cart_prompts_selection(): void
    {
        // Add single item to cart
        CartItem::create([
            'customer_id' => (string) $this->user->_id,
            'menu_item_id' => (string) $this->mieAyam->_id,
            'quantity' => 2,
        ]);

        // Request deletion without specifying menu
        $response = $this->chatbotService->handleMessage($this->user, 'hapus');

        // It should ask which menu to remove instead of deleting directly
        $this->assertSame('cart_decrease_qty', $response['intent']);
        $this->assertSame('Mau hapus menu yang mana?', $response['reply']);
        
        // It should display the cart item as a quick reply
        $this->assertCount(1, $response['actions']);
        $this->assertSame('Mie Ayam', $response['actions'][0]['label']);
        $this->assertSame('cart_decrease:' . $this->mieAyam->_id . ':1', $response['actions'][0]['value']);

        // Cache should hold the pending selection context
        $contextKey = 'chatbot:context:' . $this->user->_id;
        $context = Cache::get($contextKey);
        $this->assertNotNull($context);
        $this->assertSame('CART_ADJUST_SELECTION', $context['pendingAction']);
    }

    public function test_hapus_request_with_multiple_items_prompts_selection(): void
    {
        // Add two items
        CartItem::create([
            'customer_id' => (string) $this->user->_id,
            'menu_item_id' => (string) $this->mieAyam->_id,
            'quantity' => 1,
        ]);
        CartItem::create([
            'customer_id' => (string) $this->user->_id,
            'menu_item_id' => (string) $this->esJeruk->_id,
            'quantity' => 1,
        ]);

        $response = $this->chatbotService->handleMessage($this->user, 'kurangi');

        $this->assertSame('cart_decrease_qty', $response['intent']);
        $this->assertStringContainsString('Di keranjang kamu ada lebih dari satu menu', $response['reply']);
        $this->assertCount(2, $response['actions']);
    }

    public function test_semuanya_reply_during_pending_adjust_selection_clears_cart(): void
    {
        // Add two items
        CartItem::create([
            'customer_id' => (string) $this->user->_id,
            'menu_item_id' => (string) $this->mieAyam->_id,
            'quantity' => 1,
        ]);
        CartItem::create([
            'customer_id' => (string) $this->user->_id,
            'menu_item_id' => (string) $this->esJeruk->_id,
            'quantity' => 1,
        ]);

        // Manually place in CART_ADJUST_SELECTION context
        $contextKey = 'chatbot:context:' . $this->user->_id;
        Cache::put($contextKey, [
            'lastIntent' => 'REMOVE_FROM_CART',
            'pendingAction' => 'CART_ADJUST_SELECTION',
            'cart_adjust_operation' => 'decrease',
            'cart_adjust_delta' => 1
        ], 900);

        // User says "semuanya" to confirm clearing
        $response = $this->chatbotService->handleMessage($this->user, 'semuanya');

        // It should empty the cart
        $this->assertSame('clear_cart_request', $response['intent']);
        $this->assertSame('Keranjang berhasil dikosongkan.', $response['reply']);

        // Cart items should be deleted
        $this->assertCount(0, CartItem::where('customer_id', (string) $this->user->_id)->get());

        // Context should be forgotten
        $this->assertNull(Cache::get($contextKey));
    }

    public function test_kosongkan_input_clears_cart_directly(): void
    {
        CartItem::create([
            'customer_id' => (string) $this->user->_id,
            'menu_item_id' => (string) $this->mieAyam->_id,
            'quantity' => 1,
        ]);

        // Direct request
        $response = $this->chatbotService->handleMessage($this->user, 'kosongkan keranjag');

        $this->assertSame('clear_cart_request', $response['intent']);
        $this->assertSame('Keranjang berhasil dikosongkan.', $response['reply']);
        $this->assertCount(0, CartItem::where('customer_id', (string) $this->user->_id)->get());
    }

    public function test_hapus_specific_menu_removes_it_directly_without_prompt(): void
    {
        CartItem::create([
            'customer_id' => (string) $this->user->_id,
            'menu_item_id' => (string) $this->mieAyam->_id,
            'quantity' => 1,
        ]);

        // Direct specific request
        $response = $this->chatbotService->handleMessage($this->user, 'hapus mie ayam');

        $this->assertSame('cart_decrease_qty', $response['intent']);
        $this->assertStringContainsString('Mie Ayam dihapus dari keranjang', $response['reply']);
        $this->assertCount(0, CartItem::where('customer_id', (string) $this->user->_id)->get());
    }

    public function test_cough_throat_issues_context_excludes_spicy_menu(): void
    {
        $this->mieAyam->update(['tags' => ['berkuah', 'hangat']]);

        // Create a spicy menu item
        $spicyGeprek = MenuItem::create([
            'name' => 'Nasi Ayam Geprek Joss',
            'category' => 'makanan utama',
            'price' => 20000,
            'stock' => 10,
            'spice_level' => 4,
            'tags' => ['pedas'],
        ]);

        // Send a recommendation query about batuk
        $response = $this->chatbotService->handleMessage($this->user, 'makanannya yang cocok saat batuk apa');

        // Clean up
        $spicyGeprek->delete();

        // Assert that the spicy geprek is NOT in the recommendations/cards
        $this->assertSame('RECOMMEND_MENU', $response['intent']);
        $this->assertSame('recommendation', $response['result_mode']);
        $this->assertTrue($response['limit_applied']);
        
        $recNames = collect($response['recommendations'])->pluck('name')->toArray();
        $this->assertNotContains('Nasi Ayam Geprek Joss', $recNames);
        
        // Assert that Mie Ayam (which is non-spicy) is in recommendations
        $this->assertContains('Mie Ayam', $recNames);
    }

    public function test_recommendations_and_cards_are_filtered_by_gemini_reply(): void
    {
        // Create additional menu item
        $kakap = MenuItem::create([
            'name' => 'Nasi Kakap Bakar',
            'category' => 'makanan utama',
            'price' => 48000,
            'stock' => 10,
        ]);

        // Mock GeminiNluService
        $mockGemini = $this->createMock(\App\Domains\Chatbot\Services\GeminiNluService::class);
        $mockGemini->method('generateNaturalResponse')
            ->willReturn('Kamu bisa coba Nasi Kakap Bakar yang lezat.');
        
        $this->app->instance(\App\Domains\Chatbot\Services\GeminiNluService::class, $mockGemini);

        // Re-resolve the service to load mock
        $chatbotService = $this->app->make(ChatbotService::class);

        // Call handles message (should trigger RECOMMEND_MENU with category Makanan)
        $response = $chatbotService->handleMessage($this->user, 'rekomendasi makanan');

        // Clean up
        $kakap->delete();

        // Assert reply and cards
        $this->assertSame('RECOMMEND_MENU', $response['intent']);
        $this->assertStringContainsString('Nasi Kakap Bakar yang lezat', $response['reply']);
        
        // Assert only Nasi Kakap Bakar is returned in recommendations and cards (Mie Ayam is filtered out because it is not in the text response!)
        $recNames = collect($response['recommendations'])->pluck('name')->toArray();
        $this->assertContains('Nasi Kakap Bakar', $recNames);
        $this->assertNotContains('Mie Ayam', $recNames);
        
        $cardNames = collect($response['cards'])->pluck('menu.menu_name')->toArray();
        $this->assertContains('Nasi Kakap Bakar', $cardNames);
        $this->assertNotContains('Mie Ayam', $cardNames);
    }

    public function test_tampilkan_semua_returns_all_items_without_limit(): void
    {
        // Create 5 cemilan items
        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $items[] = MenuItem::create([
                'name' => 'Cemilan ' . $i,
                'category' => 'cemilan',
                'price' => 5000 + $i,
                'stock' => 10,
            ]);
        }

        // Call handleMessage
        $response = $this->chatbotService->handleMessage($this->user, 'tampilkan semua cemilan');

        // Clean up
        foreach ($items as $item) {
            $item->delete();
        }

        // Assert response mode and limit_applied
        $this->assertSame('ASK_CATEGORY', $response['intent']);
        $this->assertSame('list_all', $response['result_mode']);
        $this->assertFalse($response['limit_applied']);
        
        // Assert it returned all 5 items
        $recNames = collect($response['recommendations'])->pluck('name')->toArray();
        $this->assertCount(5, $recNames);
    }
}
