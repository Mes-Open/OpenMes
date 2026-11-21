<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveIssueRequest extends FormRequest
{
    /**
     * Resolving an issue needs the `resolve issues` permission (Supervisor and
     * Admin by default) — the same people who can resolve on the web, where the
     * action only exists on the supervisor and admin screens. Operators report
     * and see issues; they do not close the loop on them.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('resolve issues');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'resolution_notes.required' => 'Resolution notes are required to resolve an issue',
            'resolution_notes.max' => 'Resolution notes cannot exceed 5000 characters',
        ];
    }
}
