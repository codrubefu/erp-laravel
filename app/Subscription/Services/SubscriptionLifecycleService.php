<?php

namespace App\Subscription\Services;

use App\Payments\Models\Payment;
use App\Subscription\Models\SubscriptionUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionLifecycleService
{
    public const STATUSES = ['pending', 'active', 'expired', 'suspended', 'consumed', 'reserved'];

    public function activate(SubscriptionUser $assignment, Payment $payment, ?CarbonInterface $at = null): SubscriptionUser
    {
        return DB::transaction(function () use ($assignment, $payment, $at): SubscriptionUser {
            $assignment = $this->locked($assignment);
            $at ??= now();

            if ($payment->model_type !== Payment::MODEL_TYPE_SUBSCRIPTION_USER || $payment->model_id !== $assignment->id || $payment->paid_at === null) {
                throw ValidationException::withMessages(['payment_id' => 'Payment must be confirmed and linked to this subscription assignment.']);
            }

            if (! in_array($assignment->status, ['pending', 'reserved', 'expired'], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending, reserved, or expired assignments can be activated.']);
            }

            $subscription = $assignment->subscription;
            $start = $assignment->start_date ?? $at;
            $expiresAt = match ($subscription->expiration_rule) {
                'none' => null,
                'fixed_date' => $subscription->fixed_expires_at,
                default => $subscription->duration_days === null ? null : $start->copy()->addDays($subscription->duration_days),
            };
            $status = $start->isAfter($at) ? 'reserved' : 'active';

            $assignment->update([
                'status' => $status,
                'start_date' => $start,
                'expires_at' => $expiresAt,
                'activated_at' => $at,
                'activation_payment_id' => $payment->id,
                'suspended_at' => null,
                'resume_at' => null,
                'status_reason' => null,
            ]);

            return $assignment->refresh();
        });
    }

    public function suspend(SubscriptionUser $assignment, string $reason, ?CarbonInterface $resumeAt = null): SubscriptionUser
    {
        return DB::transaction(function () use ($assignment, $reason, $resumeAt): SubscriptionUser {
            $assignment = $this->locked($assignment);
            if (! in_array($assignment->status, ['active', 'reserved'], true)) {
                throw ValidationException::withMessages(['status' => 'Only active or reserved assignments can be suspended.']);
            }
            $assignment->update(['status' => 'suspended', 'suspended_at' => now(), 'resume_at' => $resumeAt, 'status_reason' => $reason]);

            return $assignment->refresh();
        });
    }

    public function resume(SubscriptionUser $assignment, ?CarbonInterface $at = null): SubscriptionUser
    {
        return DB::transaction(function () use ($assignment, $at): SubscriptionUser {
            $assignment = $this->locked($assignment);
            $at ??= now();
            if ($assignment->status !== 'suspended') {
                throw ValidationException::withMessages(['status' => 'Only suspended assignments can be resumed.']);
            }
            if ($this->isExpired($assignment, $at)) {
                $status = 'expired';
            } elseif ($assignment->subscription->max_accesses !== null && $assignment->accesses_used >= $assignment->subscription->max_accesses) {
                $status = 'consumed';
            } elseif ($assignment->start_date?->isAfter($at)) {
                $status = 'reserved';
            } else {
                $status = 'active';
            }
            $assignment->update(['status' => $status, 'suspended_at' => null, 'resume_at' => null, 'status_reason' => null]);

            return $assignment->refresh();
        });
    }

    public function refresh(SubscriptionUser $assignment, ?CarbonInterface $at = null): SubscriptionUser
    {
        $at ??= now();
        if ($assignment->status === 'suspended' && $assignment->resume_at?->lte($at)) {
            return $this->resume($assignment, $at);
        }
        if (in_array($assignment->status, ['active', 'reserved'], true)) {
            $status = $assignment->start_date?->isAfter($at) ? 'reserved' : 'active';
            if ($this->isExpired($assignment, $at)) {
                $status = 'expired';
            } elseif ($assignment->subscription->max_accesses !== null && $assignment->accesses_used >= $assignment->subscription->max_accesses) {
                $status = 'consumed';
            }
            if ($status !== $assignment->status) {
                $assignment->update(['status' => $status]);
            }
        }

        return $assignment->refresh();
    }

    public function consumeAccess(SubscriptionUser $assignment, ?CarbonInterface $at = null): SubscriptionUser
    {
        return DB::transaction(function () use ($assignment, $at): SubscriptionUser {
            $assignment = $this->refresh($this->locked($assignment), $at);
            if ($assignment->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'The subscription is not available for access.']);
            }
            $assignment->increment('accesses_used');

            return $this->refresh($assignment, $at);
        });
    }

    private function isExpired(SubscriptionUser $assignment, CarbonInterface $at): bool
    {
        if ($assignment->expires_at === null) {
            return false;
        }

        return $at->isAfter($assignment->expires_at->copy()->addDays($assignment->subscription->grace_period_days));
    }

    private function locked(SubscriptionUser $assignment): SubscriptionUser
    {
        return SubscriptionUser::query()->with('subscription')->lockForUpdate()->findOrFail($assignment->id);
    }
}
