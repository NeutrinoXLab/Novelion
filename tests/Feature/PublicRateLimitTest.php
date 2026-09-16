<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_chat_send_is_limited_after_ten_requests_per_minute(): void
    {
        for ($request = 1; $request <= 10; $request++) {
            $this->postJson(route('chat.send'), ['message' => 'Test message'])
                ->assertOk();
        }

        $this->postJson(route('chat.send'), ['message' => 'Test message'])
            ->assertStatus(429);
    }

    public function test_public_newsletter_subscribe_is_limited_after_five_requests_per_minute(): void
    {
        for ($request = 1; $request <= 5; $request++) {
            $this->post(route('newsletter.subscribe'), [
                'email' => 'subscriber-'.$request.'@example.test',
            ])->assertRedirect();
        }

        $this->post(route('newsletter.subscribe'), [
            'email' => 'subscriber-6@example.test',
        ])->assertStatus(429);
    }
}
