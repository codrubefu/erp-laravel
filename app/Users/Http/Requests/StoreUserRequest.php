<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'location_ids' => ['sometimes', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
            'subscription_ids' => ['sometimes', 'array'],
            'subscription_ids.*' => ['integer', 'exists:subscriptions,id'],
        ];
    }
}
