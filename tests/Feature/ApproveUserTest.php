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

class ApproveUserTest extends TestCase
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

    private function createAdmin(): array
    {
        $user = User::create([
            'username' => 'admin.aprova',
            'email' => 'admin.aprova@teste.com',
            'password' => Hash::make('Password-Segura-2026'),
            'account_type' => 'internal',
            'status' => 'active',
        ]);

        $role = Role::where('name', 'ulimbo_admin')->first();
        UserRole::create([
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
        ]);

        $token = $user->createToken('phpunit')->plainTextToken;

        return [$user, $token];
    }

    private function createPendingClient(): User
    {
        $user = User::create([
            'username' => 'cliente.pendente',
            'email' => 'cliente.pendente@teste.com',
            'password' => Hash::make('Password-Segura-2026'),
            'account_type' => 'client',
            'status' => 'pending_approval',
        ]);

        $role = Role::where('name', 'client_contact')->first();
        UserRole::create([
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
        ]);

        return $user;
    }

    public function test_approve_requires_authentication(): void
    {
        $this->withHeaders($this->gateway())
            ->postJson('/api/admin/approve-user/qualquer-id')
            ->assertUnauthorized();
    }

    public function test_approve_requires_admin_role(): void
    {
        $client = $this->createPendingClient();
        $token = $client->createToken('phpunit')->plainTextToken;

        $this->withHeaders(array_merge($this->gateway(), [
            'Authorization' => 'Bearer ' . $token,
        ]))
            ->postJson('/api/admin/approve-user/' . $client->user_id)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_approve_activates_pending_user(): void
    {
        [$admin, $adminToken] = $this->createAdmin();
        $client = $this->createPendingClient();

        $this->withHeaders(array_merge($this->gateway(), [
            'Authorization' => 'Bearer ' . $adminToken,
        ]))
            ->postJson('/api/admin/approve-user/' . $client->user_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('users', [
            'user_id' => $client->user_id,
            'status' => 'active',
        ]);
    }

    public function test_approve_returns_404_for_unknown_user(): void
    {
        [$admin, $adminToken] = $this->createAdmin();

        $this->withHeaders(array_merge($this->gateway(), [
            'Authorization' => 'Bearer ' . $adminToken,
        ]))
            ->postJson('/api/admin/approve-user/00000000-0000-0000-0000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'USER_NOT_FOUND');
    }

    public function test_approve_already_active_user_returns_message(): void
    {
        [$admin, $adminToken] = $this->createAdmin();
        $client = $this->createPendingClient();
        $client->update(['status' => 'active']);

        $this->withHeaders(array_merge($this->gateway(), [
            'Authorization' => 'Bearer ' . $adminToken,
        ]))
            ->postJson('/api/admin/approve-user/' . $client->user_id)
            ->assertOk()
            ->assertJsonPath('data.message', 'Utilizador já está ativo.');
    }
}
