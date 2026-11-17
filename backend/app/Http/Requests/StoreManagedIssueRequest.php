<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An issue filed from the Issues page (admin / supervisor). Same fields as the
 * operator's "Report Issue" dialog, plus the issue's effect on production:
 *
 *   none  — just a record, production carries on
 *   block — the work order's line (or one machine on it) is stopped: a stop is
 *           opened with the chosen downtime reason and every running order on
 *           the line is held in BLOCKED until the issue is resolved
 *   scrap — nothing is blocked; a scrap entry (reason + quantity) is recorded
 *           with the issue
 *
 * The effects are exclusive: an issue either stops the line or writes off
 * material, never both.
 */
class StoreManagedIssueRequest extends FormRequest
{
    use MergesCustomFieldRules;

    public const EFFECT_NONE = 'none';

    public const EFFECT_BLOCK = 'block';

    public const EFFECT_SCRAP = 'scrap';

    public const EFFECTS = [self::EFFECT_NONE, self::EFFECT_BLOCK, self::EFFECT_SCRAP];

    public function authorize(): bool
    {
        return true; // the routes using this are behind the admin/supervisor gates
    }

    protected function customFieldEntityType(): string
    {
        return 'issue';
    }

    public function rules(): array
    {
        return array_merge([
            'work_order_id' => ['required', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
            'issue_type_id' => ['required', 'integer', Rule::exists('issue_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'effect' => ['required', 'string', Rule::in(self::EFFECTS)],
            // The machine on the order's line that is down; none stops the whole line.
            'workstation_id' => [
                'exclude_unless:effect,'.self::EFFECT_BLOCK,
                'nullable',
                'integer',
                Rule::exists('workstations', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'downtime_reason_id' => [
                'exclude_unless:effect,'.self::EFFECT_BLOCK,
                'required',
                'integer',
                Rule::exists('downtime_reasons', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'scrap_reason_id' => [
                'exclude_unless:effect,'.self::EFFECT_SCRAP,
                'required',
                'integer',
                Rule::exists('scrap_reasons', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            // Two decimals: the scrap and non-conforming quantity columns are scale 2.
            'quantity' => ['exclude_unless:effect,'.self::EFFECT_SCRAP, 'required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999'],
        ], $this->customFieldRules());
    }

    /** Rules that depend on the work order's state, not just the submitted values. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('work_order_id')) {
                return;
            }

            $workOrder = WorkOrder::find($this->integer('work_order_id'));

            // Gone between the `exists` rule and here (deleted meanwhile).
            if ($workOrder === null) {
                $validator->errors()->add('work_order_id', __('validation.exists', ['attribute' => 'work order id']));

                return;
            }

            if (in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES, true)) {
                $validator->errors()->add('work_order_id', __('Issues cannot be reported on a finished, rejected or cancelled work order.'));

                return;
            }

            if ($this->input('effect') === self::EFFECT_BLOCK) {
                if ($workOrder->line_id === null) {
                    $validator->errors()->add('effect', __('This work order has no line, so there is no line to block.'));

                    return;
                }

                $workstationId = $this->input('workstation_id');
                if (filled($workstationId) && ! $validator->errors()->has('workstation_id')
                    && Workstation::whereKey((int) $workstationId)->value('line_id') !== $workOrder->line_id) {
                    $validator->errors()->add('workstation_id', __('The machine must be on the work order\'s line.'));
                }
            }

            // A step-ledger order records scrap per step (operator screen); a
            // bare order-level entry would be counted outside that ledger.
            if ($this->input('effect') === self::EFFECT_SCRAP && $workOrder->usesStepLedger()) {
                $validator->errors()->add('effect', __('This work order records scrap on its steps — report it there.'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'scrap_reason_id.required' => __('Select a scrap reason.'),
            'quantity.required' => __('Enter the scrapped quantity.'),
            'downtime_reason_id.required' => __('Select a downtime reason.'),
        ];
    }
}
