<?php

namespace Tests\Feature;

use App\Subscription\Models\Subscription;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_and_view_subscriptions(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['subscriptions.view']);

        $subscription = Subscription::query()->create($this->subscriptionData([
            'name' => 'Basic',
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/subscriptions')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Basic']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Basic');
    }

    public function test_user_with_create_right_can_create_subscription(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['subscriptions.create']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions', $this->subscriptionData([
                'name' => 'Pro',
                'price' => 49.99,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pro')
            ->assertJsonPath('data.currency', 'EUR');

        $this->assertDatabaseHas('subscriptions', [
            'name' => 'Pro',
            'price' => 49.99,
        ]);
    }

    public function test_user_with_update_right_can_update_and_toggle_subscription(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['subscriptions.update']);
        $subscription = Subscription::query()->create($this->subscriptionData([
            'name' => 'Starter',
            'is_active' => true,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/subscriptions/{$subscription->id}", [
                'price' => 19.99,
            ])
            ->assertOk()
            ->assertJsonPath('data.price', '19.99');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/subscriptions/{$subscription->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_user_with_delete_and_restore_rights_can_delete_and_restore_subscription(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['subscriptions.delete', 'subscriptions.restore']);
        $subscription = Subscription::query()->create($this->subscriptionData([
            'name' => 'Legacy',
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/subscriptions/{$subscription->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('subscriptions', ['id' => $subscription->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/subscriptions/{$subscription->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Legacy')
            ->assertJsonPath('data.deleted_at', null);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'deleted_at' => null,
        ]);
    }

    public function test_manage_right_allows_all_subscription_actions(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['subscriptions.manage']);
        $subscription = Subscription::query()->create($this->subscriptionData());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/subscriptions')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/subscriptions/{$subscription->id}", [
                'name' => 'Managed',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Managed');
    }

    public function test_user_without_create_right_cannot_create_subscription(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['subscriptions.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions', $this->subscriptionData())
            ->assertForbidden();
    }

    public function test_subscription_validation_requires_valid_payload(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['subscriptions.create']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions', [
                'name' => '',
                'price' => -1,
                'currency' => 'EURO',
                'billing_interval' => 'weekly',
                'duration_days' => 0,
                'trial_days' => -1,
                'max_users' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'price',
                'currency',
                'billing_interval',
                'duration_days',
                'trial_days',
                'max_users',
            ]);
    }

    private function subscriptionData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Enterprise',
            'description' => 'Enterprise subscription',
            'price' => 99.99,
            'currency' => 'EUR',
            'billing_interval' => 'monthly',
            'duration_days' => null,
            'trial_days' => 14,
            'max_users' => 25,
            'is_active' => true,
        ], $overrides);
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
