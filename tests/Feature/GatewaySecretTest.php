<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewaySecretTest extends TestCase
{
    use RefreshDatabase;

    private function login()
    {
        User::factory()->create([
            'email' => 'g@ulimbotech.com',
            'password' => 'Password-Segura-2026',
        ]);

        return $this->postJson('/api/login', [
            'email' => 'g@ulimbotech.com',
            'password' => 'Password-Segura-2026',
            'device_name' => 'Teste',
        ]);
    }

    public function test_request_without_secret_is_forbidden(): void
    {
        $this->flushHeaders();

        $this->login()
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_request_with_wrong_secret_is_forbidden(): void
    {
        $this->withHeader('X-Gateway-Secret', 'errado');

        $this->login()->assertStatus(403);
    }

    public function test_request_with_correct_secret_is_accepted(): void
    {
        $this->login()->assertOk();
    }

    public function test_unconfigured_secret_fails_closed(): void
    {
        config(['services.gateway.secret' => null]);

        $this->login()->assertStatus(403);
    }

    public function test_user_route_also_requires_secret(): void
    {
        $this->getJson('/api/user', ['X-Gateway-Secret' => 'errado'])->assertStatus(403);
    }
}
