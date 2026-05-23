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

        $this->assertSame('greeting', $result['intent']);
    }

    public function test_detects_order_intent_and_extracts_menu_and_quantity(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('saya mau pesan ayam geprek 2');

        $this->assertSame('order_menu', $result['intent']);
        $this->assertSame('ayam geprek', $result['entities']['menu_name']);
        $this->assertSame(2, $result['entities']['quantity']);
    }

    public function test_detects_order_intent_for_direct_menu_plus_quantity(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('bakwan jagung 3');

        $this->assertSame('order_menu', $result['intent']);
        $this->assertSame('bakwan jagung', $result['entities']['menu_name']);
        $this->assertSame(3, $result['entities']['quantity']);
    }

    public function test_detects_tracking_intent(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('pesanan saya sampai mana?');

        $this->assertSame('tracking_order', $result['intent']);
    }

    public function test_detects_recommendation_with_spicy_and_cheap(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('menu pedas murah apa?');

        $this->assertSame('menu_recommendation', $result['intent']);
        $this->assertSame('spicy', $result['entities']['taste']);
        $this->assertSame(20000, $result['entities']['max_price']);
    }

    public function test_maps_quick_reply_quantity_action_to_order_intent(): void
    {
        $service = new ChatbotIntentService();
        $result = $service->detect('', 'qty_3:abc123');

        $this->assertSame('order_menu', $result['intent']);
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
}
