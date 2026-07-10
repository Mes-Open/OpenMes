<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\TemplateStep;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for adding/updating a process-template step. Route
 * middleware (role:Admin) gates access; the controller checks ownership.
 */
abstract class TemplateStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'instruction' => 'nullable|string',
            'requires_confirmation' => 'boolean',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'workstation_id' => 'nullable|exists:workstations,id',
            'process_segment_id' => 'nullable|exists:process_segments,id',
            'is_optional' => 'boolean',
            'variant_group' => 'nullable|string|max:50',
            'is_default_variant' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $group = $this->input('variant_group');
            $isDefault = $this->boolean('is_default_variant');

            // A default flag only makes sense inside a variant group.
            if ($isDefault && blank($group)) {
                $v->errors()->add('is_default_variant', __('Mark a step as the default variant only when it belongs to a variant group.'));

                return;
            }

            // At most one default per (template, variant group).
            if ($isDefault && filled($group)) {
                $template = $this->route('process_template');
                $currentId = $this->route('step')?->id;

                $clash = TemplateStep::where('process_template_id', $template->id)
                    ->where('variant_group', $group)
                    ->where('is_default_variant', true)
                    ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                    ->exists();

                if ($clash) {
                    $v->errors()->add('is_default_variant', __('This variant group already has a default step.'));
                }
            }
        });
    }
}
