<?php

namespace Tests\Feature;

use App\Subscription\Models\Subscription;
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

    public function test_user_can_have_multiple_active_subscriptions(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $subscriptions = [
            Subscription::query()->create($this->subscriptionData([
                'name' => 'Basic',
                'is_active' => true,
            ])),
            Subscription::query()->create($this->subscriptionData([
                'name' => 'Pro',
                'is_active' => true,
            ])),
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Subscribed',
                'last_name' => 'User',
                'email' => 'subscribed@example.com',
                'password' => 'password',
                'subscription_ids' => collect($subscriptions)->pluck('id')->all(),
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.subscriptions')
            ->assertJsonCount(2, 'data.active_subscriptions');

        $userId = $response->json('data.id');

        foreach ($subscriptions as $subscription) {
            $this->assertDatabaseHas('subscription_user', [
                'subscription_id' => $subscription->id,
                'user_id' => $userId,
            ]);
        }
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

    public function test_user_with_manage_right_can_sync_subscriptions_through_dedicated_route(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create();
        $oldSubscription = Subscription::query()->create($this->subscriptionData([
            'name' => 'Legacy',
            'is_active' => true,
        ]));
        $newSubscription = Subscription::query()->create($this->subscriptionData([
            'name' => 'Fresh',
            'is_active' => true,
        ]));

        $user->subscriptions()->attach($oldSubscription);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/users/subscription/{$user->id}", [
                'subscription_ids' => [$newSubscription->id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.subscriptions')
            ->assertJsonPath('data.subscriptions.0.id', $newSubscription->id)
            ->assertJsonCount(1, 'data.active_subscriptions');

        $this->assertDatabaseHas('subscription_user', [
            'subscription_id' => $newSubscription->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('subscription_user', [
            'subscription_id' => $oldSubscription->id,
            'user_id' => $user->id,
        ]);
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

    private function subscriptionData(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Test subscription',
            'price' => 99.99,
            'currency' => 'EUR',
            'billing_interval' => 'monthly',
            'duration_days' => null,
            'trial_days' => 14,
            'max_users' => 25,
            'is_active' => true,
        ], $overrides);
    }
}
