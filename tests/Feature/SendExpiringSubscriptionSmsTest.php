<?php

namespace Tests\Feature;

use App\Sms\Models\SmsMessage;
use App\Sms\Services\SmsPortalService;
use App\Subscription\Jobs\SendExpiringSubscriptionSms;
use App\Subscription\Models\Subscription;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendExpiringSubscriptionSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_and_stores_sms_for_subscription_expiring_at_configured_notice_days(): void
    {
        $this->travelTo('2026-06-01 10:00:00');
        config()->set('subscriptions.expiration_notice_days', 1);
        config()->set('services.smsportal.user', 'acomtws');
        config()->set('services.smsportal.password', 'secret');
        Http::fake(['https://mtws.smsportal.ro/main.aspx*' => Http::response('OK', 200)]);

        [$user, $subscription] = $this->attachSubscription('2026-06-02');

        app(SendExpiringSubscriptionSms::class)->handle(app(SmsPortalService::class));

        Http::assertSentCount(1);
        $this->assertDatabaseHas('sms_messages', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'type' => SmsMessage::TYPE_SUBSCRIPTION_EXPIRING,
            'destination' => '0722535723',
            'status' => SmsMessage::STATUS_SENT,
        ]);
    }

    public function test_it_does_not_send_same_expiration_sms_twice(): void
    {
        $this->travelTo('2026-06-01 10:00:00');
        config()->set('services.smsportal.user', 'acomtws');
        config()->set('services.smsportal.password', 'secret');
        Http::fake(['https://mtws.smsportal.ro/main.aspx*' => Http::response('OK', 200)]);

        $this->attachSubscription('2026-06-02');

        $job = app(SendExpiringSubscriptionSms::class);
        $job->handle(app(SmsPortalService::class));
        $job->handle(app(SmsPortalService::class));

        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_it_uses_configured_notice_days(): void
    {
        $this->travelTo('2026-06-01 10:00:00');
        config()->set('subscriptions.expiration_notice_days', 3);
        config()->set('services.smsportal.user', 'acomtws');
        config()->set('services.smsportal.password', 'secret');
        Http::fake(['https://mtws.smsportal.ro/main.aspx*' => Http::response('OK', 200)]);

        $this->attachSubscription('2026-06-02');
        $this->attachSubscription('2026-06-04', '0733000000');

        app(SendExpiringSubscriptionSms::class)->handle(app(SmsPortalService::class));

        Http::assertSentCount(1);
        $this->assertDatabaseHas('sms_messages', [
            'destination' => '0733000000',
            'status' => SmsMessage::STATUS_SENT,
        ]);
        $this->assertDatabaseMissing('sms_messages', [
            'destination' => '0722535723',
        ]);
    }

    private function attachSubscription(string $expiresAt, string $phone = '0722535723'): array
    {
        $user = User::factory()->create(['phone' => $phone]);
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

        $user->subscriptions()->attach($subscription->id, [
            'start_date' => '2026-05-03',
            'expires_at' => $expiresAt,
        ]);

        return [$user, $subscription];
    }
}
