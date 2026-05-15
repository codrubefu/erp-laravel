<?php

namespace App\Subscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'max:3'],
            'billing_interval' => ['sometimes', 'required', Rule::in(['monthly', 'yearly'])],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
