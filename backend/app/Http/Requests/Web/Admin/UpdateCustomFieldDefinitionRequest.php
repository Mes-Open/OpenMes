<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Validation\Rule;

class UpdateCustomFieldDefinitionRequest extends StoreCustomFieldDefinitionRequest
{
    /** Same as store, but ignore the row being edited. */
    protected function uniqueKeyRule(): \Illuminate\Validation\Rules\Unique
    {
        return Rule::unique('custom_field_definitions', 'key')
            ->where('entity_type', $this->input('entity_type'))
            ->ignore($this->route('customField'));
    }
}
