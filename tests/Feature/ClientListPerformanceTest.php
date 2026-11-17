<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientListPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_list_query_count_does_not_scale_with_service_assignments(): void
    {
        [$admin, $token] = $this->admin();
        $service = $this->service();

        User::factory()->count(60)->create(['organization_id' => $admin->organization_id])
            ->each(fn (User $user) => $user->services()->attach($service, [
                'status' => 'active',
                'start_date' => now()->subDay()->toDateString(),
            ]));

        DB::enableQueryLog();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients?page=1&per_page=15')
            ->assertOk()
            ->assertJsonCount(15, 'data');

        $this->assertLessThan(25, count(DB::getQueryLog()));
    }

    public function test_client_list_still_expires_outdated_assignments_of_listed_users(): void
    {
        [$admin, $token] = $this->admin();
        $service = $this->service();
        $client = User::factory()->create(['organization_id' => $admin->organization_id]);
        $client->services()->attach($service, [
            'status' => 'active',
            'start_date' => now()->subDays(40)->toDateString(),
            'expires_at' => now()->subDays(10),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients?page=1&per_page=15')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $client->id);
        $this->assertFalse($row['has_active_service']);
        $this->assertDatabaseHas('service_user', ['user_id' => $client->id, 'status' => 'expired']);
    }

    private function admin(): array
    {
        $admin = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => 'password']);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Test Group']);
        $group->rights()->attach(Right::query()->firstOrCreate(['name' => 'users.view'], ['label' => 'users.view']));
        $admin->groups()->attach($group);

        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'organization_id' => $admin->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$admin, $token];
    }

    private function service(): Service
    {
        return Service::query()->create([
            'name' => 'Abonament',
            'description' => 'Test service',
            'price' => 99.99,
            'currency' => 'EUR',
            'duration_days' => null,
            'max_users' => 1000,
            'is_active' => true,
        ]);
    }
}
