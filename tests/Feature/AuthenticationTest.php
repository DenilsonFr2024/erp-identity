<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Role;
use App\Models\User;
use App\Models\UserApplication;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY_SECRET = 'test-gateway-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gateway.secret' => self::GATEWAY_SECRET]);

        // Garantir que as aplicações e roles base existem (via seeder)
        $this->seed(\Database\Seeders\IdentitySeeder::class);
    }

    private function createInternalUser(string $username = 'admin.teste', string $email = 'admin@teste.com'): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('Password-Segura-2026'),
            'account_type' => 'internal',
            'status' => 'active',
        ]);

        $role = Role::where('name', 'ulimbo_admin')->first();
        UserRole::create([
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
        ]);

        $app = Application::where('code', 'erp')->first();
        UserApplication::create([
            'user_id' => $user->user_id,
            'application_id' => $app->application_id,
        ]);

        return $user;
    }

    private function withGatewayHeaders(): array
    {
        return [
            'X-Gateway-Secret' => self::GATEWAY_SECRET,
            'X-Request-Id' => 'test-request-id',
        ];
    }

    public function test_user_can_login_with_username(): void
    {
        $user = $this->createInternalUser('admin.login', 'admin.login@teste.com');

        $response = $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/login', [
                'login' => 'admin.login',
                'password' => 'Password-Segura-2026',
                'device_name' => 'phpunit',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'admin.login')
            ->assertJsonPath('data.applications.0', 'erp')
            ->assertJsonPath('data.roles.0', 'ulimbo_admin')
            ->assertJsonStructure([
                'data' => ['user', 'applications', 'roles', 'token', 'token_type'],
            ]);
    }

    public function test_user_can_login_with_email(): void
    {
        $this->createInternalUser('admin.email', 'admin.email@teste.com');

        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/login', [
                'login' => 'admin.email@teste.com',
                'password' => 'Password-Segura-2026',
                'device_name' => 'phpunit',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.username', 'admin.email');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createInternalUser();

        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/login', [
                'login' => 'admin.teste',
                'password' => 'palavra-passe-errada',
                'device_name' => 'phpunit',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_rejects_pending_user(): void
    {
        $user = User::create([
            'username' => 'cliente.pendente',
            'email' => 'cliente.pendente@teste.com',
            'password' => Hash::make('Password-Segura-2026'),
            'account_type' => 'client',
            'status' => 'pending_approval',
        ]);

        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/login', [
                'login' => 'cliente.pendente',
                'password' => 'Password-Segura-2026',
                'device_name' => 'phpunit',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_NOT_ACTIVE');
    }

    public function test_login_validates_required_fields(): void
    {
        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login', 'password', 'device_name']);
    }

    public function test_login_requires_gateway_secret(): void
    {
        // Não configurar o secret -> middleware deve bloquear
        config(['services.gateway.secret' => null]);

        $this->postJson('/api/login', [
            'login' => 'admin.teste',
            'password' => 'Password-Segura-2026',
            'device_name' => 'phpunit',
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_user_route_rejects_requests_without_token(): void
    {
        $this->withHeaders($this->withGatewayHeaders())
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createInternalUser('admin.logout', 'admin.logout@teste.com');
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeaders($this->withGatewayHeaders())
            ->withToken($token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
