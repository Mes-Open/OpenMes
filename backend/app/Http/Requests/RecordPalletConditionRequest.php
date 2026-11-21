<?php

namespace App\Http\Requests;

use App\Enums\PalletCondition;
use App\Enums\PalletStatus;
use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Record a pallet's physical condition from the pallet screen: mark it damaged
 * or restore it to good. Either way the note says what was found or what was
 * done about it, so it is always required.
 */
class RecordPalletConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route sits in the admin pallets screen, gated by its tab access
        // (the same users who may edit pallets).
        return true;
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', 'string', Rule::in(PalletCondition::values())],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Pallet $pallet */
                $pallet = $this->route('pallet');
                if ($validator->errors()->has('condition') || ! $pallet instanceof Pallet) {
                    return;
                }
                if ($pallet->status === PalletStatus::Shipped) {
                    $validator->errors()->add('condition', __('The pallet has already been shipped.'));
                } elseif ($this->condition() === PalletCondition::Good && ! $pallet->isDamaged()) {
                    $validator->errors()->add('condition', __('The pallet is already in good condition.'));
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => __('Describe the damage, or what was done to restore the pallet.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'condition' => __('Physical condition'),
            'note' => __('Note'),
        ];
    }

    public function condition(): PalletCondition
    {
        return PalletCondition::from($this->validated('condition'));
    }
}
