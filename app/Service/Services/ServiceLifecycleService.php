<?php

namespace App\Service\Services;

use App\Notifications\Events\NotificationRequested;
use App\Payments\Models\Payment;
use App\Service\Models\Service;
use App\Service\Models\ServiceUser;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceLifecycleService
{
    public const STATUSES = ['pending', 'active', 'expired', 'suspended', 'consumed', 'reserved'];

    public function __construct(private readonly BusinessActivityLogger $activityLogger) {}

    public function activate(ServiceUser $assignment, ?Payment $payment = null, ?CarbonInterface $at = null): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $payment, $at): ServiceUser {
            $assignment = $this->locked($assignment);
            $at ??= now();

            if (! in_array($assignment->status, ['pending', 'reserved', 'expired'], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending, reserved, or expired assignments can be activated.']);
            }

            $service = $assignment->service;

            if ($payment !== null) {
                if (
                    $payment->status !== Payment::STATUS_CONFIRMED
                    || (int) $payment->organization_id !== (int) $service->organization_id
                    || $payment->model_type !== Payment::MODEL_TYPE_SERVICE_USER
                    || (int) $payment->model_id !== (int) $assignment->id
                ) {
                    throw ValidationException::withMessages(['payment_id' => 'Payment must be confirmed and linked to this service assignment.']);
                }
            } elseif ((float) $service->price > 0) {
                throw ValidationException::withMessages(['payment_id' => 'Payment is required to activate a paid service assignment.']);
            }

            $start = $assignment->start_date ?? $at;
            $expiresAt = match ($service->expiration_rule) {
                'none' => null,
                'fixed_date' => $service->fixed_expires_at,
                default => $service->duration_days === null ? null : $start->copy()->addDays($service->duration_days),
            };
            $status = $start->isAfter($at) ? 'reserved' : 'active';

            $oldValues = $assignment->only(['status', 'start_date', 'expires_at', 'activated_at', 'activation_payment_id']);
            $assignment->forceFill([
                'status' => $status,
                'start_date' => $start,
                'expires_at' => $expiresAt,
                'activated_at' => $at,
                'activation_payment_id' => $payment?->id,
                'suspended_at' => null,
                'resume_at' => null,
                'status_reason' => null,
            ])->saveQuietly();

            $assignmentId = (int) $assignment->id;
            $actorId = $payment?->admin_id;
            DB::afterCommit(function () use ($assignmentId, $actorId, $oldValues): void {
                $activatedAssignment = ServiceUser::query()->with(['service', 'user'])->find($assignmentId);
                if ($activatedAssignment === null) {
                    return;
                }

                NotificationRequested::dispatch(
                    $activatedAssignment->user,
                    NotificationRequested::SERVICE_ACTIVATED,
                    "service.activated:{$activatedAssignment->id}:{$activatedAssignment->activated_at?->timestamp}",
                    ['service' => $activatedAssignment->service->name],
                );

                $this->activityLogger->record(
                    AuditLog::SERVICE_ACTIVATED,
                    $activatedAssignment->user,
                    $activatedAssignment,
                    $oldValues,
                    $activatedAssignment->only(['status', 'start_date', 'expires_at', 'activated_at', 'activation_payment_id']),
                    $actorId === null ? null : User::query()->withoutGlobalScopes()->find($actorId),
                );
            });

            return $assignment->refresh();
        });
    }

    public function revertActivation(ServiceUser $assignment): ServiceUser
    {
        return DB::transaction(function () use ($assignment): ServiceUser {
            $assignment = $this->locked($assignment);

            $replacementPaymentId = Payment::query()
                ->where('model_type', Payment::MODEL_TYPE_SERVICE_USER)
                ->where('model_id', $assignment->id)
                ->where('status', Payment::STATUS_CONFIRMED)
                ->latest('confirmed_at')
                ->value('id');

            if ($replacementPaymentId !== null) {
                $assignment->forceFill(['activation_payment_id' => $replacementPaymentId])->saveQuietly();

                return $assignment->refresh();
            }

            if (in_array($assignment->status, ['active', 'reserved', 'suspended'], true)) {
                $assignment->forceFill([
                    'status' => 'pending',
                    'expires_at' => null,
                    'activated_at' => null,
                    'activation_payment_id' => null,
                    'suspended_at' => null,
                    'resume_at' => null,
                    'status_reason' => null,
                ])->saveQuietly();
            }

            return $assignment->refresh();
        });
    }

    public function suspend(ServiceUser $assignment, string $reason, ?CarbonInterface $resumeAt = null): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $reason, $resumeAt): ServiceUser {
            $assignment = $this->locked($assignment);
            if (! in_array($assignment->status, ['active', 'reserved'], true)) {
                throw ValidationException::withMessages(['status' => 'Only active or reserved assignments can be suspended.']);
            }
            $assignment->update(['status' => 'suspended', 'suspended_at' => now(), 'resume_at' => $resumeAt, 'status_reason' => $reason]);

            return $assignment->refresh();
        });
    }

    public function resume(ServiceUser $assignment, ?CarbonInterface $at = null): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $at): ServiceUser {
            $assignment = $this->locked($assignment);
            $at ??= now();
            if ($assignment->status !== 'suspended') {
                throw ValidationException::withMessages(['status' => 'Only suspended assignments can be resumed.']);
            }
            if ($this->isExpired($assignment, $at)) {
                $status = 'expired';
            } elseif ($assignment->service->max_accesses !== null && $assignment->accesses_used >= $assignment->service->max_accesses) {
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

    /**
     * Refresh the service statuses of the given users with a single query,
     * touching only the assignments whose status would actually change.
     *
     * @param  iterable<User>|User  $users
     */
    public function refreshForUsers(iterable|User $users, ?CarbonInterface $at = null): void
    {
        $at ??= now();
        $userIds = collect($users instanceof User ? [$users] : $users)
            ->map(fn (User $user) => $user->getKey())
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        ServiceUser::query()
            ->with('service')
            // Inlined integers: faster than bindings, and not limited to 2100 on SQL Server.
            ->whereIntegerInRaw('user_id', $userIds->all())
            ->whereIn('status', ['active', 'reserved', 'suspended'])
            ->get()
            ->filter(fn (ServiceUser $assignment): bool => $this->needsRefresh($assignment, $at))
            ->each(fn (ServiceUser $assignment) => $this->refresh($assignment, $at));
    }

    /**
     * Keep only the users with (or without) an active service. Stored statuses may be
     * stale, so this mirrors the rules of refresh() and resume(); keep them in sync.
     */
    public function filterUsersByActiveService(Builder $users, bool $hasActiveService, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();
        // One date condition per distinct grace period avoids driver-specific date arithmetic.
        $gracePeriods = Service::query()->where('is_active', true)->distinct()->pluck('grace_period_days');

        return $users->whereHas('services', function (Builder $query) use ($at, $gracePeriods): void {
            $query->where('services.is_active', true)
                ->where(function (Builder $query) use ($at): void {
                    $query->whereIn('service_user.status', ['active', 'reserved'])
                        ->orWhere(function (Builder $query) use ($at): void {
                            $query->where('service_user.status', 'suspended')
                                ->where('service_user.resume_at', '<=', $at);
                        });
                })
                ->where(function (Builder $query) use ($at): void {
                    $query->whereNull('service_user.start_date')
                        ->orWhere('service_user.start_date', '<=', $at);
                })
                ->where(function (Builder $query) use ($at, $gracePeriods): void {
                    $query->whereNull('service_user.expires_at');

                    foreach ($gracePeriods as $days) {
                        $query->orWhere(function (Builder $query) use ($at, $days): void {
                            $query->where('services.grace_period_days', $days)
                                ->where('service_user.expires_at', '>=', $at->copy()->subDays($days));
                        });
                    }
                })
                ->where(function (Builder $query): void {
                    $query->whereNull('services.max_accesses')
                        ->orWhereColumn('service_user.accesses_used', '<', 'services.max_accesses');
                });
        }, $hasActiveService ? '>=' : '<', 1);
    }

    public function needsRefresh(ServiceUser $assignment, CarbonInterface $at): bool
    {
        if ($assignment->status === 'suspended') {
            return (bool) $assignment->resume_at?->lte($at);
        }

        if (! in_array($assignment->status, ['active', 'reserved'], true)) {
            return false;
        }

        return $this->computedStatus($assignment, $at) !== $assignment->status;
    }

    private function computedStatus(ServiceUser $assignment, CarbonInterface $at): string
    {
        $status = $assignment->start_date?->isAfter($at) ? 'reserved' : 'active';
        if ($this->isExpired($assignment, $at)) {
            $status = 'expired';
        } elseif ($assignment->service->max_accesses !== null && $assignment->accesses_used >= $assignment->service->max_accesses) {
            $status = 'consumed';
        }

        return $status;
    }

    public function refresh(ServiceUser $assignment, ?CarbonInterface $at = null): ServiceUser
    {
        $at ??= now();
        if ($assignment->status === 'suspended' && $assignment->resume_at?->lte($at)) {
            return $this->resume($assignment, $at);
        }
        if (in_array($assignment->status, ['active', 'reserved'], true)) {
            $status = $this->computedStatus($assignment, $at);
            if ($status !== $assignment->status) {
                $assignment->update(['status' => $status]);
            }
        }

        return $assignment->refresh();
    }

    public function consumeAccess(ServiceUser $assignment, ?CarbonInterface $at = null): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $at): ServiceUser {
            $assignment = $this->refresh($this->locked($assignment), $at);
            if ($assignment->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'The service is not available for access.']);
            }
            $assignment->increment('accesses_used');

            return $this->refresh($assignment, $at);
        });
    }

    private function isExpired(ServiceUser $assignment, CarbonInterface $at): bool
    {
        if ($assignment->expires_at === null) {
            return false;
        }

        return $at->isAfter($assignment->expires_at->copy()->addDays($assignment->service->grace_period_days));
    }

    private function locked(ServiceUser $assignment): ServiceUser
    {
        return ServiceUser::query()->with('service')->lockForUpdate()->findOrFail($assignment->id);
    }
}
