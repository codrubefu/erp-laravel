<?php

namespace Tests\Feature;

use App\Users\Models\AuditLog;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_changes_are_logged_with_actor_and_values(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['groups.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/groups', [
                'name' => 'operators',
                'label' => 'Operators',
                'description' => 'Initial description',
            ])
            ->assertCreated();

        $groupId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => Group::class,
            'model_id' => $groupId,
            'action' => 'created',
            'changed_by' => $admin->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/groups/{$groupId}", [
                'label' => 'Dispatch Operators',
            ])
            ->assertOk();

        $updateLog = AuditLog::query()
            ->where('model_type', Group::class)
            ->where('model_id', $groupId)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame(['label' => 'Operators'], $updateLog->old_values);
        $this->assertSame(['label' => 'Dispatch Operators'], $updateLog->new_values);
        $this->assertSame($admin->id, $updateLog->changed_by);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/groups/{$groupId}")
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => Group::class,
            'model_id' => $groupId,
            'action' => 'deleted',
            'changed_by' => $admin->id,
        ]);
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
            $right = Right::query()->firstOrCreate([
                'name' => $rightName,
            ], [
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
