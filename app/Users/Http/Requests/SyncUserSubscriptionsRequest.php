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
            'subscription_ids' => ['required_without:subscriptions', 'array'],
            'subscription_ids.*' => ['integer', 'exists:subscriptions,id'],
            'subscriptions' => ['required_without:subscription_ids', 'array'],
            'subscriptions.*.id' => ['required_with:subscriptions', 'integer', 'exists:subscriptions,id'],
            'subscriptions.*.start_date' => ['sometimes', 'date'],
        ];
    }
}
