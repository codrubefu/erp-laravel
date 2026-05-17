<?php

namespace App\Articles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'groups' => ['sometimes', 'array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'locations' => ['sometimes', 'array'],
            'locations.*' => ['integer', 'exists:locations,id'],
        ];
    }
}