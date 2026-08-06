<?php

namespace App\Payments\Services;

use App\Payments\Models\Payment;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(private readonly BusinessActivityLogger $activityLogger)
    {
    }

    public function create(array $data, User $admin): Payment
    {
        $data['model_type'] = $data['model_type'] ?? Payment::MODEL_TYPE_SUBSCRIPTION_USER;
        $data['admin_id'] = $admin->id;

        $this->ensureSupportedModelType($data['model_type']);

        $payment = Payment::query()->create($data);
        $subject = $this->subjectFor($payment);
        $this->activityLogger->record(AuditLog::PAYMENT_RECORDED, $subject, $payment, [], [
            'amount' => $payment->amount,
            'payment_type' => $payment->paymentTypeName(),
            'paid_at' => $payment->paid_at,
        ], $admin);

        return $payment;
    }

    public function attachModel(Payment $payment, string $modelType, int $modelId): Payment
    {
        $this->ensureSupportedModelType($modelType);

        $payment->update([
            'model_type' => $modelType,
            'model_id' => $modelId,
        ]);

        return $payment;
    }

    private function ensureSupportedModelType(string $modelType): void
    {
        if (! in_array($modelType, Payment::MODEL_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported payable model type.');
        }
    }

    private function subjectFor(Payment $payment): ?User
    {
        $pivotTable = $payment->model_type === Payment::MODEL_TYPE_SUBSCRIPTION_USER
            ? 'subscription_user'
            : 'event_occurrence_user';
        $userId = DB::table($pivotTable)->where('id', $payment->model_id)->value('user_id');

        return $userId ? User::query()->withoutGlobalScopes()->find($userId) : null;
    }
}
