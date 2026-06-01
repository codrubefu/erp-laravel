<?php

namespace Tests\Feature;

use App\Sms\Models\SmsMessage;
use App\Subscription\Models\Subscription;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsMessageIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_sms_view_right_can_list_sms_messages_with_search_filters_and_pagination(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['sms.view']);

        $user = User::factory()->create([
            'first_name' => 'Ion',
            'last_name' => 'Popescu',
            'phone' => '0722000001',
            'email' => 'ion@example.com',
        ]);
        $subscription = Subscription::query()->create([
            'organization_id' => $user->organization_id,
            'name' => 'Gold',
            'description' => 'Gold subscription',
            'price' => 100,
            'currency' => 'RON',
            'duration_days' => 30,
            'max_users' => 10,
            'is_active' => true,
        ]);

        $firstMatch = SmsMessage::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'subscription_user_id' => 101,
            'type' => SmsMessage::TYPE_SUBSCRIPTION_EXPIRING,
            'destination' => '0722000001',
            'message' => 'Gold expires soon',
            'status' => SmsMessage::STATUS_SENT,
            'sent_at' => '2026-06-01 10:00:00',
        ]);

        SmsMessage::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'subscription_user_id' => 102,
            'type' => SmsMessage::TYPE_SUBSCRIPTION_EXPIRING,
            'destination' => '0722000001',
            'message' => 'Another Gold reminder',
            'status' => SmsMessage::STATUS_SENT,
            'sent_at' => '2026-06-01 11:00:00',
        ]);

        SmsMessage::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'subscription_user_id' => 103,
            'type' => 'manual',
            'destination' => '0733000000',
            'message' => 'Other message',
            'status' => SmsMessage::STATUS_FAILED,
            'sent_at' => '2026-05-28 09:00:00',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sms-messages?search=Gold&status=sent&type=subscription_expiring&user_id='.$user->id.'&subscription_id='.$subscription->id.'&sent_from=2026-06-01&sent_to=2026-06-01&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 2)
            ->assertJsonPath('data.0.subscription.name', 'Gold')
            ->assertJsonPath('data.0.user.first_name', 'Ion')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sms-messages?search=Gold&status=sent&type=subscription_expiring&user_id='.$user->id.'&subscription_id='.$subscription->id.'&sent_from=2026-06-01&sent_to=2026-06-01&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $firstMatch->id);
    }

    public function test_user_without_sms_view_right_cannot_list_sms_messages(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['subscriptions.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sms-messages')
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
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $token];
    }
}