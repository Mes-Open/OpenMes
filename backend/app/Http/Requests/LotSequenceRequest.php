<?php

namespace App\Http\Requests;

use App\Models\LotSequence;
use App\Rules\ValidLotPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared store/update validation for LOT sequences (web admin).
 * Two modes: token pattern (e.g. "test-[date]-[seq]-[hour]") or legacy
 * prefix/suffix — prefix is only required when no pattern is given.
 */
class LotSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + admin middleware
    }

    public function rules(): array
    {
        $sequenceId = $this->route('lot_sequence')?->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'purpose' => ['nullable', Rule::in(LotSequence::PURPOSES)],
            // One sequence per (product, purpose): a product may number its LOTs,
            // its process serials and its unit serials with three sequences.
            'product_type_id' => [
                'nullable',
                'exists:product_types,id',
                Rule::unique('lot_sequences', 'product_type_id')
                    ->where('purpose', $this->input('purpose') ?: LotSequence::PURPOSE_LOT)
                    ->ignore($sequenceId),
            ],
            'pattern' => ['nullable', 'string', 'max:100', new ValidLotPattern],
            'prefix' => ['required_without:pattern', 'nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'pad_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'year_prefix' => ['nullable', 'boolean'],
            'reset_period' => ['nullable', Rule::in(LotSequence::RESET_PERIODS)],
        ];
    }

    public function messages(): array
    {
        return [
            'product_type_id.unique' => __('This product already has a sequence of this kind. Edit that one, or pick another kind under "Numbers".'),
        ];
    }

    /**
     * Normalized payload with defaults applied.
     */
    public function payload(): array
    {
        $validated = $this->validated();

        $validated['year_prefix'] = $this->boolean('year_prefix');
        $validated['pad_size'] = $validated['pad_size'] ?? 4;
        $validated['reset_period'] = $validated['reset_period'] ?? 'none';
        $validated['purpose'] = $validated['purpose'] ?? LotSequence::PURPOSE_LOT;
        // Legacy column is NOT NULL; pattern mode doesn't use it.
        $validated['prefix'] = $validated['prefix'] ?? '';

        return $validated;
    }
}
