<?php

namespace App\Http\Requests;

use App\Support\UnitSerialisation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Starting a unit on its process serial at the line's first station: the PSN
 * scanned off a pre-printed label, or none to take the next one from the
 * product's sequence (then the work order is required - its product owns it).
 */
class StartUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['psn' => app(UnitSerialisation::class)->normalize($this->input('psn'))]);
    }

    public function rules(): array
    {
        $psnRules = app(UnitSerialisation::class)->psnRules();
        $psnRules[0] = 'nullable'; // absent = issue the next number

        return [
            'psn' => $psnRules,
            'work_order_id' => ['required_without:psn', 'nullable', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
        ];
    }
}
