<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientListBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_benchmark(): void
    {
        $admin = User::factory()->create(['email' => 'bench-admin@example.com', 'password' => 'password']);
        $orgId = $admin->organization_id;
        $group = Group::query()->create(['name' => 'bench-admin', 'label' => 'Admin']);
        $group->rights()->attach(Right::query()->firstOrCreate(['name' => 'users.view'], ['label' => 'users.view']));
        $admin->groups()->attach($group);
        $clientGroup = Group::query()->create(['name' => 'bench-client', 'label' => 'Client']);
        $clientGroup->rights()->attach(Right::query()->firstOrCreate(['name' => 'profile.view'], ['label' => 'profile.view']));

        $now = now();
        $locIds = [];
        for ($i = 1; $i <= 200; $i++) {
            $locIds[] = DB::table('locations')->insertGetId(['name' => "Loc {$i}", 'created_at' => $now, 'updated_at' => $now] + (DB::getSchemaBuilder()->hasColumn('locations', 'organization_id') ? ['organization_id' => $orgId] : []));
        }
        $service = Service::query()->create(['name' => 'Abonament', 'description' => 'x', 'price' => 10, 'currency' => 'EUR', 'max_users' => 100000, 'is_active' => true]);

        $pwd = Hash::make('x');
        $faker = fake();
        $rows = [];
        for ($i = 0; $i < 5000; $i++) {
            $rows[] = ['organization_id' => $orgId, 'first_name' => $faker->firstName(), 'last_name' => $faker->lastName(),
                'email' => "c{$i}@ex.com", 'phone' => "07{$i}", 'user_code' => sprintf('C%05d', $i), 'active' => true,
                'password' => $pwd, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }
        $clientIds = DB::table('users')->where('id', '!=', $admin->id)->pluck('id');
        $lu = $gu = $su = [];
        foreach ($clientIds as $k => $id) {
            $lu[] = ['location_id' => $locIds[$k % 200], 'user_id' => $id, 'created_at' => $now, 'updated_at' => $now];
            $gu[] = ['group_id' => $clientGroup->id, 'user_id' => $id, 'created_at' => $now, 'updated_at' => $now];
            $su[] = ['service_id' => $service->id, 'user_id' => $id, 'status' => 'active', 'start_date' => $now->copy()->subDays(5)->toDateString(), 'expires_at' => $now->copy()->addDays(25), 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ([['location_user', $lu], ['group_user', $gu], ['service_user', $su]] as [$t, $data]) {
            foreach (array_chunk($data, 200) as $chunk) {
                DB::table($t)->insert($chunk);
            }
        }
        // admin restricted to 10 locations -> LocationAccessScope active
        foreach (array_slice($locIds, 0, 10) as $lid) {
            DB::table('location_user')->insert(['location_id' => $lid, 'user_id' => $admin->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        if (getenv('BENCH_IDX')) {
            DB::statement('create index bench_lu_user on location_user (user_id, location_id)');
            DB::statement('create index bench_gu_user on group_user (user_id, group_id)');
        }

        $token = $this->postJson('/api/login', ['email' => $admin->email, 'organization_id' => $orgId, 'password' => 'password'])->json('token');

        foreach (['' => 'page 1', '&page=10' => 'page 10', '&search=ana' => 'search=ana', '&per_page=100' => 'per_page=100'] as $qs => $label) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $t = microtime(true);
            $res = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/clients?page=1' . $qs)->assertOk();
            $ms = (microtime(true) - $t) * 1000;
            $log = DB::getQueryLog();
            DB::disableQueryLog();
            fwrite(STDERR, sprintf("\n=== %s: %.0f ms, %d queries, total=%d, rows=%d\n", $label, $ms, count($log), $res->json('meta.total') ?? -1, count($res->json('data'))));
            usort($log, fn ($a, $b) => $b['time'] <=> $a['time']);
            foreach (array_slice($log, 0, 6) as $q) {
                fwrite(STDERR, sprintf("  %7.2f ms  %s\n", $q['time'], substr($q['query'], 0, 220)));
            }
            if ($label === 'page 1') {
                $slow = $log[0];
                $plan = DB::select('EXPLAIN QUERY PLAN ' . $slow['query'], $slow['bindings']);
                fwrite(STDERR, "  PLAN slowest:\n");
                foreach ($plan as $p) {
                    fwrite(STDERR, '    ' . $p->detail . "\n");
                }
                fwrite(STDERR, "  FULL slowest: " . $slow['query'] . "\n");
            }
        }
        $this->assertTrue(true);
    }
}
