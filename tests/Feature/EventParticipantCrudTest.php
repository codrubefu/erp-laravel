<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventParticipantCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_manage_right_can_create_paid_event(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', [
                'title' => 'Eveniment platit',
                'description' => 'Descriere eveniment',
                'location' => 'Sala 2',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'recurrence_type' => 'once',
                'start_date' => '2026-06-10',
                'status' => 'active',
                'requires_payment' => true,
                'payment_amount' => 49.99,
                'payment_type' => 'card',
            ])
            ->assertCreated()
            ->assertJsonPath('data.requires_payment', true)
            ->assertJsonPath('data.payment_amount', '49.99')
            ->assertJsonPath('data.payment_type', 'card');

        $this->assertDatabaseHas('events', [
            'title' => 'Eveniment platit',
            'requires_payment' => true,
            'payment_amount' => 49.99,
            'payment_type' => 'card',
        ]);
    }

    public function test_paid_event_requires_amount_and_type(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', [
                'title' => 'Eveniment platit',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'recurrence_type' => 'once',
                'start_date' => '2026-06-10',
                'status' => 'active',
                'requires_payment' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'payment_amount',
                'payment_type',
            ]);
    }

    public function test_user_with_manage_right_can_update_occurrence_participant(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $participant = User::factory()->create();
        $event = Event::query()->create($this->eventData([
            'max_participants' => 10,
        ]));
        $occurrence = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => '2026-06-10',
            'start_datetime' => '2026-06-10 10:00:00',
            'end_datetime' => '2026-06-10 11:00:00',
            'status' => 'scheduled',
        ]);

        $occurrence->participants()->attach($participant->id, [
            'status' => 'registered',
            'registered_at' => '2026-06-01 09:00:00',
            'notes' => 'Inscris initial.',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-occurrences/{$occurrence->id}/participants/{$participant->id}", [
                'status' => 'attended',
                'notes' => 'A intarziat',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'attended')
            ->assertJsonPath('data.notes', 'A intarziat');

        $this->assertDatabaseHas('event_occurrence_user', [
            'event_occurrence_id' => $occurrence->id,
            'user_id' => $participant->id,
            'status' => 'attended',
            'notes' => 'A intarziat',
        ]);
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
            'requires_active_subscription' => false,
            'required_subscription_id' => null,
            'requires_payment' => false,
            'payment_amount' => null,
            'payment_type' => null,
            'max_participants' => null,
            'status' => 'active',
        ], $overrides);
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
