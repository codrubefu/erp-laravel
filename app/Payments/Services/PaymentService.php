<?php

namespace App\Payments\Services;

use App\Payments\Models\Payment;
use App\Users\Models\User;
use InvalidArgumentException;

class PaymentService
{
    public function create(array $data, User $admin): Payment
    {
        $data['model_type'] = $data['model_type'] ?? Payment::MODEL_TYPE_SUBSCRIPTION_USER;
        $data['admin_id'] = $admin->id;

        $this->ensureSupportedModelType($data['model_type']);

        return Payment::query()->create($data);
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
}
