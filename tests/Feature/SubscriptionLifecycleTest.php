<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Subscription\Models\Subscription;
use App\Subscription\Models\SubscriptionUser;
use App\Subscription\Services\SubscriptionLifecycleService;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_payment_activates_and_audits_an_assignment(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment();
        $payment = $this->payment($assignment);

        $assignment = app(SubscriptionLifecycleService::class)->activate($assignment, $payment);

        $this->assertSame('active', $assignment->status);
        $this->assertSame($payment->id, $assignment->activation_payment_id);
        $this->assertTrue($assignment->expires_at->equalTo(now()->addDays(30)));
        $this->assertTrue(AuditLog::query()->where('model_type', SubscriptionUser::class)->where('model_id', $assignment->id)->where('action', 'updated')->exists());
    }

    public function test_grace_period_includes_exact_boundary_then_expires_after_it(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment(['duration_days' => 1, 'grace_period_days' => 2]);
        $service = app(SubscriptionLifecycleService::class);
        $assignment = $service->activate($assignment, $this->payment($assignment));

        $this->assertSame('active', $service->refresh($assignment, now()->addDays(3))->status);
        $this->assertSame('expired', $service->refresh($assignment, now()->addDays(3)->addSecond())->status);
    }

    public function test_access_limit_consumes_assignment_on_last_access(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment(['max_accesses' => 2]);
        $service = app(SubscriptionLifecycleService::class);
        $assignment = $service->activate($assignment, $this->payment($assignment));

        $this->assertSame('active', $service->consumeAccess($assignment)->status);
        $assignment = $service->consumeAccess($assignment->refresh());
        $this->assertSame('consumed', $assignment->status);
        $this->assertSame(2, $assignment->accesses_used);
    }

    public function test_scheduled_and_manual_resume_follow_lifecycle_rules(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment();
        $service = app(SubscriptionLifecycleService::class);
        $assignment = $service->activate($assignment, $this->payment($assignment));
        $assignment = $service->suspend($assignment, 'Medical leave', now()->addHour());

        $this->assertSame('suspended', $service->refresh($assignment, now()->addMinutes(59))->status);
        $this->assertSame('active', $service->refresh($assignment, now()->addHour())->status);

        $assignment = $service->suspend($assignment->refresh(), 'Manual review');
        $this->assertSame('active', $service->resume($assignment)->status);
    }

    private function assignment(array $subscriptionOverrides = []): SubscriptionUser
    {
        $user = User::factory()->create();
        $subscription = Subscription::query()->create(array_merge([
            'organization_id' => $user->organization_id,
            'name' => 'Pass',
            'price' => 10,
            'currency' => 'EUR',
            'duration_days' => 30,
            'expiration_rule' => 'duration',
            'grace_period_days' => 0,
            'is_active' => true,
        ], $subscriptionOverrides));

        return SubscriptionUser::query()->create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    private function payment(SubscriptionUser $assignment): Payment
    {
        return Payment::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pop',
            'payment_type_id' => Payment::TYPE_CARD,
            'model_type' => Payment::MODEL_TYPE_SUBSCRIPTION_USER,
            'model_id' => $assignment->id,
            'amount' => 10,
            'paid_at' => now(),
            'admin_id' => $assignment->user_id,
        ]);
    }
}
