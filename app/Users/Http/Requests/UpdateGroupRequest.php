<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('groups', 'name')->ignore($group?->id),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'right_ids' => ['sometimes', 'array'],
            'right_ids.*' => ['integer', 'exists:rights,id'],
        ];
    }
}
