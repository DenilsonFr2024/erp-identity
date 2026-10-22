<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY_SECRET = 'test-gateway-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gateway.secret' => self::GATEWAY_SECRET]);
        $this->seed(\Database\Seeders\IdentitySeeder::class);
    }

    private function gateway(): array
    {
        return ['X-Gateway-Secret' => self::GATEWAY_SECRET];
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'reset.teste',
            'email' => 'reset@teste.com',
            'password' => Hash::make('Old-Password-2026'),
            'account_type' => 'internal',
            'status' => 'active',
        ], $overrides));
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        $this->makeUser();

        $this->withHeaders($this->gateway())
            ->postJson('/api/password/forgot', ['email' => 'reset@teste.com'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@teste.com',
        ]);
    }

    public function test_forgot_password_validates_email(): void
    {
        $this->withHeaders($this->gateway())
            ->postJson('/api/password/forgot', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = $this->makeUser();

        $token = Password::createToken($user);

        $this->withHeaders($this->gateway())
            ->postJson('/api/password/reset', [
                'token' => $token,
                'email' => 'reset@teste.com',
                'password' => 'New-Password-2026',
                'password_confirmation' => 'New-Password-2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            Hash::check('New-Password-2026', $user->fresh()->password)
        );
    }

    public function test_reset_password_with_invalid_token_fails(): void
    {
        $this->makeUser();

        $this->withHeaders($this->gateway())
            ->postJson('/api/password/reset', [
                'token' => 'invalid-token',
                'email' => 'reset@teste.com',
                'password' => 'New-Password-2026',
                'password_confirmation' => 'New-Password-2026',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'PASSWORD_RESET_FAILED');
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->withHeaders($this->gateway())
            ->postJson('/api/password/change', [])
            ->assertUnauthorized();
    }

    public function test_change_password_with_valid_current_password(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeaders(array_merge($this->gateway(), [
            'Authorization' => 'Bearer ' . $token,
        ]))
            ->postJson('/api/password/change', [
                'current_password' => 'Old-Password-2026',
                'password' => 'New-Password-2026',
                'password_confirmation' => 'New-Password-2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            Hash::check('New-Password-2026', $user->fresh()->password)
        );

        // Tokens revogados
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_change_password_with_wrong_current_password(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeaders(array_merge($this->gateway(), [
            'Authorization' => 'Bearer ' . $token,
        ]))
            ->postJson('/api/password/change', [
                'current_password' => 'Wrong-Password',
                'password' => 'New-Password-2026',
                'password_confirmation' => 'New-Password-2026',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_CURRENT_PASSWORD');
    }
}
