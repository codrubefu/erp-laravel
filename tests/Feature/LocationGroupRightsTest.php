<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\LocationGroup;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LocationGroupRightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_location_group_rights_and_assigns_default_groups(): void
    {
        $exitCode = Artisan::call('db:seed');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('rights', ['name' => 'location_groups.view']);
        $this->assertDatabaseHas('rights', ['name' => 'location_groups.manage']);

        $admin = Group::query()->where('name', 'admin')->firstOrFail();
        $manager = Group::query()->where('name', 'manager')->firstOrFail();

        $this->assertTrue($admin->rights()->where('name', 'location_groups.view')->exists());
        $this->assertTrue($admin->rights()->where('name', 'location_groups.manage')->exists());
        $this->assertTrue($manager->rights()->where('name', 'location_groups.view')->exists());
        $this->assertFalse($manager->rights()->where('name', 'location_groups.manage')->exists());
    }

    public function test_user_with_location_group_view_right_can_list_location_groups(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['location_groups.view']);

        LocationGroup::query()->create([
            'name' => 'Downtown',
            'description' => 'Downtown locations',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/location-groups')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Downtown']);
    }

    public function test_location_view_right_does_not_allow_listing_location_groups(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/location-groups')
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
