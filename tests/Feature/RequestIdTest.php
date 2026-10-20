<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    use RefreshDatabase;

    private function loginPayload(): array
    {
        User::factory()->create([
            'email' => 'utilizador@ulimbotech.com',
            'password' => 'Password-Segura-2026',
        ]);

        return [
            'email' => 'utilizador@ulimbotech.com',
            'password' => 'Password-Segura-2026',
            'device_name' => 'Teste',
        ];
    }

    public function test_valid_request_id_is_preserved_in_header_and_meta(): void
    {
        $this->withHeader('X-Request-ID', 'GATEWAY-REQ-001')
            ->postJson('/api/login', $this->loginPayload())
            ->assertOk()
            ->assertHeader('X-Request-ID', 'GATEWAY-REQ-001')
            ->assertJsonPath('meta.request_id', 'GATEWAY-REQ-001');
    }

    public function test_invalid_request_id_is_replaced_by_uuid(): void
    {
        $response = $this->withHeader('X-Request-ID', 'invalido com espacos!')
            ->postJson('/api/login', $this->loginPayload())
            ->assertOk();

        $this->assertNotSame('invalido com espacos!', $response->headers->get('X-Request-ID'));
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',
            $response->json('meta.request_id')
        );
    }

    public function test_missing_request_id_is_generated(): void
    {
        $response = $this->postJson('/api/login', $this->loginPayload())->assertOk();

        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $response->json('meta.request_id')
        );
    }

    public function test_request_id_is_returned_on_error_responses(): void
    {
        $this->withHeader('X-Request-ID', 'ERR-REQ-002')
            ->getJson('/api/user')
            ->assertUnauthorized()
            ->assertHeader('X-Request-ID', 'ERR-REQ-002');
    }
}
