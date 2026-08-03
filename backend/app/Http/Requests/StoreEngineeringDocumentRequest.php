<?php

namespace App\Http\Requests;

use App\Models\EngineeringDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEngineeringDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage engineering documents') ?? false;
    }

    public function rules(): array
    {
        $maxKb = (int) (config('engineering.max_upload_bytes', 104857600) / 1024);

        return [
            'file' => ['required', 'file', "max:{$maxKb}"],
            'entity_type' => ['required', 'string', Rule::in(array_keys(EngineeringDocument::ENTITY_TYPES))],
            'entity_id' => ['required', 'integer', 'min:1'],
            'revision' => ['required', 'string', 'max:40'],
            'document_type' => ['nullable', 'string', 'max:64'],
            'entry_point' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function withValidator($validator): void
    {
        // The referenced owner entity must actually exist. Deferred so entity_type
        // is validated (against the allowlist) before we resolve its model class.
        $validator->after(function ($validator) {
            $type = $this->input('entity_type');
            $id = $this->input('entity_id');
            $class = EngineeringDocument::ENTITY_TYPES[$type] ?? null;

            if ($class && $id && ! $class::whereKey($id)->exists()) {
                $validator->errors()->add('entity_id', __('The selected owner does not exist.'));
            }
        });
    }
}
