<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_manage_right_can_manage_event_categories_and_filter_events(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.view', 'events.manage']);

        $categoryId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/event-categories', [
                'name' => 'Fitness',
                'color' => '#2563eb',
                'description' => 'Fitness classes',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Fitness')
            ->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventData([
                'title' => 'Yoga',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.category_id', null);

        $eventId = Event::query()->where('title', 'Yoga')->value('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$eventId}", [
                'category_id' => $categoryId,
                'title' => 'Yoga',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'recurrence_type' => 'once',
                'start_date' => '2026-06-10',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.category_id', $categoryId)
            ->assertJsonPath('data.category.name', 'Fitness');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/events?category_id={$categoryId}")
            ->assertOk()
            ->assertJsonFragment(['title' => 'Yoga']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-categories/{$categoryId}", [
                'name' => 'Fitness premium',
                'color' => '#16a34a',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Fitness premium')
            ->assertJsonPath('data.is_active', false);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/event-categories/{$categoryId}")
            ->assertNoContent();

        $this->assertSoftDeleted('event_categories', ['id' => $categoryId]);
        $this->assertDatabaseHas('events', [
            'title' => 'Yoga',
            'category_id' => null,
            'organization_id' => $admin->organization_id,
        ]);
    }

    public function test_user_with_view_right_cannot_create_event_category(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/event-categories', ['name' => 'Readonly'])
            ->assertForbidden();
    }

    private function eventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Eveniment test',
            'description' => 'Descriere eveniment',
            'location' => 'Sala 1',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'recurrence_days' => null,
            'monthly_day' => null,
            'start_date' => '2026-06-10',
            'end_date' => null,
            'requires_active_service' => false,
            'required_service_id' => null,
            'requires_payment' => false,
            'payment_amount' => null,
            'payment_type' => null,
            'max_participants' => null,
            'status' => 'active',
        ], $overrides);
    }

    private function authenticatedUserWithRights(array $rightNames): array
    {
        Organization::factory()->create();

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
