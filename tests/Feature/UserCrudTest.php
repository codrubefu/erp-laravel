<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Payments\Models\Payment;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\LocationGroup;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_users(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'organization_id' => $admin->organization_id,
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

    public function test_member_address_is_saved_and_returned(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'address@example.com',
                'address' => 'Str. Exemplu 1, Iasi',
            ])
            ->assertCreated()
            ->assertJsonPath('data.address', 'Str. Exemplu 1, Iasi');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/users/'.$response->json('data.id'), ['address' => 'Str. Noua 2, Iasi'])
            ->assertOk()
            ->assertJsonPath('data.address', 'Str. Noua 2, Iasi');
    }

    public function test_users_are_listed_alphabetically_by_default(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Zoe',
            'last_name' => 'Alpha',
            'email' => 'sort-user-alpha-zoe@example.com',
        ]);
        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Adam',
            'last_name' => 'Bravo',
            'email' => 'sort-user-bravo-adam@example.com',
        ]);
        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Adam',
            'last_name' => 'Alpha',
            'email' => 'sort-user-alpha-adam@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users?search=sort-user')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'sort-user-alpha-adam@example.com')
            ->assertJsonPath('data.1.email', 'sort-user-alpha-zoe@example.com')
            ->assertJsonPath('data.2.email', 'sort-user-bravo-adam@example.com');
    }

    public function test_user_lists_can_be_filtered_by_location_and_location_group(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $north = LocationGroup::query()->create(['name' => 'North', 'organization_id' => $admin->organization_id]);
        $south = LocationGroup::query()->create(['name' => 'South', 'organization_id' => $admin->organization_id]);
        $location = fn (string $name, ?LocationGroup $group) => Location::query()->create([
            'name' => $name,
            'organization_id' => $admin->organization_id,
            'location_group_id' => $group?->id,
        ]);
        $annex = $location('Annex', $north);
        $warehouse = $location('Warehouse', $north);
        $office = $location('Office', $south);
        $depot = $location('Depot', null);
        $client = function (string $lastName, array $locations) use ($admin): void {
            User::factory()
                ->create(['organization_id' => $admin->organization_id, 'first_name' => 'Client', 'last_name' => $lastName])
                ->locations()->sync(collect($locations)->pluck('id'));
        };
        $client('Alpha', [$annex, $office]);
        $client('Beta', [$warehouse]);
        $client('Gamma', [$depot]);
        $client('Delta', []);

        $list = fn (array $query): array => $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients?'.http_build_query(['search' => 'Client', ...$query]))
            ->assertOk()
            ->json('data.*.last_name');

        $this->assertSame(['Alpha', 'Beta', 'Delta', 'Gamma'], $list([]));
        $this->assertSame(['Alpha', 'Beta', 'Delta', 'Gamma'], $list(['location_id' => '', 'location_group_id' => '']));
        $this->assertSame(['Alpha'], $list(['location_id' => $annex->id]));
        $this->assertSame(['Delta'], $list(['location_id' => 'none']));
        $this->assertSame(['Alpha', 'Beta'], $list(['location_group_id' => $north->id]));
        $this->assertSame(['Gamma'], $list(['location_group_id' => 'none']));
        $this->assertSame(['Beta'], $list(['location_group_id' => $north->id, 'location_id' => $warehouse->id]));
        $this->assertSame(['Beta'], $list(['location_group_id' => $north->id, 'search' => 'Bet']));
        // Both filters must match the same location, not two different ones.
        $this->assertSame([], $list(['location_group_id' => $south->id, 'location_id' => $annex->id]));

        foreach (['users', 'administrators'] as $resource) {
            $admin->locations()->sync([$office->id]);
            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson("/api/{$resource}?location_id={$office->id}&search=".urlencode($admin->email))
                ->assertOk()
                ->assertJsonPath('data.*.id', [$admin->id]);
            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson("/api/{$resource}?location_id={$depot->id}&search=".urlencode($admin->email))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_user_list_rejects_invalid_location_filters(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients?location_id=abc&location_group_id=-1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['location_id', 'location_group_id']);
    }

    public function test_user_lists_can_be_filtered_by_active_service(): void
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $service = fn (array $attributes = []): Service => Service::query()->create([
            'organization_id' => $admin->organization_id, 'name' => 'Service', 'price' => 10, 'currency' => 'RON',
            'duration_days' => 30, 'is_active' => true, ...$attributes,
        ]);
        $plain = $service();
        $grace = $service(['grace_period_days' => 3]);
        $limited = $service(['max_accesses' => 2]);
        $disabled = $service(['is_active' => false]);
        $deleted = $service();
        $client = function (string $lastName, ?Service $service = null, array $pivot = []) use ($admin): User {
            $user = User::factory()->create(['organization_id' => $admin->organization_id, 'first_name' => 'Client', 'last_name' => $lastName]);
            if ($service !== null) {
                $user->services()->attach($service->id, ['status' => 'active', 'start_date' => now()->subDays(10), ...$pivot]);
            }

            return $user;
        };
        // Stored statuses are only refreshed when a user is loaded, so some are deliberately stale.
        $client('A current', $plain, ['expires_at' => now()->addDay()]);
        $client('A no expiry', $plain);
        $client('A no start', $plain, ['start_date' => null]);
        $client('A grace boundary', $grace, ['expires_at' => now()->subDays(3)]);
        $client('A accesses left', $limited, ['accesses_used' => 1]);
        $client('A reservation started', $plain, ['status' => 'reserved']);
        $client('A suspension ended', $plain, ['status' => 'suspended', 'resume_at' => now()]);
        $client('A mixed', $plain, ['status' => 'expired'])->services()->attach($limited->id, ['status' => 'active']);
        $client('I none');
        $client('I expired stale', $plain, ['expires_at' => now()->subSecond()]);
        $client('I past grace', $grace, ['expires_at' => now()->subDays(3)->subSecond()]);
        $client('I consumed stale', $limited, ['accesses_used' => 2]);
        $client('I future', $plain, ['status' => 'reserved', 'start_date' => now()->addDay()]);
        $client('I future stale', $plain, ['start_date' => now()->addDay()]);
        $client('I suspended', $plain, ['status' => 'suspended']);
        $client('I suspended later', $plain, ['status' => 'suspended', 'resume_at' => now()->addDay()]);
        $client('I resumed expired', $plain, ['status' => 'suspended', 'resume_at' => now(), 'expires_at' => now()->subDay()]);
        $client('I pending', $plain, ['status' => 'pending']);
        $client('I disabled', $disabled);
        $client('I deleted', $deleted);
        $deleted->delete();

        $list = fn (array $query): TestResponse => $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients?'.http_build_query(['search' => 'Client', 'per_page' => 50, ...$query]))
            ->assertOk();
        $names = fn (array $query): array => $list($query)->json('data.*.last_name');

        // Filter first: listing refreshes the stored statuses.
        $active = $list(['active_service' => '1']);
        $inactive = $list(['active_service' => '0']);

        $this->assertSame([
            'A accesses left', 'A current', 'A grace boundary', 'A mixed', 'A no expiry',
            'A no start', 'A reservation started', 'A suspension ended',
        ], $active->json('data.*.last_name'));
        $this->assertSame(8, $active->json('meta.total'));
        $this->assertSame([
            'I consumed stale', 'I deleted', 'I disabled', 'I expired stale', 'I future', 'I future stale',
            'I none', 'I past grace', 'I pending', 'I resumed expired', 'I suspended', 'I suspended later',
        ], $inactive->json('data.*.last_name'));

        // The filter agrees with the active services shown for each user.
        foreach ($active->json('data') as $user) {
            $this->assertNotEmpty($user['active_services'], $user['last_name']);
        }
        foreach ($inactive->json('data') as $user) {
            $this->assertEmpty($user['active_services'], $user['last_name']);
        }

        $this->assertCount(20, $names([]));
        $this->assertCount(20, $names(['active_service' => '']));
        $this->assertSame(['A mixed'], $names(['active_service' => '1', 'search' => 'mixed']));

        $location = Location::query()->create(['name' => 'Annex', 'organization_id' => $admin->organization_id]);
        $location->users()->sync(User::query()->whereIn('last_name', ['A current', 'I none'])->pluck('id'));
        $this->assertSame(['A current'], $names(['active_service' => '1', 'location_id' => $location->id]));
        $this->assertSame(['I none'], $names(['active_service' => '0', 'location_id' => $location->id]));

        foreach (['users', 'administrators'] as $resource) {
            foreach (['1' => 0, '0' => 1] as $value => $count) {
                $this->withHeader('Authorization', "Bearer {$token}")
                    ->getJson("/api/{$resource}?active_service={$value}&search=".urlencode($admin->email))
                    ->assertOk()
                    ->assertJsonCount($count, 'data');
            }
        }
    }

    public function test_user_list_rejects_invalid_active_service_filter(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients?active_service=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['active_service']);
    }

    public function test_user_lists_can_be_filtered_by_active_status(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $service = Service::query()->create([
            'organization_id' => $admin->organization_id, 'name' => 'Service', 'price' => 10, 'currency' => 'RON', 'is_active' => true,
        ]);
        foreach (['Alpha' => true, 'Beta' => false, 'Delta' => false] as $lastName => $active) {
            $user = User::factory()->create([
                'organization_id' => $admin->organization_id, 'first_name' => 'Client', 'last_name' => $lastName, 'active' => $active,
            ]);
            if ($lastName !== 'Delta') {
                $user->services()->attach($service->id, ['status' => 'active']);
            }
        }

        $names = fn (array $query): array => $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients?'.http_build_query(['search' => 'Client', ...$query]))
            ->assertOk()
            ->json('data.*.last_name');

        $this->assertSame(['Alpha', 'Beta', 'Delta'], $names(['active' => '']));
        $this->assertSame(['Alpha'], $names(['active' => '1']));
        $this->assertSame(['Beta', 'Delta'], $names(['active' => '0']));
        // User status and active service are independent filters.
        $this->assertSame(['Beta'], $names(['active' => '0', 'active_service' => '1']));

        foreach (['users', 'administrators'] as $resource) {
            foreach (['1' => 1, '0' => 0] as $value => $count) {
                $this->withHeader('Authorization', "Bearer {$token}")
                    ->getJson("/api/{$resource}?active={$value}&search=".urlencode($admin->email))
                    ->assertOk()
                    ->assertJsonCount($count, 'data');
            }
        }

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients?active=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['active']);
    }

    public function test_user_location_filters_respect_location_access_and_organization(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $mine = Location::query()->create(['name' => 'Mine', 'organization_id' => $admin->organization_id]);
        $hidden = Location::query()->create(['name' => 'Hidden', 'organization_id' => $admin->organization_id]);
        $other = Organization::factory()->create();
        $foreign = Location::query()->create(['name' => 'Foreign', 'organization_id' => $other->id]);
        $admin->locations()->sync([$mine->id]);
        User::factory()->create(['organization_id' => $admin->organization_id])->locations()->sync([$hidden->id]);
        User::factory()->create(['organization_id' => $other->id])->locations()->sync([$foreign->id]);

        foreach ([$hidden, $foreign] as $location) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson("/api/users?location_id={$location->id}")
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
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
            'organization_id' => $admin->organization_id,
            'first_name' => 'Visible',
            'email' => 'visible@example.com',
        ]);
        $hiddenUser = User::factory()->create([
            'organization_id' => $admin->organization_id,
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
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_code' => 'USR11100000000000000000000000001',
            'first_name' => 'Matching',
            'email' => 'matching-code@example.com',
        ]);
        User::factory()->create([
            'organization_id' => $admin->organization_id,
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
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_code' => 'USR11100000000000000000000000001',
            'first_name' => 'Matching',
            'email' => 'matching-code@example.com',
        ]);
        User::factory()->create([
            'organization_id' => $admin->organization_id,
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
            'organization_id' => $admin->organization_id,
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
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $client = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'client@example.com']);
        $administrator = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'administrator@example.com']);
        $withoutRights = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'without-rights@example.com']);

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
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $client = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'client@example.com']);
        $administrator = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'administrator@example.com']);
        $withoutRights = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'without-rights@example.com']);

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

    public function test_user_can_be_created_without_email_or_phone(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'No',
                'last_name' => 'Contact',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.phone', null);

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'email' => null,
            'phone' => null,
        ]);
    }

    public function test_user_email_and_phone_can_be_cleared_on_update(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'email' => 'clear-me@example.com',
            'phone' => '+40722000099',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/{$user->id}", [
                'email' => null,
                'phone' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.phone', null);
    }

    public function test_user_email_and_phone_can_be_shared_within_same_organization(): void
    {
        $organization = Organization::query()->create(['name' => 'Same Org', 'slug' => 'same-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $organization->id]);

        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'shared@example.com',
            'phone' => '+40722000001',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Duplicate',
                'last_name' => 'Contact',
                'email' => 'shared@example.com',
                'phone' => '+40722000001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'shared@example.com')
            ->assertJsonPath('data.phone', '+40722000001');

        $this->assertSame(2, User::query()
            ->where('organization_id', $organization->id)
            ->where('email', 'shared@example.com')
            ->where('phone', '+40722000001')
            ->count());
        $this->assertNotNull($response->json('data.id'));
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

    public function test_user_code_must_be_unique_within_same_organization(): void
    {
        $organization = Organization::query()->create(['name' => 'Unique Codes Org', 'slug' => 'unique-codes-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $organization->id]);

        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'existing-unique@example.com',
            'user_code' => 'CARD-001',
            'phone' => '+40722000001',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Duplicate',
                'last_name' => 'Identifiers',
                'email' => 'duplicate-identifiers@example.com',
                'user_code' => 'CARD-001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_code'])
            ->assertJsonMissingValidationErrors(['phone']);
    }

    public function test_user_code_and_phone_can_be_reused_in_different_organizations(): void
    {
        $firstOrganization = Organization::query()->create(['name' => 'First Identifiers Org', 'slug' => 'first-identifiers-org']);
        $secondOrganization = Organization::query()->create(['name' => 'Second Identifiers Org', 'slug' => 'second-identifiers-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $secondOrganization->id]);

        User::factory()->create([
            'organization_id' => $firstOrganization->id,
            'email' => 'first-identifiers@example.com',
            'user_code' => 'CARD-001',
            'phone' => '+40722000001',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Shared',
                'last_name' => 'Identifiers',
                'email' => 'second-identifiers@example.com',
                'user_code' => 'CARD-001',
                'phone' => '+40722000001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_code', 'CARD-001')
            ->assertJsonPath('data.phone', '+40722000001');

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'organization_id' => $secondOrganization->id,
            'user_code' => 'CARD-001',
            'phone' => '+40722000001',
        ]);
    }

    public function test_user_can_have_multiple_active_services(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $services = [
            Service::query()->create($this->serviceData([
                'name' => 'Basic',
                'price' => 0,
                'is_active' => true,
            ])),
            Service::query()->create($this->serviceData([
                'name' => 'Pro',
                'price' => 0,
                'is_active' => true,
            ])),
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Subscribed',
                'last_name' => 'User',
                'email' => 'subscribed@example.com',
                'password' => 'password',
                'service_ids' => collect($services)->pluck('id')->all(),
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.services')
            ->assertJsonCount(2, 'data.active_services');

        $userId = $response->json('data.id');

        foreach ($services as $service) {
            $this->assertDatabaseHas('service_user', [
                'service_id' => $service->id,
                'user_id' => $userId,
            ]);
        }
    }

    public function test_user_service_dates_are_stored_and_expiration_is_calculated(): void
    {
        Carbon::setTestNow('2026-05-18 10:00:00');

        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Monthly',
            'price' => 0,
            'duration_days' => 10,
            'is_active' => true,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Subscribed',
                'last_name' => 'History',
                'email' => 'history@example.com',
                'password' => 'password',
                'services' => [
                    [
                        'id' => $service->id,
                        'start_date' => '2026-05-15',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_active_service', true)
            ->assertJsonPath('data.service_history.0.start_date', '2026-05-15T00:00:00.000000Z')
            ->assertJsonPath('data.service_history.0.expires_at', '2026-05-25T00:00:00.000000Z')
            ->assertJsonPath('data.service_history.0.is_active', true);

        $this->assertDatabaseHas('service_user', [
            'service_id' => $service->id,
            'user_id' => $response->json('data.id'),
            'start_date' => '2026-05-15 00:00:00',
            'expires_at' => '2026-05-25 00:00:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_assigning_service_creates_bill_without_invoice(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Bill only',
            'is_active' => true,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Bill',
                'last_name' => 'Only',
                'email' => 'bill-only@example.com',
                'password' => 'password',
                'service_ids' => [$service->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.service_history.0.invoice_number', null)
            ->assertJsonPath('data.service_history.0.bill_number', 'BILL000001');

        $this->assertDatabaseHas('service_user', [
            'service_id' => $service->id,
            'user_id' => $response->json('data.id'),
            'invoice_number' => null,
            'bill_number' => 'BILL000001',
        ]);
    }

    public function test_expired_user_service_is_not_marked_active(): void
    {
        Carbon::setTestNow('2026-05-18 10:00:00');

        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Expired',
            'duration_days' => 5,
            'is_active' => true,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Expired',
                'last_name' => 'User',
                'email' => 'expired-service@example.com',
                'password' => 'password',
                'services' => [
                    [
                        'id' => $service->id,
                        'start_date' => '2026-05-01',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_active_service', false)
            ->assertJsonCount(0, 'data.active_services')
            ->assertJsonPath('data.service_history.0.is_active', false);

        Carbon::setTestNow();
    }

    public function test_user_with_manage_right_can_update_user(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'email' => 'old@example.com',
        ]);

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
        $user = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'email' => 'blocked-update@example.com',
        ]);

        $admin->locations()->attach($allowedLocation);
        $user->locations()->attach($blockedLocation);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/{$user->id}", [
                'first_name' => 'Updated',
            ])
            ->assertNotFound();
    }

    public function test_user_with_manage_right_can_sync_services_through_dedicated_route(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $oldService = Service::query()->create($this->serviceData([
            'name' => 'Legacy',
            'is_active' => true,
        ]));
        $newService = Service::query()->create($this->serviceData([
            'name' => 'Fresh',
            'price' => 0,
            'is_active' => true,
        ]));

        $user->services()->attach($oldService);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/service/{$user->id}", [
                'service_ids' => [$newService->id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.id', $newService->id)
            ->assertJsonCount(1, 'data.active_services');

        $this->assertDatabaseHas('service_user', [
            'service_id' => $newService->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('service_user', [
            'service_id' => $oldService->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_with_manage_right_can_remove_all_services_through_dedicated_route(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Legacy',
            'is_active' => true,
        ]));

        $user->services()->attach($service);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/service/{$user->id}", [
                'services' => [],
            ])
            ->assertOk()
            ->assertJsonCount(0, 'data.services')
            ->assertJsonCount(0, 'data.active_services');

        $this->assertDatabaseMissing('service_user', [
            'service_id' => $service->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_with_manage_right_can_remove_omitted_service_through_dedicated_route(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $serviceToKeepA = Service::query()->create($this->serviceData([
            'name' => 'Keep A',
            'is_active' => true,
        ]));
        $serviceToRemove = Service::query()->create($this->serviceData([
            'name' => 'Remove',
            'is_active' => true,
        ]));
        $serviceToKeepB = Service::query()->create($this->serviceData([
            'name' => 'Keep B',
            'is_active' => true,
        ]));

        $user->services()->attach($serviceToKeepA, ['start_date' => '2026-08-30']);
        $user->services()->attach($serviceToRemove, ['start_date' => '2026-08-25']);
        $user->services()->attach($serviceToKeepB, ['start_date' => '2026-08-22']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/service/{$user->id}", [
                'services' => [
                    ['id' => $serviceToKeepA->id, 'start_date' => '2026-08-30'],
                    ['id' => $serviceToKeepB->id, 'start_date' => '2026-08-22'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.services')
            ->assertJsonMissing(['name' => 'Remove']);

        $this->assertDatabaseHas('service_user', [
            'service_id' => $serviceToKeepA->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('service_user', [
            'service_id' => $serviceToKeepB->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('service_user', [
            'service_id' => $serviceToRemove->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_syncing_existing_service_preserves_lifecycle_and_payment_link(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');

        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Paid plan',
            'price' => 100,
            'duration_days' => 30,
            'expiration_rule' => 'duration',
        ]));

        $user->services()->attach($service, [
            'status' => 'active',
            'start_date' => '2026-08-01',
            'expires_at' => '2026-08-31',
            'activated_at' => '2026-08-01 10:00:00',
        ]);
        $assignmentId = $user->services()->whereKey($service->id)->first()->pivot->id;
        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Ana',
            'last_name' => 'Pop',
            'payment_type_id' => Payment::TYPE_CARD,
            'status' => Payment::STATUS_CONFIRMED,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $assignmentId,
            'amount' => 100,
            'paid_at' => '2026-08-01 10:00:00',
            'admin_id' => $admin->id,
        ]);
        DB::table('service_user')->where('id', $assignmentId)->update(['activation_payment_id' => $payment->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/service/{$user->id}", [
                'services' => [
                    ['id' => $service->id, 'start_date' => '2026-08-02'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.services.0.pivot.status', 'active')
            ->assertJsonPath('data.services.0.pivot.activation_payment_id', $payment->id);

        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'service_id' => $service->id,
            'user_id' => $user->id,
            'status' => 'active',
            'activation_payment_id' => $payment->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_service_history_uses_lifecycle_status(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Paused plan',
            'price' => 0,
            'duration_days' => 30,
            'expiration_rule' => 'duration',
        ]));

        $user->services()->attach($service, [
            'status' => 'suspended',
            'start_date' => now()->subDays(5)->toDateString(),
            'expires_at' => now()->addDays(25)->toDateString(),
            'suspended_at' => now(),
            'status_reason' => 'Medical leave',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.service_history.0.service_id', $service->id)
            ->assertJsonPath('data.service_history.0.status', 'suspended')
            ->assertJsonPath('data.service_history.0.is_active', false)
            ->assertJsonPath('data.service_history.0.status_reason', 'Medical leave');
    }

    public function test_user_with_manage_right_can_delete_user(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'active' => false, 'user_code' => null]);
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

    private function serviceData(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Test service',
            'price' => 99.99,
            'currency' => 'EUR',
            'duration_days' => null,
            'max_users' => 25,
            'is_active' => true,
        ], $overrides);
    }
}
