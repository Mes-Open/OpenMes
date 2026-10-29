<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Models\WorkOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An issue filed from the Issues page (admin / supervisor). Same fields as the
 * operator's "Report Issue" dialog, plus the issue's effect on production:
 *
 *   none  — just a record, production carries on
 *   block — the work order is held in BLOCKED until the issue is resolved
 *   scrap — a scrap entry (reason + quantity) is recorded with the issue
 *
 * The effects are exclusive: an issue either stops the order or writes off
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
            'scrap_reason_id' => [
                'exclude_unless:effect,'.self::EFFECT_SCRAP,
                'required',
                'integer',
                Rule::exists('scrap_reasons', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'quantity' => ['exclude_unless:effect,'.self::EFFECT_SCRAP, 'required', 'numeric', 'min:0.01', 'max:99999999'],
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

            if (in_array($workOrder->status, [WorkOrder::STATUS_DONE, WorkOrder::STATUS_CANCELLED], true)) {
                $validator->errors()->add('work_order_id', __('Issues cannot be reported on a finished or cancelled work order.'));

                return;
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
        ];
    }
}
