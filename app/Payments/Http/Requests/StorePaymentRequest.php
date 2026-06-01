<?php

namespace App\Payments\Http\Requests;

use App\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'model_type' => $this->input('model_type', Payment::MODEL_TYPE_SUBSCRIPTION),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'payment_type_id' => ['required', 'integer', Rule::in(array_keys(Payment::PAYMENT_TYPES))],
            'model_type' => ['required', 'string', Rule::in([Payment::MODEL_TYPE_SUBSCRIPTION])],
            'subscription_id' => ['required_if:model_type,'.Payment::MODEL_TYPE_SUBSCRIPTION, 'integer', 'exists:subscriptions,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['required', 'date'],
        ];
    }
}
