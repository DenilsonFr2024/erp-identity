<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY_SECRET = 'test-gateway-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gateway.secret' => self::GATEWAY_SECRET]);
        $this->seed(\Database\Seeders\IdentitySeeder::class);
    }

    private function withGatewayHeaders(): array
    {
        return ['X-Gateway-Secret' => self::GATEWAY_SECRET];
    }

    private function clientPayload(array $extra = []): array
    {
        return array_merge([
            'username' => 'cliente.teste',
            'email' => 'cliente@teste.ao',
            'password' => 'Password-Segura-2026',
            'password_confirmation' => 'Password-Segura-2026',
            'account_type' => 'client',
            'applications' => ['erp'],
            'role' => 'client_contact',
        ], $extra);
    }

    public function test_register_client_creates_pending_user(): void
    {
        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/register', $this->clientPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.username', 'cliente.teste')
            ->assertJsonPath('data.account_type', 'client')
            ->assertJsonPath('data.status', 'pending_approval');

        $this->assertDatabaseHas('users', [
            'username' => 'cliente.teste',
            'status' => 'pending_approval',
            'account_type' => 'client',
        ]);
    }

    public function test_register_internal_user_is_active(): void
    {
        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/register', $this->clientPayload([
                'username' => 'interno.teste',
                'email' => 'interno@teste.com',
                'account_type' => 'internal',
                'role' => 'ulimbo_admin',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_register_rejects_duplicate_username(): void
    {
        User::create([
            'username' => 'cliente.teste',
            'email' => 'outro@teste.com',
            'password' => bcrypt('x'),
            'account_type' => 'client',
            'status' => 'pending_approval',
        ]);

        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/register', $this->clientPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::create([
            'username' => 'outro.teste',
            'email' => 'cliente@teste.ao',
            'password' => bcrypt('x'),
            'account_type' => 'client',
            'status' => 'pending_approval',
        ]);

        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/register', $this->clientPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_required_fields(): void
    {
        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'username',
                'email',
                'password',
                'account_type',
                'applications',
                'role',
            ]);
    }

    public function test_register_requires_gateway_secret(): void
    {
        config(['services.gateway.secret' => null]);

        $this->postJson('/api/register', $this->clientPayload())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
