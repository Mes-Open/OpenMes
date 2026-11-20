<?php

namespace App\Http\Requests\Web\Admin\Schedule;

class UndoPlanningInsightRequest extends PlanningInsightRequest
{
    public function authorize(): bool
    {
        $ack = $this->route('acknowledgement');

        return $ack !== null && ($this->user()?->can('delete', $ack) ?? false);
    }
}
