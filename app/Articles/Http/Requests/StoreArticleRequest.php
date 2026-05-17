<?php

namespace App\Articles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'groups' => ['sometimes', 'array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'locations' => ['sometimes', 'array'],
            'locations.*' => ['integer', 'exists:locations,id'],
        ];
    }
}