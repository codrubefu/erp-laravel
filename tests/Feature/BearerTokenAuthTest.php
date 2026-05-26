<?php

namespace Tests\Feature;

use App\Users\Models\Organization;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BearerTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_a_bearer_token(): void
    {
        $organization = Organization::query()->create(['name' => 'Login Org', 'slug' => 'login-org']);
        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'first_name', 'last_name', 'phone', 'active', 'email'],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_protected_routes_require_a_bearer_token(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_user_can_access_profile_with_bearer_token(): void
    {
        $organization = Organization::query()->create(['name' => 'Profile Org', 'slug' => 'profile-org']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'user@example.com');
    }

    public function test_logout_revokes_the_current_bearer_token(): void
    {
        $organization = Organization::query()->create(['name' => 'Logout Org', 'slug' => 'logout-org']);
        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_login_uses_the_selected_organization(): void
    {
        $firstOrganization = Organization::query()->create(['name' => 'First Login Org', 'slug' => 'first-login-org']);
        $secondOrganization = Organization::query()->create(['name' => 'Second Login Org', 'slug' => 'second-login-org']);
        User::factory()->create([
            'organization_id' => $firstOrganization->id,
            'email' => 'shared@example.com',
            'password' => 'first-password',
        ]);
        $secondUser = User::factory()->create([
            'organization_id' => $secondOrganization->id,
            'email' => 'shared@example.com',
            'password' => 'second-password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'shared@example.com',
            'organization_id' => $secondOrganization->id,
            'password' => 'second-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $secondUser->id);

        $this->postJson('/api/login', [
            'email' => 'shared@example.com',
            'organization_id' => $firstOrganization->id,
            'password' => 'second-password',
        ])
            ->assertUnauthorized();
    }
}
