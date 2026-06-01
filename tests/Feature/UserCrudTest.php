<?php

namespace Tests\Feature;

use App\Subscription\Models\Subscription;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_user_with_locations_only_sees_users_from_shared_locations(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $allowedLocation = Location::query()->create([
            'name' => 'HQ',
            'description' => 'Main office',
        ]);
        $otherLocation = Location::query()->create([
            'name' => 'Remote',
            'description' => 'Remote office',
        ]);
        $visibleUser = User::factory()->create([
            'first_name' => 'Visible',
            'email' => 'visible@example.com',
        ]);
        $hiddenUser = User::factory()->create([
            'first_name' => 'Hidden',
            'email' => 'hidden@example.com',
        ]);

        $admin->locations()->attach($allowedLocation);
        $visibleUser->locations()->attach($allowedLocation);
        $hiddenUser->locations()->attach($otherLocation);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'visible@example.com'])
            ->assertJsonMissing(['email' => 'hidden@example.com']);
    }

    public function test_user_can_search_users_by_partial_user_code(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'user_code' => 'USR11100000000000000000000000001',
            'first_name' => 'Matching',
            'email' => 'matching-code@example.com',
        ]);
        User::factory()->create([
            'user_code' => 'USR22200000000000000000000000002',
            'first_name' => 'Other',
            'email' => 'other-code@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users?search=111')
            ->assertOk()
            ->assertJsonFragment(['email' => 'matching-code@example.com'])
            ->assertJsonMissing(['email' => 'other-code@example.com']);
    }

    public function test_user_code_search_endpoint_only_searches_user_code(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'user_code' => 'USR11100000000000000000000000001',
            'first_name' => 'Matching',
            'email' => 'matching-code@example.com',
        ]);
        User::factory()->create([
            'user_code' => 'USR22200000000000000000000000002',
            'first_name' => '111',
            'email' => '111@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users/search/user-code?search=111')
            ->assertOk()
            ->assertJsonFragment(['email' => 'matching-code@example.com'])
            ->assertJsonMissing(['email' => '111@example.com']);
    }

    public function test_user_code_search_endpoint_ignores_location_scope_and_returns_user_code(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $allowedLocation = Location::query()->create([
            'name' => 'Allowed',
            'description' => 'Allowed location',
        ]);
        $otherLocation = Location::query()->create([
            'name' => 'Other',
            'description' => 'Other location',
        ]);
        $targetUser = User::factory()->create([
            'user_code' => '323',
            'first_name' => 'Mathias',
            'email' => 'mathias323@example.com',
        ]);

        $admin->locations()->attach($allowedLocation);
        $targetUser->locations()->attach($otherLocation);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users/search/user-code?search=323')
            ->assertOk()
            ->assertJsonFragment([
                'email' => 'mathias323@example.com',
                'user_code' => '323',
            ]);
    }

    public function test_clients_endpoint_lists_users_with_only_profile_view_or_no_rights(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);
        $client = User::factory()->create(['email' => 'client@example.com']);
        $administrator = User::factory()->create(['email' => 'administrator@example.com']);
        $withoutRights = User::factory()->create(['email' => 'without-rights@example.com']);

        $this->attachRightsToUser($client, ['profile.view']);
        $this->attachRightsToUser($administrator, ['profile.view', 'users.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients')
            ->assertOk()
            ->assertJsonFragment(['email' => 'client@example.com'])
            ->assertJsonFragment(['email' => 'without-rights@example.com'])
            ->assertJsonMissing(['email' => 'administrator@example.com']);
    }

    public function test_administrators_endpoint_excludes_users_with_only_profile_view_right_and_without_groups(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);
        $client = User::factory()->create(['email' => 'client@example.com']);
        $administrator = User::factory()->create(['email' => 'administrator@example.com']);
        $withoutRights = User::factory()->create(['email' => 'without-rights@example.com']);

        $this->attachRightsToUser($client, ['profile.view']);
        $this->attachRightsToUser($administrator, ['profile.view', 'users.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/administrators')
            ->assertOk()
            ->assertJsonMissing(['email' => 'client@example.com'])
            ->assertJsonFragment(['email' => 'administrator@example.com'])
            ->assertJsonMissing(['email' => 'without-rights@example.com']);
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
                'user_code' => 'USR00000000000000000000000000001',
                'first_name' => 'New',
                'last_name' => 'User',
                'phone' => '+15550001111',
                'active' => true,
                'email' => 'new@example.com',
                'password' => 'password',
                'group_ids' => [$group->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_code', 'USR00000000000000000000000000001')
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.groups.0.id', $group->id);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'user_code' => 'USR00000000000000000000000000001',
        ]);
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id]);
    }

    public function test_user_with_manage_right_can_create_user_without_password(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'No',
                'last_name' => 'Password',
                'email' => 'no-password@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'no-password@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'email' => 'no-password@example.com',
            'password' => null,
        ]);
    }

    public function test_user_email_must_be_unique_within_same_organization(): void
    {
        $organization = Organization::query()->create(['name' => 'Same Org', 'slug' => 'same-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $organization->id]);

        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'shared@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Duplicate',
                'last_name' => 'Email',
                'email' => 'shared@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_email_can_be_reused_in_different_organizations(): void
    {
        $firstOrganization = Organization::query()->create(['name' => 'First Org', 'slug' => 'first-org']);
        $secondOrganization = Organization::query()->create(['name' => 'Second Org', 'slug' => 'second-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $secondOrganization->id]);

        User::factory()->create([
            'organization_id' => $firstOrganization->id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Shared',
                'last_name' => 'Email',
                'email' => 'shared@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'shared@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'organization_id' => $secondOrganization->id,
            'email' => 'shared@example.com',
        ]);
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

    public function test_user_subscription_dates_are_stored_and_expiration_is_calculated(): void
    {
        Carbon::setTestNow('2026-05-18 10:00:00');

        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $subscription = Subscription::query()->create($this->subscriptionData([
            'name' => 'Monthly',
            'duration_days' => 10,
            'is_active' => true,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Subscribed',
                'last_name' => 'History',
                'email' => 'history@example.com',
                'password' => 'password',
                'subscriptions' => [
                    [
                        'id' => $subscription->id,
                        'start_date' => '2026-05-15',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_active_subscription', true)
            ->assertJsonPath('data.subscription_history.0.start_date', '2026-05-15')
            ->assertJsonPath('data.subscription_history.0.expires_at', '2026-05-25')
            ->assertJsonPath('data.subscription_history.0.is_active', true);

        $this->assertDatabaseHas('subscription_user', [
            'subscription_id' => $subscription->id,
            'user_id' => $response->json('data.id'),
            'start_date' => '2026-05-15',
            'expires_at' => '2026-05-25',
        ]);

        Carbon::setTestNow();
    }

    public function test_expired_user_subscription_is_not_marked_active(): void
    {
        Carbon::setTestNow('2026-05-18 10:00:00');

        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $subscription = Subscription::query()->create($this->subscriptionData([
            'name' => 'Expired',
            'duration_days' => 5,
            'is_active' => true,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Expired',
                'last_name' => 'User',
                'email' => 'expired-subscription@example.com',
                'password' => 'password',
                'subscriptions' => [
                    [
                        'id' => $subscription->id,
                        'start_date' => '2026-05-01',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_active_subscription', false)
            ->assertJsonCount(0, 'data.active_subscriptions')
            ->assertJsonPath('data.subscription_history.0.is_active', false);

        Carbon::setTestNow();
    }

    public function test_user_with_manage_right_can_update_user(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/{$user->id}", [
                'user_code' => 'USR00000000000000000000000000002',
                'first_name' => 'Updated',
                'email' => 'updated@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.user_code', 'USR00000000000000000000000000002')
            ->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.email', 'updated@example.com');
    }

    public function test_user_with_locations_cannot_update_user_from_other_location(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $allowedLocation = Location::query()->create([
            'name' => 'Allowed',
            'description' => 'Allowed location',
        ]);
        $blockedLocation = Location::query()->create([
            'name' => 'Blocked',
            'description' => 'Blocked location',
        ]);
        $user = User::factory()->create(['email' => 'blocked-update@example.com']);

        $admin->locations()->attach($allowedLocation);
        $user->locations()->attach($blockedLocation);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/{$user->id}", [
                'first_name' => 'Updated',
            ])
            ->assertNotFound();
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
            ->patchJson("/api/users/subscription/{$user->id}", [
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

        $this->attachRightsToUser($user, $rightNames);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$user, $token];
    }

    private function attachRightsToUser(User $user, array $rightNames): void
    {
        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Test Group',
        ]);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->firstOrCreate([
                'name' => $rightName,
            ], [
                'label' => $rightName,
            ]);
            $group->rights()->attach($right);
        }

        $user->groups()->attach($group);
    }

    private function subscriptionData(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Test subscription',
            'price' => 99.99,
            'currency' => 'EUR',
            'duration_days' => null,
            'max_users' => 25,
            'is_active' => true,
        ], $overrides);
    }
}
