<?php

namespace App\Payments\Services;

use App\Payments\Models\Payment;
use App\Subscription\Models\Subscription;
use App\Users\Models\User;
use InvalidArgumentException;

class PaymentService
{
    public function create(array $data, User $admin): Payment
    {
        $data['model_type'] = $data['model_type'] ?? Payment::MODEL_TYPE_SUBSCRIPTION;
        $data['admin_id'] = $admin->id;

        $this->ensureSupportedModelLink($data['model_type'], $data['subscription_id'] ?? null);

        return Payment::query()->create($data);
    }

    public function attachModel(Payment $payment, string $modelType, int $modelId): Payment
    {
        $this->ensureSupportedModelLink($modelType, $modelId);

        $payment->update([
            'model_type' => $modelType,
            'subscription_id' => $modelType === Payment::MODEL_TYPE_SUBSCRIPTION ? $modelId : null,
        ]);

        return $payment;
    }

    private function ensureSupportedModelLink(string $modelType, ?int $modelId): void
    {
        if ($modelType !== Payment::MODEL_TYPE_SUBSCRIPTION) {
            throw new InvalidArgumentException('Unsupported payable model type.');
        }

        Subscription::query()->findOrFail($modelId);
    }
}
