<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_periods_and_bank_reconciliation_are_aggregated(): void
    {
        [$operator, $token] = $this->loginWith('reports.view');
        $this->payment($operator, 100, Payment::STATUS_CONFIRMED, Payment::TYPE_BANK_TRANSFER, '2026-07-01', true);
        $this->payment($operator, 40, Payment::STATUS_CONFIRMED, Payment::TYPE_BANK_TRANSFER, '2026-07-15');
        $this->payment($operator, 10, Payment::STATUS_REFUNDED, Payment::TYPE_CARD, '2026-07-20');

        $this->withToken($token)->getJson('/api/reports/financial?from=2026-07-01&to=2026-07-31')
            ->assertOk()->assertJsonPath('data.totals.confirmed', 140)
            ->assertJsonPath('data.totals.net', 130)
            ->assertJsonPath('data.revenue_by_period.0.period', '2026-07')
            ->assertJsonPath('data.bank_reconciliation.reconciled', 100)
            ->assertJsonPath('data.bank_reconciliation.unreconciled', 40);
    }

    public function test_report_cannot_read_or_request_another_organization(): void
    {
        [$operator, $token] = $this->loginWith('reports.view');
        $outsider = User::factory()->create();
        $this->payment($outsider, 999, Payment::STATUS_CONFIRMED, Payment::TYPE_CARD, '2026-07-01');

        $this->withToken($token)->getJson('/api/reports/financial')
            ->assertOk()->assertJsonPath('data.totals.confirmed', 0);
        $this->withToken($token)->getJson('/api/reports/financial?organization_id='.$outsider->organization_id)
            ->assertForbidden();
    }

    private function payment(User $operator, float $amount, string $status, int $type, string $date, bool $reconciled = false): void
    {
        Payment::query()->create([
            'organization_id' => $operator->organization_id, 'first_name' => 'Test', 'last_name' => 'Member',
            'payment_type_id' => $type, 'status' => $status, 'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'amount' => $amount, 'paid_at' => $date, 'admin_id' => $operator->id,
            'reconciled_at' => $reconciled ? $date : null,
        ]);
    }

    private function loginWith(string $rightName): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $right = Right::query()->create(['name' => $rightName, 'label' => $rightName]);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Reports']);
        $group->rights()->attach($right);
        $user->groups()->attach($group);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'organization_id' => $user->organization_id, 'password' => 'password'])->json('token');
        return [$user, $token];
    }
}
