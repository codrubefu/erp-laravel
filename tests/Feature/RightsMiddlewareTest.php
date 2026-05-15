<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RightsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_required_right_can_access_groups_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $right = Right::query()->create([
            'name' => 'groups.view',
            'label' => 'View groups',
        ]);
        $group = Group::query()->create([
            'name' => 'admin',
            'label' => 'Administrator',
        ]);
        $group->rights()->sync([$right->id]);
        $user->groups()->sync([$group->id]);

        $token = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/groups')
            ->assertOk()
            ->assertJsonPath('groups.0.name', 'admin');
    }

    public function test_user_without_required_right_is_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
        ]);
        $group = Group::query()->create([
            'name' => 'staff',
            'label' => 'Staff',
        ]);
        $user->groups()->sync([$group->id]);

        $token = $this->postJson('/api/login', [
            'email' => 'staff@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/groups')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }
}
