<?php

namespace App\Payments\Services;

use App\Payments\Models\Payment;
use App\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PaymentService
{
    public function create(array $data, User $admin): Payment
    {
        $data['model_type'] ??= Payment::MODEL_TYPE_SUBSCRIPTION_USER;
        $this->ensureSupportedModelType($data['model_type']);
        $this->ensurePayableBelongsToOrganization($data['model_type'], (int) $data['model_id'], (int) $admin->organization_id);

        return DB::transaction(function () use ($data, $admin): Payment {
            $data['admin_id'] = $admin->id;
            $data['organization_id'] = $admin->organization_id;
            $data['location_id'] = $admin->locations()->withoutGlobalScopes()->value('locations.id');
            $data['external_reference'] ??= (string) Str::uuid();
            $data['provider'] ??= config('services.payments.provider');
            $data['status'] = $data['payment_type_id'] === Payment::TYPE_CASH
                ? Payment::STATUS_CONFIRMED
                : Payment::STATUS_INITIATED;
            $data['confirmed_at'] = $data['status'] === Payment::STATUS_CONFIRMED ? now() : null;

            $payment = Payment::query()->create($data);

            if ($payment->status === Payment::STATUS_CONFIRMED) {
                $this->completeConfirmation($payment);
            }

            return $payment->refresh();
        });
    }

    public function attachModel(Payment $payment, string $modelType, int $modelId): Payment
    {
        $this->ensureSupportedModelType($modelType);
        $this->ensurePayableBelongsToOrganization($modelType, $modelId, (int) $payment->organization_id);

        $payment->update(['model_type' => $modelType, 'model_id' => $modelId]);

        return $payment;
    }

    public function processCallback(array $payload): Payment
    {
        return DB::transaction(function () use ($payload): Payment {
            $payment = Payment::query()
                ->where('external_reference', $payload['external_reference'])
                ->lockForUpdate()
                ->firstOrFail();

            $status = $payload['status'];
            if ($payment->status === $status || ($this->isTerminal($payment->status) && ! ($payment->status === Payment::STATUS_CONFIRMED && $status === Payment::STATUS_REFUNDED))) {
                return $payment;
            }

            $updates = [
                'status' => $status,
                'provider_transaction_id' => $payload['transaction_id'] ?? $payment->provider_transaction_id,
                'provider_payload' => $payload,
            ];

            if ($status === Payment::STATUS_CONFIRMED) {
                $updates['confirmed_at'] = now();
            } elseif ($status === Payment::STATUS_FAILED) {
                $updates['failed_at'] = now();
                $updates['failure_reason'] = $payload['failure_reason'] ?? null;
            } elseif ($status === Payment::STATUS_REFUNDED) {
                $updates['refunded_at'] = now();
            } elseif ($status === Payment::STATUS_CANCELLED) {
                $updates['cancelled_at'] = now();
            }

            $payment->update($updates);
            if ($status === Payment::STATUS_CONFIRMED) {
                $this->completeConfirmation($payment);
            }

            return $payment->refresh();
        });
    }

    private function completeConfirmation(Payment $payment): void
    {
        if ($payment->receipt_number === null) {
            $payment->update(['receipt_number' => sprintf('RCPT-%d-%s-%06d', $payment->organization_id, now()->format('Y'), $payment->id)]);
        }

        if ($payment->model_type !== Payment::MODEL_TYPE_SUBSCRIPTION_USER) {
            return;
        }

        $assignment = DB::table('subscription_user')->where('id', $payment->model_id)->first();
        $duration = DB::table('subscriptions')->where('id', $assignment->subscription_id)->value('duration_days');
        DB::table('subscription_user')->where('id', $payment->model_id)->update([
            'start_date' => now()->toDateString(),
            'expires_at' => $duration ? now()->addDays((int) $duration)->toDateString() : null,
            'updated_at' => now(),
        ]);
    }

    private function ensurePayableBelongsToOrganization(string $type, int $id, int $organizationId): void
    {
        $matches = $type === Payment::MODEL_TYPE_SUBSCRIPTION_USER
            ? DB::table('subscription_user')->join('subscriptions', 'subscriptions.id', '=', 'subscription_user.subscription_id')->where('subscription_user.id', $id)->where('subscriptions.organization_id', $organizationId)->exists()
            : DB::table('event_occurrence_user')->join('event_occurrences', 'event_occurrences.id', '=', 'event_occurrence_user.event_occurrence_id')->where('event_occurrence_user.id', $id)->where('event_occurrences.organization_id', $organizationId)->exists();

        if (! $matches) {
            throw ValidationException::withMessages(['model_id' => 'The payable object does not belong to the authenticated organization.']);
        }
    }

    private function isTerminal(string $status): bool
    {
        return in_array($status, [Payment::STATUS_CONFIRMED, Payment::STATUS_FAILED, Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED], true);
    }

    private function ensureSupportedModelType(string $modelType): void
    {
        if (! in_array($modelType, Payment::MODEL_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported payable model type.');
        }
    }
}
