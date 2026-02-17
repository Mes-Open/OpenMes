<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by the controller/policy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'batch_step_id' => ['nullable', 'integer', 'exists:batch_steps,id'],
            'issue_type_id' => ['required', 'integer', 'exists:issue_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'work_order_id.required' => 'Work order is required',
            'work_order_id.exists' => 'The selected work order does not exist',
            'issue_type_id.required' => 'Issue type is required',
            'issue_type_id.exists' => 'The selected issue type does not exist',
            'title.required' => 'Issue title is required',
            'title.max' => 'Issue title cannot exceed 255 characters',
        ];
    }
}
