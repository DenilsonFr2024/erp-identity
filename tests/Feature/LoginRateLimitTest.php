<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $password = 'errada')
    {
        return $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'Teste',
        ]);
    }

    public function test_sixth_login_attempt_for_same_email_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'a@ulimbotech.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('a@ulimbotech.com')->assertStatus(401);
        }

        $this->withHeader('X-Request-ID', 'RL-001')
            ->postJson('/api/login', [
                'email' => 'a@ulimbotech.com',
                'password' => 'errada',
                'device_name' => 'Teste',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
            ->assertJsonPath('meta.request_id', 'RL-001');
    }

    public function test_rate_limit_is_per_email_not_global(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->attempt('a@ulimbotech.com')->assertStatus(401);
        }

        $this->attempt('a@ulimbotech.com')->assertStatus(429);
        $this->attempt('b@ulimbotech.com')->assertStatus(401);
    }

    public function test_successful_login_still_works_within_limit(): void
    {
        User::factory()->create([
            'email' => 'ok@ulimbotech.com',
            'password' => 'Password-Segura-2026',
        ]);

        $this->attempt('ok@ulimbotech.com', 'Password-Segura-2026')->assertOk();
    }
}
