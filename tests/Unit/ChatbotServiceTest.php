<?php

namespace Tests\Unit;

use App\Domains\Cart\Services\CartService;
use App\Domains\Chatbot\Services\ChatbotIntentService;
use App\Domains\Chatbot\Services\ChatbotService;
use App\Domains\Chatbot\Services\GeminiNluService;
use App\Domains\Payment\Services\PaymentService;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\TestCase;

class ChatbotServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_confirm_cancel_rejects_paid_order(): void
    {
        $intent = Mockery::mock(ChatbotIntentService::class);
        $intent->shouldReceive('detect')
            ->once()
            ->with('batal', 'confirm_cancel:order-paid-1')
            ->andReturn([
                'intent' => 'confirm_cancel',
                'entities' => ['order_id' => 'order-paid-1'],
            ]);

        $cart = Mockery::mock(CartService::class);
        $payment = Mockery::mock(PaymentService::class);
        $payment->shouldNotReceive('cancelTransaction');
        $payment->shouldNotReceive('cancelPendingOrderLocally');

        $orderAlias = Mockery::mock('alias:App\Models\Order');
        $orderQuery = Mockery::mock();
        $orderAlias->shouldReceive('where')->once()->with('_id', 'order-paid-1')->andReturn($orderQuery);
        $orderQuery->shouldReceive('where')->once()->with('customer_id', 'user-1')->andReturn($orderQuery);
        $orderQuery->shouldReceive('first')->once()->andReturn((object) [
            '_id' => 'order-paid-1',
            'payment_status' => 'PAID',
            'midtrans_order_id' => 'ORDER-order-paid-1-123',
        ]);

        $service = new ChatbotService($intent, $cart, $payment);
        $user = new User([
            'username' => 'tester',
            'email' => 'tester@example.com',
            'name' => 'Tester',
            'role' => 'CUSTOMER',
        ]);
        $user->setAttribute('_id', 'user-1');

        $response = $service->handleMessage($user, 'batal', 'confirm_cancel:order-paid-1');

        $this->assertSame('confirm_cancel', $response['intent']);
        $this->assertStringContainsString('sudah lunas', $response['reply']);
        $this->assertSame('PAID', $response['data']['payment_status']);
    }

    public function test_confirm_cancel_allows_unpaid_order(): void
    {
        $intent = Mockery::mock(ChatbotIntentService::class);
        $intent->shouldReceive('detect')
            ->once()
            ->with('batal', 'confirm_cancel:order-unpaid-1')
            ->andReturn([
                'intent' => 'confirm_cancel',
                'entities' => ['order_id' => 'order-unpaid-1'],
            ]);

        $cart = Mockery::mock(CartService::class);
        $payment = Mockery::mock(PaymentService::class);
        $payment->shouldReceive('cancelTransaction')
            ->once()
            ->with('ORDER-order-unpaid-1-123')
            ->andReturn([
                'ok' => true,
                'message' => 'Transaction canceled',
                'data' => [
                    'order_id' => 'order-unpaid-1',
                    'payment_status' => 'CANCELED',
                ],
            ]);

        $orderAlias = Mockery::mock('alias:App\Models\Order');
        $orderQuery = Mockery::mock();
        $orderAlias->shouldReceive('where')->once()->with('_id', 'order-unpaid-1')->andReturn($orderQuery);
        $orderQuery->shouldReceive('where')->once()->with('customer_id', 'user-1')->andReturn($orderQuery);
        $orderQuery->shouldReceive('first')->once()->andReturn((object) [
            '_id' => 'order-unpaid-1',
            'payment_status' => 'PENDING',
            'midtrans_order_id' => 'ORDER-order-unpaid-1-123',
        ]);

        $service = new ChatbotService($intent, $cart, $payment);
        $user = new User([
            'username' => 'tester',
            'email' => 'tester@example.com',
            'name' => 'Tester',
            'role' => 'CUSTOMER',
        ]);
        $user->setAttribute('_id', 'user-1');

        $response = $service->handleMessage($user, 'batal', 'confirm_cancel:order-unpaid-1');

        $this->assertSame('confirm_cancel', $response['intent']);
        $this->assertSame('Transaction canceled', $response['reply']);
        $this->assertSame('CANCELED', $response['data']['payment_status']);
    }

    public function test_unknown_intent_uses_gemini_when_confidence_high(): void
    {
        $intent = Mockery::mock(ChatbotIntentService::class);
        $intent->shouldReceive('detect')
            ->once()
            ->with('yang pedes murah ada ga?', '')
            ->andReturn([
                'intent' => 'unknown_or_ambiguous',
                'entities' => [],
            ]);

        $cart = Mockery::mock(CartService::class);
        $payment = Mockery::mock(PaymentService::class);
        $gemini = Mockery::mock(GeminiNluService::class);
        $gemini->shouldReceive('detectIntent')
            ->once()
            ->with('yang pedes murah ada ga?')
            ->andReturn([
                'intent' => 'greeting',
                'confidence' => 0.9,
                'entities' => [],
            ]);

        $service = new ChatbotService($intent, $cart, $payment, $gemini);
        $user = new User([
            'username' => 'tester',
            'email' => 'tester@example.com',
            'name' => 'Tester',
            'role' => 'CUSTOMER',
        ]);
        $user->setAttribute('_id', 'user-1');

        $response = $service->handleMessage($user, 'yang pedes murah ada ga?', '');

        $this->assertSame('greeting', $response['intent']);
    }

    public function test_unknown_intent_stays_fallback_when_gemini_confidence_low(): void
    {
        $intent = Mockery::mock(ChatbotIntentService::class);
        $intent->shouldReceive('detect')
            ->once()
            ->with('yang pedes murah ada ga?', '')
            ->andReturn([
                'intent' => 'unknown_or_ambiguous',
                'entities' => [],
            ]);

        $cart = Mockery::mock(CartService::class);
        $payment = Mockery::mock(PaymentService::class);
        $gemini = Mockery::mock(GeminiNluService::class);
        $gemini->shouldReceive('detectIntent')
            ->once()
            ->with('yang pedes murah ada ga?')
            ->andReturn([
                'intent' => 'menu_recommendation',
                'confidence' => 0.2,
                'entities' => ['taste' => 'spicy', 'max_price' => 20000],
            ]);

        $service = new ChatbotService($intent, $cart, $payment, $gemini);
        $user = new User([
            'username' => 'tester',
            'email' => 'tester@example.com',
            'name' => 'Tester',
            'role' => 'CUSTOMER',
        ]);
        $user->setAttribute('_id', 'user-1');

        $response = $service->handleMessage($user, 'yang pedes murah ada ga?', '');

        $this->assertSame('unknown_or_ambiguous', $response['intent']);
    }
}
