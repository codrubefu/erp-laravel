<?php

namespace App\Users\Http\Requests;

use App\Users\Services\MemberExportService;
use App\Users\Services\UserListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'distinct', Rule::in(MemberExportService::FIELDS)],
            'search' => ['nullable', 'string', 'max:255'],
            'lang' => ['nullable', Rule::in(MemberExportService::LANGUAGES)],
            ...UserListQuery::FILTER_RULES,
        ];
    }

    public function filters(): array
    {
        return array_filter($this->safe()->only(['search', ...array_keys(UserListQuery::FILTER_RULES)]), filled(...));
    }
}
