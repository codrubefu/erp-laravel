<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'user_code' => ['nullable', 'string', 'max:32'],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'location_ids' => ['sometimes', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
            'subscription_ids' => ['sometimes', 'array'],
            'subscription_ids.*' => ['integer', 'exists:subscriptions,id'],
            'subscriptions' => ['sometimes', 'array'],
            'subscriptions.*.id' => ['required_with:subscriptions', 'integer', 'exists:subscriptions,id'],
            'subscriptions.*.start_date' => ['sometimes', 'date'],
        ];
    }
}
