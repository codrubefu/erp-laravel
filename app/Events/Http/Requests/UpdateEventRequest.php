<?php

namespace App\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'recurrence_type' => ['sometimes', 'required', Rule::in(['once', 'weekly', 'monthly'])],
            'recurrence_days' => ['required_if:recurrence_type,weekly', 'array'],
            'recurrence_days.*' => [Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'monthly_day' => ['required_if:recurrence_type,monthly', 'nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'requires_active_subscription' => ['sometimes', 'boolean'],
            'required_subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'cancelled'])],
        ];
    }
}
