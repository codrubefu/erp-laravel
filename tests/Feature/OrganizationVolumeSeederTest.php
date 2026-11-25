<?php

namespace Tests\Feature;

use Database\Seeders\OrganizationVolumeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationVolumeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_distributes_users_and_branches_evenly_inside_each_organization(): void
    {
        $this->seed(OrganizationVolumeSeeder::class);

        $this->assertDatabaseCount('organizations', 20);
        $this->assertDatabaseCount('location_groups', 100);
        $this->assertDatabaseCount('locations', 100);
        $this->assertDatabaseCount('users', 5000);
        $this->assertDatabaseCount('location_user', 5000);

        $this->assertSame([5], $this->distinctCounts('location_groups', 'organization_id', 20));
        $this->assertSame([250], $this->distinctCounts('users', 'organization_id', 20));
        $this->assertSame([50], $this->distinctCounts('location_user', 'location_id', 100));

        $crossOrganizationLinks = DB::table('location_user')
            ->join('users', 'users.id', '=', 'location_user.user_id')
            ->join('locations', 'locations.id', '=', 'location_user.location_id')
            ->join('location_groups', 'location_groups.id', '=', 'locations.location_group_id')
            ->where(fn ($query) => $query
                ->whereColumn('users.organization_id', '!=', 'locations.organization_id')
                ->orWhereColumn('locations.organization_id', '!=', 'location_groups.organization_id'))
            ->count();

        $this->assertSame(0, $crossOrganizationLinks);
        $this->assertSame(0, DB::table('users')->whereNotNull('password')->count());
        $this->assertDatabaseCount('group_user', 0);
    }

    public function test_it_can_run_more_than_once(): void
    {
        $this->seed(OrganizationVolumeSeeder::class);
        $this->seed(OrganizationVolumeSeeder::class);

        $this->assertDatabaseCount('organizations', 40);
        $this->assertDatabaseCount('location_groups', 200);
        $this->assertDatabaseCount('users', 10000);
    }

    /** @return list<int> */
    private function distinctCounts(string $table, string $column, int $expectedGroups): array
    {
        $counts = DB::table($table)->select($column)->selectRaw('count(*) as total')->groupBy($column)->pluck('total');

        $this->assertCount($expectedGroups, $counts);

        return $counts->map(fn ($total): int => (int) $total)->unique()->values()->all();
    }
}
