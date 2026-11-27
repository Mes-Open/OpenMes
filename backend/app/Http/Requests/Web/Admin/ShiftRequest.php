<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Requests\Concerns\AllowsModuleFields;
use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Creating or editing a shift from the admin screens. */
class ShiftRequest extends FormRequest
{
    use AllowsModuleFields;
    use MergesCustomFieldRules;

    public function authorize(): bool
    {
        // Route middleware already restricts admin routes to the Admin role.
        return true;
    }

    protected function customFieldEntityType(): string
    {
        return 'shift';
    }

    /** A missing checkbox on create means "not filled in", not "unchecked". */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    public function rules(): array
    {
        $shift = $this->route('shift');

        return $this->withModuleFields(array_merge([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:10', Rule::unique('shifts', 'code')->ignore($shift instanceof Shift ? $shift->id : null)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'line_id' => ['nullable', 'exists:lines,id'],
            'is_active' => ['boolean'],
        ], $this->customFieldRules()));
    }

    /** Fields an installed module adds to the shift form (e.g. the plant). */
    protected function moduleFieldFilter(): string
    {
        return 'validation.admin.shifts';
    }

    protected function moduleFieldContext(): array
    {
        return ['action' => $this->moduleFieldAction(), 'shift' => $this->route('shift')];
    }
}
