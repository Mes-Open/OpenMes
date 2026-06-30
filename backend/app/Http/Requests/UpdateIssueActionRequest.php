<?php

namespace App\Http\Requests;

use App\Models\IssueAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + role:Admin|Supervisor middleware.
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(IssueAction::TYPES)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::in(IssueAction::STATUSES)],
        ];
    }
}
