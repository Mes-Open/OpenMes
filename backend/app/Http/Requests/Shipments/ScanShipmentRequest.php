<?php

namespace App\Http\Requests\Shipments;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

/** One label scanned at the dock. */
class ScanShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('load', $this->route('shipment')) ?? false;
    }

    /** A keyboard-wedge scanner may send CR/LF/GS around the code; strip them before the charset check. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:'.Shipment::IDENTIFIER_MAX, 'regex:'.Shipment::IDENTIFIER_REGEX],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => __('Not a label number: use letters, digits and - _ / . only.')];
    }
}
