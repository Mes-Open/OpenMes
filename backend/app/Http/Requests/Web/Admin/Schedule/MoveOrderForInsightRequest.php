<?php

namespace App\Http\Requests\Web\Admin\Schedule;

/**
 * "Move order to the first slot with enough people". Only the order is named:
 * the slot is worked out again on the server.
 */
class MoveOrderForInsightRequest extends AcknowledgePlanningInsightRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + [
            'work_order_id' => ['required', 'integer', 'min:1', 'exists:work_orders,id'],
            'force_conflict' => ['sometimes', 'boolean'],
        ];
    }
}
