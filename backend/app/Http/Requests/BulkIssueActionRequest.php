<?php

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a bulk issue transition — the alerts page's "Acknowledge all",
 * which acts on every open issue it is listing.
 *
 * As with the work-order twin, whether each issue is in a status the action is
 * legal from is deliberately *not* validated: the set routinely spans OPEN and
 * ACKNOWLEDGED, and failing the whole request for that would make the button
 * useless. The controller skips the ineligible ones and reports the count.
 */
class BulkIssueActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the routes using this are behind the admin/supervisor gates
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['acknowledge', 'resolve'])],
            // Existence is checked once for the whole set (see withValidator) —
            // `Rule::exists` on `ids.*` would issue one SELECT per id.
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** One query for the whole selection instead of one per id. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = array_filter((array) $this->input('ids', []), 'is_numeric');
            if ($ids === []) {
                return;
            }

            // The model's SoftDeletes scope applies, so a trashed issue counts as
            // missing — same outcome the per-id `exists` rule gave.
            $found = array_flip(array_map('intval', Issue::whereIn('id', $ids)->pluck('id')->all()));

            foreach ((array) $this->input('ids', []) as $index => $id) {
                if (! isset($found[(int) $id])) {
                    $validator->errors()->add("ids.{$index}", __('Some of the selected issues no longer exist.'));
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one issue.',
            'ids.max' => 'Too many issues selected at once (max 500).',
        ];
    }
}
