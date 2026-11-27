<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AllowsModuleFields;
use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating (POST) or editing (PATCH/PUT) a shift over the API. An edit may
 * send only the fields it changes. Takes the same module fields as the admin
 * form (`validation.admin.shifts`), so a module that sets a shift's plant can
 * be given it here too.
 */
class ShiftRequest extends FormRequest
{
    use AllowsModuleFields;

    /** ShiftPolicy, checked before validation — a non-admin gets 403, not 422. */
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $shift instanceof Shift
            ? ($this->user()?->can('update', $shift) ?? false)
            : ($this->user()?->can('create', Shift::class) ?? false);
    }

    public function rules(): array
    {
        $shift = $this->route('shift');
        $required = $shift instanceof Shift ? ['sometimes', 'required'] : ['required'];
        $optional = $shift instanceof Shift ? ['sometimes', 'nullable'] : ['nullable'];

        return $this->withModuleFields([
            'name' => [...$required, 'string', 'max:100'],
            'code' => [...$optional, 'string', 'max:10', Rule::unique('shifts', 'code')->ignore($shift instanceof Shift ? $shift->id : null)],
            'start_time' => [...$required, 'date_format:H:i'],
            'end_time' => [...$required, 'date_format:H:i'],
            'days_of_week' => [...$optional, 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'line_id' => [...$optional, 'integer', Rule::exists('lines', 'id')->whereNull('deleted_at')],
            'sort_order' => [...$optional, 'integer', 'min:0'],
            'is_active' => [...($shift instanceof Shift ? ['sometimes'] : ['nullable']), 'boolean'],
        ]);
    }

    protected function moduleFieldFilter(): string
    {
        return 'validation.admin.shifts';
    }

    protected function moduleFieldContext(): array
    {
        return ['action' => $this->moduleFieldAction(), 'shift' => $this->route('shift')];
    }
}
