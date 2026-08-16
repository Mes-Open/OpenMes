<?php

namespace App\Http\Requests\Api\V1;

use App\Models\MaterialLot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Change a material lot's status (#99) — released / quarantine / rejected. The
 * route is gated to Supervisor|Admin. A reason is required when quarantining or
 * rejecting, so the disposition is explainable in the audit trail.
 */
class ReclassifyLotStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', Rule::in([
                MaterialLot::STATUS_RELEASED,
                MaterialLot::STATUS_QUARANTINE,
                MaterialLot::STATUS_REJECTED,
            ])],
            'reason' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => in_array($this->input('to_status'), [
                    MaterialLot::STATUS_QUARANTINE,
                    MaterialLot::STATUS_REJECTED,
                ], true)),
            ],
        ];
    }
}
