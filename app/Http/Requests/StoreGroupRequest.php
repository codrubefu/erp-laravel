<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'alpha_dash:ascii', 'unique:groups,name'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'right_ids' => ['sometimes', 'array'],
            'right_ids.*' => ['integer', 'exists:rights,id'],
        ];
    }
}
