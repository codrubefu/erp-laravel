<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserSubscriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_ids' => ['required', 'array'],
            'subscription_ids.*' => ['integer', 'exists:subscriptions,id'],
        ];
    }
}