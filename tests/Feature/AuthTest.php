<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_kasir_and_returns_token(): void
    {
        $res = $this->postJson('/api/register', [
            'name' => 'Kasir Baru', 'email' => 'baru@test.com',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ]);

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'kasir')
            ->assertJsonStructure(['data' => ['token', 'token_type']]);
    }

    public function test_register_validation_error_is_consistent(): void
    {
        $this->postJson('/api/register', ['email' => 'bukan-email'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['name', 'email', 'password']]);
    }

    public function test_login_and_access_protected_endpoint_with_bearer_token(): void
    {
        User::factory()->create(['email' => 'k@test.com', 'password' => 'rahasia123']);

        $token = $this->postJson('/api/login', ['email' => 'k@test.com', 'password' => 'rahasia123'])
            ->assertOk()->json('data.token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'k@test.com');
    }

    public function test_wrong_password_returns_401(): void
    {
        User::factory()->create(['email' => 'k@test.com']);
        $this->postJson('/api/login', ['email' => 'k@test.com', 'password' => 'salah'])
            ->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'off@test.com', 'password' => 'rahasia123']);
        $this->postJson('/api/login', ['email' => 'off@test.com', 'password' => 'rahasia123'])->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/products')->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
