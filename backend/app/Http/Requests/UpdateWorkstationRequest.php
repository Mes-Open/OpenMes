<?php

namespace App\Http\Requests;

use App\Services\CustomFieldService;
use Illuminate\Foundation\Http\FormRequest;

/** Editing a workstation, including who works at it and which operator screens it shows. */
class UpdateWorkstationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route group already requires the admin tab
    }

    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:workstations,code,'.$this->route('workstation')->id],
            'name' => ['required', 'string', 'max:255'],
            'workstation_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'worker_ids' => ['nullable', 'array'],
            'worker_ids.*' => ['exists:workers,id'],
            ...StoreWorkstationRequest::operatorScreenRules(),
        ], app(CustomFieldService::class)->rules('workstation'));
    }

    public function attributes(): array
    {
        return app(CustomFieldService::class)->attributeNames('workstation');
    }
}
