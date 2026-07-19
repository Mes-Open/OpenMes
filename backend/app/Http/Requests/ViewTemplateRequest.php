<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create/update of a line-dashboard view template (column preset) for
 * the mobile API. Mirrors the web Admin\ViewTemplateController rules: a unique
 * name plus 1–20 columns, each with a label, key and source. Authorization is
 * the route's role:Admin middleware.
 */
class ViewTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('viewTemplate')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('view_templates', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'columns' => ['required', 'array', 'min:1', 'max:20'],
            'columns.*.label' => ['required', 'string', 'max:100'],
            'columns.*.key' => ['required', 'string', 'max:100'],
            'columns.*.source' => ['required', 'in:extra_data,field'],
        ];
    }
}
