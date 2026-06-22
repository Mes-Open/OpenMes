<?php

namespace App\Http\Requests;

use App\Enums\IssueDisposition;
use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Set the non-conformance disposition on an issue (#11): the decision plus the
 * non-conforming quantity, root-cause / containment narrative and the
 * responsibility-source classification.
 */
class SetDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + role:Admin|Supervisor middleware.
    }

    public function rules(): array
    {
        return [
            'disposition' => ['required', Rule::in(IssueDisposition::values())],
            'non_conforming_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'containment_action' => ['nullable', 'string', 'max:5000'],
            'nc_source' => ['nullable', Rule::in(Issue::NC_SOURCES)],
        ];
    }
}
