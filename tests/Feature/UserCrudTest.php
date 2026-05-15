<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_users(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['id' => $admin->id])
            ->assertJsonFragment(['email' => 'jane@example.com']);
    }

    public function test_user_with_manage_right_can_create_user_with_groups(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $group = Group::query()->create([
            'name' => 'staff',
            'label' => 'Staff',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'New',
                'last_name' => 'User',
                'phone' => '+15550001111',
                'active' => true,
                'email' => 'new@example.com',
                'password' => 'password',
                'group_ids' => [$group->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.groups.0.id', $group->id);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id]);
    }

    public function test_user_with_manage_right_can_update_user(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/{$user->id}", [
                'first_name' => 'Updated',
                'email' => 'updated@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.email', 'updated@example.com');
    }

    public function test_user_with_manage_right_can_delete_user(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_without_manage_right_cannot_create_user(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'email' => 'blocked@example.com',
                'password' => 'password',
            ])
            ->assertForbidden();
    }

    private function authenticatedUserWithRights(array $rightNames): array
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Test Group',
        ]);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->create([
                'name' => $rightName,
                'label' => $rightName,
            ]);
            $group->rights()->attach($right);
        }

        $user->groups()->attach($group);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        return [$user, $token];
    }
}
