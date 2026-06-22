<?php

namespace Tests\Unit;

use App\Domains\Chatbot\Services\ChatbotIntentService;
use PHPUnit\Framework\TestCase;

class ChatbotIntentServiceTest extends TestCase
{
    public function test_detects_greeting_intent(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('halo');

        $this->assertSame('SMALL_TALK_RESTAURANT', $result['intent']);
    }

    public function test_detects_order_intent_and_extracts_menu_and_quantity(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('saya mau pesan ayam geprek 2');

        $this->assertSame('ADD_TO_CART', $result['intent']);
        $this->assertSame('ayam geprek', $result['entities']['menu_name']);
        $this->assertSame(2, $result['entities']['quantity']);
    }

    public function test_detects_order_intent_for_direct_menu_plus_quantity(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('bakwan jagung 3');

        $this->assertSame('ADD_TO_CART', $result['intent']);
        $this->assertSame('bakwan jagung', $result['entities']['menu_name']);
        $this->assertSame(3, $result['entities']['quantity']);
    }

    public function test_detects_tracking_intent(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('pesanan saya sampai mana?');

        $this->assertSame('CHECK_ORDER', $result['intent']);
    }

    public function test_detects_best_seller_intent(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('menu best seller apa?');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
    }

    public function test_detects_recommendation_with_spicy_and_cheap(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('menu pedas murah apa?');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('spicy', $result['entities']['taste']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_detects_recommendation_for_cheapest_phrase(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('makanan yang paling murah apa?');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_maps_quick_reply_quantity_action_to_order_intent(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('', 'qty_3:abc123');

        $this->assertSame('ADD_TO_CART', $result['intent']);
        $this->assertSame(3, $result['entities']['quantity']);
        $this->assertSame('abc123', $result['entities']['menu_id']);
    }

    public function test_maps_confirm_cancel_action(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('', 'confirm_cancel:order001');

        $this->assertSame('confirm_cancel', $result['intent']);
        $this->assertSame('order001', $result['entities']['order_id']);
    }

    public function test_maps_checkout_type_action(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('', 'checkout_type:pickup');

        $this->assertSame('checkout_type_select', $result['intent']);
        $this->assertSame('pickup', $result['entities']['checkout_type']);
    }

    public function test_detects_recommendation_around_price_for_makanan(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('rekomendasi menu makanan yang harganya sekitar 20000');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('makanan utama', $result['entities']['category']);
        $this->assertSame('around', $result['entities']['price_mode']);
        $this->assertSame(20000, $result['entities']['target_price']);
        $this->assertSame(16000, $result['entities']['min_price']);
        $this->assertSame(24000, $result['entities']['max_price']);
    }

    public function test_detects_recommendation_max_price_for_makanan(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('rekomendasi menu makanan yang harga maksimalnya 20000');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('makanan utama', $result['entities']['category']);
        $this->assertSame('max', $result['entities']['price_mode']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_detects_minuman_fresh_under_budget(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('ada minuman segar di bawah 15000');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('minuman', $result['entities']['category']);
        $this->assertSame('fresh', $result['entities']['taste']);
        $this->assertSame('max', $result['entities']['price_mode']);
        $this->assertSame(15000, $result['entities']['max_price']);
    }

    public function test_detects_recommendation_price_range(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('makanan 15rb sampe 25rb ada apa aja');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('makanan utama', $result['entities']['category']);
        $this->assertSame('range', $result['entities']['price_mode']);
        $this->assertSame(15000, $result['entities']['min_price']);
        $this->assertSame(25000, $result['entities']['max_price']);
    }

    public function test_maps_recommend_relax_price_action_to_recommendation_intent(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('', 'recommend_relax_price:MAKANAN_UTAMA:15000:25000');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('makanan utama', $result['entities']['category']);
        $this->assertSame('range', $result['entities']['price_mode']);
        $this->assertSame(15000, $result['entities']['min_price']);
        $this->assertSame(25000, $result['entities']['max_price']);
    }

    public function test_maps_recommend_nearest_price_action_to_recommendation_intent(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('', 'recommend_nearest_price:MINUMAN:15000:25000');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('minuman', $result['entities']['category']);
        $this->assertSame('around', $result['entities']['price_mode']);
        $this->assertSame(15000, $result['entities']['min_price']);
        $this->assertSame(25000, $result['entities']['max_price']);
        $this->assertSame(20000, $result['entities']['target_price']);
    }

    public function test_detects_minuman_fresh_with_max_price_natural_phrase(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('kalau minuman yang menyegarkan dengan rentang harga tidak lebih dari 20000');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('minuman', $result['entities']['category']);
        $this->assertSame('max', $result['entities']['price_mode']);
        $this->assertSame(20000, $result['entities']['max_price']);
        $this->assertSame('fresh', $result['entities']['taste']);
    }

    public function test_detects_minuman_fresh_with_range_price(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('kalau minuman yang menyegarkan dengan harga maksimal 15rb sampai 20rb');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('minuman', $result['entities']['category']);
        $this->assertSame('range', $result['entities']['price_mode']);
        $this->assertSame(15000, $result['entities']['min_price']);
        $this->assertSame(20000, $result['entities']['max_price']);
        $this->assertSame('fresh', $result['entities']['taste']);
    }

    public function test_detects_makanan_pedas_dan_asin_preference(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('kalau makanan pedas dan asin?');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('makanan utama', $result['entities']['category']);
        $this->assertSame('spicy', $result['entities']['taste']);
        $this->assertContains('asin', $result['entities']['preferred_tags']);
    }

    public function test_kurang_dari_is_recommendation_not_cart_decrease(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('ada makanan yang harganya kurang dari 20000?');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_mau_with_price_signal_is_recommendation(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('saya mau yang harganya ga lebih dari 20rb');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_menu_di_bawah_price_is_recommendation(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('menu yang harganya di bawah 20 ribu');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('max', $result['entities']['price_mode']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_ada_harga_number_is_recommendation(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('ada yang harganya 15 ribu?');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame(15000, $result['entities']['max_price']);
    }

    public function test_ada_menu_di_bawah_is_recommendation(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('ada menu di bawah 20000?');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
    }

    public function test_ada_mi_ayam_ga_is_order_menu(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('ada mi ayam ga?');

        $this->assertSame('ASK_MENU_DETAIL', $result['intent']);
        $this->assertSame('mi ayam', $result['entities']['menu_name']);
        $this->assertNull($result['entities']['quantity']);
    }

    public function test_lapar_budget_is_recommendation(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('lapar tapi budget cuma 20rb');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_cariin_makanan_murah_is_recommendation(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('cariin makanan murah dong di bawah 20rb');

        $this->assertSame('RECOMMEND_MENU', $result['intent']);
        $this->assertSame('makanan utama', $result['entities']['category']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_kurangi_still_works_for_cart_decrease(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('kurangi ayam geprek');

        $this->assertSame('REMOVE_FROM_CART', $result['intent']);
    }

    public function test_detects_kosongkan_keranjang_as_clear_cart(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('kosongkan keranjang');

        $this->assertSame('REMOVE_FROM_CART', $result['intent']);
        $this->assertTrue($result['criteria']['clear_cart'] ?? false);
    }

    public function test_detects_kosongkan_word_as_clear_cart(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('kosongkan');

        $this->assertSame('REMOVE_FROM_CART', $result['intent']);
        $this->assertTrue($result['criteria']['clear_cart'] ?? false);
    }

    public function test_detects_hapus_semua_as_clear_cart(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('hapus semua');

        $this->assertSame('REMOVE_FROM_CART', $result['intent']);
        $this->assertTrue($result['criteria']['clear_cart'] ?? false);
    }

    public function test_detects_semuanya_as_unknown_intent_for_contextual_handling(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('semuanya');

        $this->assertSame('unknown_or_ambiguous', $result['intent']);
    }

    public function test_detects_single_hapus_as_remove_from_cart_without_clear(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('hapus');

        $this->assertSame('REMOVE_FROM_CART', $result['intent']);
        $this->assertFalse($result['criteria']['clear_cart'] ?? false);
    }
}
