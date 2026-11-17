<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Services\Erp\ProcessTemplateImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an ERP routing (process template) payload.
 *
 * Shape and types only: whether a product type or workstation exists is decided
 * by ProcessTemplateImportService and reported per row (207), so one unknown
 * code does not reject the whole batch.
 *
 * Authorization is handled upstream by the auth.apikey + scope middleware.
 */
class ImportProcessTemplatesRequest extends FormRequest
{
    /** Upper bound per request, paired with the erp-import rate limit. */
    public const MAX_ROWS = 500;

    public const MAX_STEPS = 200;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['nullable', Rule::in(ProcessTemplateImportService::MODES)],

            'templates' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'templates.*.product_type_code' => ['required', 'string', 'max:50'],
            'templates.*.template_name' => ['nullable', 'string', 'max:255'],
            'templates.*.steps' => ['required', 'array', 'min:1', 'max:'.self::MAX_STEPS],
            // ERP operation numbers (10, 20, 30…): they order the steps and are
            // stored as 1..n. Uniqueness per routing is checked in after():
            // `distinct` on a nested wildcard compares across all routings.
            'templates.*.steps.*.step_number' => ['required', 'integer', 'min:1', 'max:1000000'],
            'templates.*.steps.*.name' => ['required', 'string', 'max:255'],
            'templates.*.steps.*.workstation_code' => ['nullable', 'string', 'max:50'],
            'templates.*.steps.*.line_code' => ['nullable', 'string', 'max:50'],
            'templates.*.steps.*.setup_time_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'templates.*.steps.*.run_time_per_unit_minutes' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'templates.*.steps.*.instruction' => ['nullable', 'string', 'max:10000'],
            'templates.*.steps.*.requires_confirmation' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('templates', []) as $t => $template) {
                    $seen = [];

                    $steps = is_array($template) ? ($template['steps'] ?? []) : [];

                    foreach (is_array($steps) ? $steps : [] as $s => $step) {
                        $number = is_array($step) ? ($step['step_number'] ?? null) : null;

                        if (! is_numeric($number)) {
                            continue; // the rules above report it
                        }

                        if (isset($seen[(int) $number])) {
                            $validator->errors()->add(
                                "templates.{$t}.steps.{$s}.step_number",
                                __("Step number ':number' is listed twice", ['number' => (int) $number]),
                            );
                        }

                        $seen[(int) $number] = true;
                    }
                }
            },
        ];
    }

    public function mode(): string
    {
        return $this->input('mode') ?? ProcessTemplateImportService::MODE_NEW_VERSION;
    }
}
