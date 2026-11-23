<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\LocationGroup;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_locations(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);

        Location::query()->create([
            'name' => 'Main Office',
            'description' => 'Headquarters',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/locations')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Main Office']);
    }

    public function test_locations_are_listed_alphabetically_by_default(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);

        Location::query()->create(['name' => 'Warehouse']);
        Location::query()->create(['name' => 'Annex']);
        Location::query()->create(['name' => 'Main Office']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/locations')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Annex')
            ->assertJsonPath('data.1.name', 'Main Office')
            ->assertJsonPath('data.2.name', 'Warehouse');
    }

    public function test_locations_can_be_filtered_by_location_group(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);
        [$north, $south] = $this->locationsInTwoGroups();

        $this->assertSame(['Annex', 'Warehouse'], $this->listedNames($token, ['location_group_id' => $north->id]));
        $this->assertSame(['Main Office'], $this->listedNames($token, ['location_group_id' => $south->id]));
        $this->assertSame(['Depot'], $this->listedNames($token, ['location_group_id' => 'none']));
        $this->assertSame(['Warehouse'], $this->listedNames($token, ['location_group_id' => $north->id, 'search' => 'ware']));
        $this->assertSame(['Annex', 'Depot', 'Main Office', 'Warehouse'], $this->listedNames($token, ['location_group_id' => '']));
    }

    public function test_locations_can_be_sorted(): void
    {
        [$viewer, $token] = $this->authenticatedUserWithRights(['locations.view']);
        $this->locationsInTwoGroups();

        $users = User::factory()->count(2)->create(['organization_id' => $viewer->organization_id]);
        $depot = Location::query()->where('name', 'Depot')->firstOrFail();
        $depot->users()->sync($users->pluck('id'));
        Location::query()->where('name', 'Warehouse')->firstOrFail()->users()->sync([$users->first()->id]);
        Location::query()->where('name', 'Main Office')->toBase()->update(['updated_at' => now()->addDay()]);
        Location::query()->where('name', 'Annex')->toBase()->update(['updated_at' => now()->subDay()]);

        $this->assertSame(['Warehouse', 'Main Office', 'Depot', 'Annex'], $this->listedNames($token, ['sort_by' => 'name', 'sort_dir' => 'desc']));
        $this->assertSame(['Depot', 'Annex', 'Warehouse', 'Main Office'], $this->listedNames($token, ['sort_by' => 'location_group']));
        $this->assertSame(['Main Office', 'Annex', 'Warehouse', 'Depot'], $this->listedNames($token, ['sort_by' => 'location_group', 'sort_dir' => 'desc']));
        $this->assertSame(['Depot', 'Warehouse', 'Annex', 'Main Office'], $this->listedNames($token, ['sort_by' => 'users_count', 'sort_dir' => 'desc']));
        $this->assertSame('Annex', $this->listedNames($token, ['sort_by' => 'updated_at'])[0]);
        $this->assertSame('Main Office', $this->listedNames($token, ['sort_by' => 'updated_at', 'sort_dir' => 'desc'])[0]);
    }

    public function test_location_list_rejects_invalid_filter_and_sort_values(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/locations?sort_by=password&sort_dir=sideways&location_group_id=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_by', 'sort_dir', 'location_group_id']);
    }

    public function test_location_group_filter_does_not_expose_other_organizations(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);
        $other = Organization::factory()->create();
        $group = LocationGroup::query()->create(['name' => 'Foreign', 'organization_id' => $other->id]);
        Location::query()->create(['name' => 'Hidden', 'organization_id' => $other->id, 'location_group_id' => $group->id]);

        $this->assertSame([], $this->listedNames($token, ['location_group_id' => $group->id]));
    }

    /** @return array{LocationGroup, LocationGroup} */
    private function locationsInTwoGroups(): array
    {
        $north = LocationGroup::query()->create(['name' => 'North']);
        $south = LocationGroup::query()->create(['name' => 'South']);

        Location::query()->create(['name' => 'Warehouse', 'location_group_id' => $north->id]);
        Location::query()->create(['name' => 'Main Office', 'location_group_id' => $south->id]);
        Location::query()->create(['name' => 'Annex', 'location_group_id' => $north->id]);
        Location::query()->create(['name' => 'Depot']);

        return [$north, $south];
    }

    private function listedNames(string $token, array $query): array
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/locations?'.http_build_query($query))
            ->assertOk()
            ->json('data.*.name');
    }

    public function test_user_with_manage_right_can_create_location_with_users(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['locations.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/locations', [
                'name' => 'Warehouse',
                'description' => 'Storage location',
                'user_ids' => [$user->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Warehouse')
            ->assertJsonPath('data.users.0.id', $user->id);

        $this->assertDatabaseHas('locations', ['name' => 'Warehouse']);
        $this->assertDatabaseHas('location_user', ['user_id' => $user->id]);
    }

    public function test_user_with_manage_right_can_update_location(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.manage']);
        $location = Location::query()->create([
            'name' => 'Warehouse',
            'description' => 'Storage location',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/locations/{$location->id}", [
                'description' => 'Updated storage location',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated storage location');
    }

    public function test_user_with_manage_right_can_delete_location(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.manage']);
        $location = Location::query()->create([
            'name' => 'Warehouse',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/locations/{$location->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_user_without_manage_right_cannot_create_location(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/locations', [
                'name' => 'Blocked',
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
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$user, $token];
    }
}
