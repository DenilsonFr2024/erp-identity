<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpar o rate limiter entre testes (a cache não é limpa pelo RefreshDatabase)
        Cache::flush();
    }

    private function attempt(string $login, string $password = 'errada')
    {
        return $this->postJson('/api/login', [
            'login' => $login,
            'password' => $password,
            'device_name' => 'Teste',
        ]);
    }

    public function test_sixth_login_attempt_for_same_email_is_rate_limited(): void
    {
        User::factory()->create([
            'username' => 'a.ulimbo',
            'email' => 'a@ulimbotech.com',
            'password' => bcrypt('Password-Segura-2026'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('a@ulimbotech.com')->assertStatus(401);
        }

        $this->withHeader('X-Request-ID', 'RL-001')
            ->postJson('/api/login', [
                'login' => 'a@ulimbotech.com',
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
        User::factory()->create([
            'username' => 'a.ulimbo',
            'email' => 'a@ulimbotech.com',
            'password' => bcrypt('Password-Segura-2026'),
        ]);

        User::factory()->create([
            'username' => 'b.ulimbo',
            'email' => 'b@ulimbotech.com',
            'password' => bcrypt('Password-Segura-2026'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('a@ulimbotech.com')->assertStatus(401);
        }

        // O 6º pedido para 'a' deve ser bloqueado (429)
        $this->attempt('a@ulimbotech.com')->assertStatus(429);

        // Um pedido para 'b' não deve ser afetado (401 = credenciais inválidas, mas não 429)
        $this->attempt('b@ulimbotech.com')->assertStatus(401);
    }

    public function test_successful_login_still_works_within_limit(): void
    {
        User::factory()->create([
            'username' => 'g.ulimbo',
            'email' => 'g@ulimbotech.com',
            'password' => bcrypt('Password-Segura-2026'),
        ]);

        $this->attempt('g@ulimbotech.com', 'Password-Segura-2026')->assertOk();
    }
}
