<?php

namespace App\Reporting\Services;

use App\Payments\Models\Payment;
use App\Reporting\Models\Segment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function __construct(private readonly SegmentService $segments) {}

    public function aggregate(int $organizationId, array $filters): array
    {
        $payments = $this->payments($organizationId, $filters);
        $confirmed = (clone $payments)->where('payments.status', Payment::STATUS_CONFIRMED);
        $confirmedTotal = (float) (clone $confirmed)->sum('payments.amount');
        $refundedTotal = (float) (clone $payments)->where('payments.status', Payment::STATUS_REFUNDED)->sum('payments.amount');

        $assignments = DB::table('subscription_user as su')
            ->join('subscriptions as s', 's.id', '=', 'su.subscription_id')
            ->join('users as member', 'member.id', '=', 'su.user_id')
            ->where('s.organization_id', $organizationId);
        $memberIds = $this->segmentMemberIds($organizationId, $filters);
        if ($memberIds !== null) {
            $assignments->whereIn('member.id', $memberIds);
        }
        if (isset($filters['subscription_type'])) {
            $assignments->where('s.type', $filters['subscription_type']);
        }
        if (isset($filters['location_id'])) {
            $assignments->whereExists(fn ($q) => $q->selectRaw('1')->from('location_user as lu')
                ->whereColumn('lu.user_id', 'member.id')->where('lu.location_id', $filters['location_id']));
        }
        $invoiced = (float) (clone $assignments)->sum('s.price');
        $subscriptionRevenue = (float) (clone $confirmed)
            ->where('payments.model_type', Payment::MODEL_TYPE_SUBSCRIPTION_USER)->sum('payments.amount');

        $renewals = (clone $assignments)->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('subscription_user as previous')
            ->whereColumn('previous.user_id', 'su.user_id')
            ->whereColumn('previous.subscription_id', 'su.subscription_id')
            ->whereColumn('previous.id', '<', 'su.id'))->count();

        $period = $filters['group_by'] ?? 'month';
        $dateExpression = $this->periodExpression($period);
        $revenue = (clone $confirmed)->selectRaw($dateExpression.' as period')
            ->selectRaw('SUM(payments.amount) as total')->groupBy('period')->orderBy('period')->get()
            ->map(fn ($row) => ['period' => $row->period, 'total' => (float) $row->total])->all();

        $bank = (clone $confirmed)->where('payments.payment_type_id', Payment::TYPE_BANK_TRANSFER);

        return [
            'totals' => ['confirmed' => $confirmedTotal, 'refunded' => $refundedTotal, 'net' => $confirmedTotal - $refundedTotal, 'count' => (clone $payments)->count()],
            'revenue_by_period' => $revenue,
            'receivables' => ['invoiced' => $invoiced, 'paid' => $subscriptionRevenue, 'outstanding' => max(0, $invoiced - $subscriptionRevenue)],
            'renewals' => $renewals,
            'bank_reconciliation' => [
                'total' => (float) (clone $bank)->sum('payments.amount'),
                'reconciled' => (float) (clone $bank)->whereNotNull('payments.reconciled_at')->sum('payments.amount'),
                'unreconciled' => (float) (clone $bank)->whereNull('payments.reconciled_at')->sum('payments.amount'),
            ],
        ];
    }

    public function rows(int $organizationId, array $filters): array
    {
        return $this->payments($organizationId, $filters)->orderBy('paid_at')->get([
            'id', 'paid_at', 'first_name', 'last_name', 'amount', 'status', 'payment_type_id',
            'model_type', 'model_id', 'admin_id', 'location_id', 'bank_reference', 'reconciled_at',
        ])->map(fn (Payment $payment) => [
            $payment->id, $payment->paid_at?->toIso8601String(), $payment->first_name, $payment->last_name,
            $payment->amount, $payment->status, $payment->paymentTypeName(), $payment->model_type,
            $payment->model_id, $payment->admin_id, $payment->location_id, $payment->bank_reference,
            $payment->reconciled_at?->toIso8601String(),
        ])->all();
    }

    private function payments(int $organizationId, array $filters): Builder
    {
        $query = Payment::query()->where('payments.organization_id', $organizationId);
        foreach (['location_id', 'admin_id', 'payment_type_id', 'status'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where("payments.{$filter}", $filters[$filter]);
            }
        }
        if (isset($filters['from'])) $query->where('payments.paid_at', '>=', $filters['from'].' 00:00:00');
        if (isset($filters['to'])) $query->where('payments.paid_at', '<=', $filters['to'].' 23:59:59');
        if (isset($filters['subscription_type'])) {
            $query->where('payments.model_type', Payment::MODEL_TYPE_SUBSCRIPTION_USER)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('subscription_user as su')
                    ->join('subscriptions as s', 's.id', '=', 'su.subscription_id')
                    ->whereColumn('su.id', 'payments.model_id')->where('s.type', $filters['subscription_type'])
                    ->where('s.organization_id', $organizationId));
        }
        $memberIds = $this->segmentMemberIds($organizationId, $filters);
        if ($memberIds !== null) {
            $query->where('payments.model_type', Payment::MODEL_TYPE_SUBSCRIPTION_USER)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('subscription_user as segment_su')
                    ->whereColumn('segment_su.id', 'payments.model_id')->whereIn('segment_su.user_id', $memberIds));
        }
        return $query;
    }

    private function periodExpression(string $period): string
    {
        if ($period === 'day') {
            return 'date(payments.paid_at)';
        }

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(payments.paid_at, '%Y-%m')",
            'pgsql' => "to_char(payments.paid_at, 'YYYY-MM')",
            'sqlsrv' => "CONVERT(varchar(7), payments.paid_at, 120)",
            default => "strftime('%Y-%m', payments.paid_at)",
        };
    }

    private function segmentMemberIds(int $organizationId, array $filters): ?array
    {
        if (! isset($filters['segment_id'])) {
            return null;
        }
        $segment = Segment::query()->where('organization_id', $organizationId)->findOrFail($filters['segment_id']);
        return $this->segments->members($segment)->pluck('users.id')->all();
    }
}
