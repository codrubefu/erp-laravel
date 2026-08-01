<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationAccessConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_right_group_blocks_authorization_even_when_right_is_assigned(): void
    {
        [$user, $token] = $this->authenticatedUserWithRights(['payments.view']);
        config()->set("organization-access.disabled_right_groups.{$user->organization_id}", ['payments']);

        Payment::query()->create([
            'first_name' => 'John',
            'last_name' => 'Member',
            'payment_type_id' => Payment::TYPE_CARD,
            'model_type' => Payment::MODEL_TYPE_SUBSCRIPTION_USER,
            'model_id' => 77,
            'amount' => 25.50,
            'paid_at' => '2026-06-01 12:00:00',
            'admin_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/payments')
            ->assertForbidden();
    }

    public function test_rights_index_hides_disabled_right_groups(): void
    {
        [$user, $token] = $this->authenticatedUserWithRights(['rights.view']);
        config()->set("organization-access.disabled_right_groups.{$user->organization_id}", ['payments']);

        Right::query()->create(['name' => 'payments.view', 'label' => 'View payments']);
        Right::query()->create(['name' => 'users.view', 'label' => 'View users']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rights')
            ->assertOk()
            ->assertJsonMissing(['name' => 'payments.view'])
            ->assertJsonFragment(['name' => 'users.view']);
    }

    public function test_group_cannot_be_created_or_updated_with_disabled_rights(): void
    {
        [$user, $token] = $this->authenticatedUserWithRights(['groups.manage']);
        config()->set("organization-access.disabled_right_groups.{$user->organization_id}", ['payments']);

        $disabledRight = Right::query()->create(['name' => 'payments.view', 'label' => 'View payments']);
        $group = Group::query()->create([
            'name' => 'staff',
            'label' => 'Staff',
            'organization_id' => $user->organization_id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/groups', [
                'name' => 'manager',
                'label' => 'Manager',
                'right_ids' => [$disabledRight->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['right_ids']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/groups/{$group->id}", [
                'right_ids' => [$disabledRight->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['right_ids']);
    }

    public function test_me_hides_disabled_rights_that_remain_attached_to_groups(): void
    {
        [$user, $token] = $this->authenticatedUserWithRights(['profile.view', 'payments.view']);
        config()->set("organization-access.disabled_right_groups.{$user->organization_id}", ['payments']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonMissing(['name' => 'payments.view'])
            ->assertJsonFragment(['name' => 'profile.view']);
    }

    public function test_organization_without_disabled_rights_keeps_existing_access(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['payments.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/payments')
            ->assertOk();
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
            'organization_id' => $user->organization_id,
        ]);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->firstOrCreate(
                ['name' => $rightName],
                ['label' => $rightName],
            );
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
