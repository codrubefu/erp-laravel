<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Payments\Services\PaymentService;
use App\Service\Models\ServiceUser;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_confirmation_activates_service_and_issues_receipt(): void
    {
        Carbon::setTestNow('2026-09-03 10:00:00');
        [$operator, $assignmentId] = $this->serviceAssignment();

        $payment = app(PaymentService::class)->create($this->paymentData($assignmentId, Payment::TYPE_CASH), $operator);

        $this->assertSame(Payment::STATUS_CONFIRMED, $payment->status);
        $this->assertSame('CH000001', $payment->receipt_number);
        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'status' => 'active',
            'start_date' => now()->format('Y-m-d H:i:s'),
            'activation_payment_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('organizations', [
            'id' => $operator->organization_id,
            'receipt_number' => 1,
        ]);

        Carbon::setTestNow();
    }

    public function test_card_confirmation_activates_service_and_issues_receipt(): void
    {
        [$operator, $assignmentId] = $this->serviceAssignment();

        $payment = app(PaymentService::class)->create($this->paymentData($assignmentId, Payment::TYPE_CARD), $operator);

        $this->assertSame(Payment::STATUS_CONFIRMED, $payment->status);
        $this->assertSame('CH000001', $payment->receipt_number);
        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'status' => 'active',
            'activation_payment_id' => $payment->id,
        ]);
    }

    public function test_bank_callback_activates_service_and_issues_receipt(): void
    {
        [$operator, $assignmentId] = $this->serviceAssignment();
        $payment = app(PaymentService::class)->create($this->paymentData($assignmentId, Payment::TYPE_BANK_TRANSFER), $operator);

        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertNull($payment->receipt_number);

        $payment = app(PaymentService::class)->processCallback([
            'external_reference' => $payment->external_reference,
            'transaction_id' => 'bank-123',
            'status' => Payment::STATUS_CONFIRMED,
        ]);

        $this->assertNotNull($payment->receipt_number);
        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'status' => 'active',
            'activation_payment_id' => $payment->id,
        ]);
    }

    public function test_confirmed_callback_is_idempotent(): void
    {
        config(['services.payments.callback_secret' => 'callback-secret']);
        [$operator, $assignmentId] = $this->serviceAssignment();
        $payment = app(PaymentService::class)->create($this->paymentData($assignmentId, Payment::TYPE_CARD), $operator);
        $payload = ['external_reference' => $payment->external_reference, 'transaction_id' => 'provider-123', 'status' => Payment::STATUS_CONFIRMED];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $json, 'callback-secret');

        foreach ([1, 2] as $attempt) {
            $this->call('POST', '/api/payments/callback', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYMENT_SIGNATURE' => $signature,
            ], $json)->assertOk()->assertJsonPath('data.status', Payment::STATUS_CONFIRMED);
        }

        $this->assertSame(1, Payment::query()->where('external_reference', $payment->external_reference)->count());
        $this->assertNotNull($payment->refresh()->receipt_number);
        $this->assertSame($payment->id, DB::table('service_user')->where('id', $assignmentId)->value('activation_payment_id'));
    }

    public function test_failed_payment_does_not_activate_service(): void
    {
        [$operator, $assignmentId] = $this->serviceAssignment();
        $payment = app(PaymentService::class)->create($this->paymentData($assignmentId, Payment::TYPE_BANK_TRANSFER), $operator);

        app(PaymentService::class)->processCallback([
            'external_reference' => $payment->external_reference,
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => 'declined',
        ]);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_FAILED, 'failure_reason' => 'declined']);
        $this->assertDatabaseHas('service_user', ['id' => $assignmentId, 'start_date' => null, 'expires_at' => null]);
    }

    public function test_confirmed_payment_from_another_organization_cannot_activate_assignment(): void
    {
        [$operator, $assignmentId] = $this->serviceAssignment();
        $otherOperator = User::factory()->create();
        $payment = Payment::query()->create(array_merge($this->paymentData($assignmentId, Payment::TYPE_CARD), [
            'organization_id' => $otherOperator->organization_id,
            'admin_id' => $otherOperator->id,
            'status' => Payment::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ServiceLifecycleService::class)->activate(
            ServiceUser::query()->findOrFail($assignmentId),
            $payment,
        );
    }

    public function test_cancelling_the_activation_payment_returns_assignment_to_pending_and_allows_a_new_payment(): void
    {
        [$operator, $assignmentId] = $this->serviceAssignment();
        $payments = app(PaymentService::class);
        $payment = $payments->create($this->paymentData($assignmentId, Payment::TYPE_CASH), $operator);

        $cancelled = $payments->cancel($payment, 'Suma gresita', $operator);

        $this->assertSame(Payment::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('Suma gresita', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('CH000001', $cancelled->receipt_number);
        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'status' => 'pending',
            'activation_payment_id' => null,
            'expires_at' => null,
            'activated_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditLog::PAYMENT_CANCELLED,
            'model_id' => $payment->id,
            'changed_by' => $operator->id,
        ]);

        $replacement = $payments->create($this->paymentData($assignmentId, Payment::TYPE_CASH), $operator);

        $this->assertSame('CH000002', $replacement->receipt_number);
        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'status' => 'active',
            'activation_payment_id' => $replacement->id,
        ]);
    }

    public function test_cancelling_keeps_the_assignment_active_when_another_confirmed_payment_covers_it(): void
    {
        [$operator, $assignmentId] = $this->serviceAssignment();
        $payments = app(PaymentService::class);
        $payment = $payments->create($this->paymentData($assignmentId, Payment::TYPE_CASH), $operator);
        $other = Payment::query()->create([
            ...$this->paymentData($assignmentId, Payment::TYPE_CASH),
            'organization_id' => $operator->organization_id,
            'admin_id' => $operator->id,
            'status' => Payment::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $payments->cancel($payment, 'Dublura', $operator);

        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'status' => 'active',
            'activation_payment_id' => $other->id,
        ]);
    }

    public function test_late_provider_callback_does_not_revive_a_cancelled_payment(): void
    {
        [$operator, $assignmentId] = $this->serviceAssignment();
        $payments = app(PaymentService::class);
        $payment = $payments->create($this->paymentData($assignmentId, Payment::TYPE_CASH), $operator);
        $payments->cancel($payment, 'Suma gresita', $operator);

        $result = $payments->processCallback([
            'external_reference' => $payment->external_reference,
            'status' => Payment::STATUS_CONFIRMED,
        ]);

        $this->assertSame(Payment::STATUS_CANCELLED, $result->status);
        $this->assertDatabaseHas('service_user', ['id' => $assignmentId, 'status' => 'pending']);
    }

    public function test_bank_callback_freezes_the_receipt(): void
    {
        [$operator, $assignmentId] = $this->serviceAssignment();
        $payments = app(PaymentService::class);
        $payment = $payments->create($this->paymentData($assignmentId, Payment::TYPE_BANK_TRANSFER), $operator);

        $this->assertNull($payment->receipt_snapshot);

        $confirmed = $payments->processCallback([
            'external_reference' => $payment->external_reference,
            'status' => Payment::STATUS_CONFIRMED,
        ]);

        $this->assertSame('Transfer bancar', $confirmed->receipt_snapshot['paymentMethod']);
        $this->assertStringContainsString('expira', $confirmed->receipt_snapshot['description']);
    }

    private function serviceAssignment(): array
    {
        $operator = User::factory()->create();
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);
        $serviceId = DB::table('services')->insertGetId([
            'organization_id' => $operator->organization_id,
            'name' => 'Annual membership',
            'description' => 'Test',
            'price' => 100,
            'currency' => 'RON',
            'duration_days' => 365,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assignmentId = DB::table('service_user')->insertGetId([
            'service_id' => $serviceId,
            'user_id' => $member->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$operator, $assignmentId];
    }

    private function paymentData(int $assignmentId, int $type): array
    {
        return [
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'payment_type_id' => $type,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $assignmentId,
            'amount' => 100,
            'paid_at' => now(),
        ];
    }
}
