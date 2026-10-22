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

class RefreshTokenTest extends TestCase
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

    private function createActiveUser(string $username = 'refresh.teste'): array
    {
        $user = User::create([
            'username' => $username,
            'email' => $username . '@teste.com',
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

        $token = $user->createToken('phpunit')->plainTextToken;

        return [$user, $token];
    }

    public function test_refresh_requires_authentication(): void
    {
        $this->withHeaders($this->withGatewayHeaders())
            ->postJson('/api/refresh')
            ->assertUnauthorized();
    }

    public function test_refresh_rotates_token(): void
    {
        [$user, $oldToken] = $this->createActiveUser();

        // 1. Refresh com o token antigo
        $refreshResponse = $this->withHeaders([
            'X-Gateway-Secret' => self::GATEWAY_SECRET,
            'Authorization' => 'Bearer ' . $oldToken,
        ])->postJson('/api/refresh');

        $refreshResponse->assertOk();
        $newToken = $refreshResponse->json('data.token');
        $this->assertNotSame($oldToken, $newToken);

        // 2. Verificar que só existe 1 token na BD
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // 3. Limpar a autenticação em cache
        $this->app['auth']->forgetGuards();

        // 4. O token antigo já não funciona
        $this->flushHeaders();
        $this->withHeaders([
            'X-Gateway-Secret' => self::GATEWAY_SECRET,
            'Authorization' => 'Bearer ' . $oldToken,
        ])->getJson('/api/user')->assertUnauthorized();

        // 5. O novo token funciona
        $this->flushHeaders();
        $this->withHeaders([
            'X-Gateway-Secret' => self::GATEWAY_SECRET,
            'Authorization' => 'Bearer ' . $newToken,
        ])->getJson('/api/user')->assertOk();
    }
    public function test_refresh_with_invalid_token_is_rejected(): void
    {
        $this->withHeaders($this->withGatewayHeaders())
            ->withToken('invalid-token')
            ->postJson('/api/refresh')
            ->assertUnauthorized();
    }
}
