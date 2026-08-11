<?php

namespace App\Http\Requests;

use App\Models\ProductionDowntime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Assigning a cause to an automatically-logged stop. The reason must be a real,
 * active, non-placeholder reason: replacing an AUTO-* code with another AUTO-*
 * code would clear the "needs a cause" flag without anyone having decided
 * anything.
 *
 * The stop itself is validated too, not just the reason. This endpoint takes an
 * id, and the screen it serves deliberately stops refreshing while a drawer is
 * open — so the id in hand can easily describe a stop that somebody else has
 * already explained since it was read.
 */
class ClassifyDowntimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Supervisor']) ?? false;
    }

    public function rules(): array
    {
        return [
            'downtime_reason_id' => [
                'required',
                'integer',
                Rule::exists('downtime_reasons', 'id')
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->where('code', 'not like', 'AUTO-%')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            // The value the client's snapshot held. Absent means "I believe
            // this is unclassified"; a mismatch means the drawer is stale.
            'seen_classified_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $downtime = $this->route('downtime');

            if (! $downtime instanceof ProductionDowntime) {
                return;
            }

            // The monitor explains machine time. A stop with no station is a
            // line-level record from somewhere else in the app, and this
            // endpoint has no business rewriting it.
            if (! $downtime->workstation_id) {
                $validator->errors()->add('downtime', __('This stop is not attached to a workstation.'));

                return;
            }

            // Last-writer-wins is the wrong rule for a decision two people can
            // reach differently: A's "Tool change" would vanish under B's
            // "Material shortage" with nothing recorded that it ever changed.
            // Whoever is second is told to look again.
            $seen = $this->date('seen_classified_at');
            $actual = $downtime->classified_at;

            if ($actual?->toIso8601String() !== $seen?->toIso8601String()) {
                $validator->errors()->add('downtime', __('Somebody else has already given this stop a cause. Refresh to see it.'));
            }
        });
    }
}
