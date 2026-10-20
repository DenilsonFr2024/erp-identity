<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_access_protected_route_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'utilizador@ulimbotech.com',
            'password' => 'Password-Segura-2026',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'utilizador@ulimbotech.com',
            'password' => 'Password-Segura-2026',
            'device_name' => 'Teste Web',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => ['user', 'token', 'token_type'],
                'meta' => ['timestamp'],
            ]);

        $token = $response->json('data.token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'utilizador@ulimbotech.com',
            'password' => 'Password-Segura-2026',
        ]);

        $this->postJson('/api/login', [
            'email' => 'utilizador@ulimbotech.com',
            'password' => 'palavra-passe-errada',
            'device_name' => 'Teste Web',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
                'device_name',
            ]);
    }

    public function test_user_route_rejects_requests_without_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('Teste Logout')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
