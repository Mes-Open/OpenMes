<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:lines,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // divisions come with an optional module. Where it is absent the
            // table is not there to check against, and silently accepting an
            // id nothing can resolve is worse than refusing it — so the field
            // is prohibited rather than unvalidated.
            'division_id' => ['nullable', 'integer', \App\Models\Line::hasModuleRelation('division') ? 'exists:divisions,id' : 'prohibited'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
