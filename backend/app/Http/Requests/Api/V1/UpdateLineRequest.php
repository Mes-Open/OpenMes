<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $lineId = $this->route('line')?->id;

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('lines', 'code')->ignore($lineId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            // divisions come with an optional module. Where it is absent the
            // table is not there to check against, and silently accepting an
            // id nothing can resolve is worse than refusing it — so the field
            // is prohibited rather than unvalidated.
            'division_id' => ['sometimes', 'nullable', 'integer', \App\Models\Line::hasModuleRelation('division') ? 'exists:divisions,id' : 'prohibited'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
