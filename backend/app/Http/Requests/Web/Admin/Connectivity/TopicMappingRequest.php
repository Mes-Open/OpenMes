<?php

namespace App\Http\Requests\Web\Admin\Connectivity;

use App\Models\TopicMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a topic mapping (store + update). Route middleware gates access; the
 * controller checks the topic/connection ownership. `action_params` arrives as a
 * JSON string from the textarea and must be valid JSON — a malformed value is
 * rejected here so it can never be silently persisted as null.
 */
class TopicMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:255'],
            'field_path' => ['nullable', 'string', 'max:255'],
            'action_type' => ['required', Rule::in(array_keys(TopicMapping::ACTION_LABELS))],
            'action_params' => ['nullable', 'string'],
            'condition_expr' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $raw = $this->input('action_params');
            if ($raw !== null && $raw !== '') {
                json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $v->errors()->add('action_params', __('Invalid JSON in action parameters.'));
                }
            }
        });
    }

    /**
     * Validated payload with `action_params` decoded to an array (or null).
     *
     * @return array<string, mixed>
     */
    public function mappingData(): array
    {
        $data = $this->validated();
        $raw = $data['action_params'] ?? null;
        $data['action_params'] = ($raw !== null && $raw !== '') ? json_decode($raw, true) : null;

        return $data;
    }
}
