<?php

namespace Tests\Feature;

use App\Domains\Chatbot\Services\ChatbotService;
use App\Models\User;
use Mockery;
use Tests\TestCase;

class ChatbotMessageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_chatbot_message_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/chatbot/message', [
            'message' => 'halo',
        ]);

        $response->assertStatus(401);
    }

    public function test_chatbot_message_returns_validation_error_for_invalid_payload(): void
    {
        $this->withoutMiddleware();

        $response = $this->postJson('/api/v1/chatbot/message', [
            'message' => str_repeat('a', 501),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Validation error');
    }

    public function test_chatbot_message_returns_structured_response(): void
    {
        $this->withoutMiddleware();
        $this->actingAs(new User([
            '_id' => 'test-user-id',
            'username' => 'tester',
            'email' => 'tester@example.com',
            'name' => 'Tester',
            'role' => 'CUSTOMER',
        ]), 'api');

        $expected = [
            'reply' => 'Halo! Ada yang bisa saya bantu?',
            'intent' => 'greeting',
            'data' => null,
            'actions' => [
                ['type' => 'quick_reply', 'label' => 'Pesan Makanan', 'value' => 'greeting_order'],
            ],
        ];

        $mock = Mockery::mock(ChatbotService::class);
        $mock->shouldReceive('handleMessage')
            ->once()
            ->andReturn($expected);
        $this->app->instance(ChatbotService::class, $mock);

        $response = $this->postJson('/api/v1/chatbot/message', [
            'message' => 'halo',
            'action' => '',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Chatbot response generated')
            ->assertJsonPath('data.reply', $expected['reply'])
            ->assertJsonPath('data.intent', $expected['intent'])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'reply',
                    'intent',
                    'data',
                    'actions',
                ],
            ]);
    }
}
