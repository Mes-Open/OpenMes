<?php

namespace App\Http\Requests\Operator;

use App\Models\TemplateStepOutput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Operator records the value of a typed step output. The rule set follows the
 * output's value type (number, boolean, date, select, picture, text). Route
 * middleware gates the operator area; the controller checks the step belongs to
 * the selected line, the output belongs to the step and the step is running.
 */
class RecordStepOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var TemplateStepOutput $output */
        $output = $this->route('output');

        return [
            'value' => match ($output->value_type) {
                TemplateStepOutput::TYPE_PICTURE => ['required', 'image', 'max:10240'],
                TemplateStepOutput::TYPE_NUMBER => ['required', 'numeric'],
                // Required: without it a missing value would silently record `false`
                // and satisfy the required-output gate. The UI posts '1'/'0'.
                TemplateStepOutput::TYPE_BOOLEAN => ['required', 'boolean'],
                TemplateStepOutput::TYPE_DATE => ['required', 'date'],
                TemplateStepOutput::TYPE_SELECT => ['required', Rule::in($output->options ?? [])],
                default => ['required', 'string', 'max:5000'],
            },
        ];
    }
}
