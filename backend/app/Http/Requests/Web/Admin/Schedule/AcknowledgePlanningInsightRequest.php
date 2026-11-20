<?php

namespace App\Http\Requests\Web\Admin\Schedule;

use App\Models\PlanningInsightAcknowledgement;

class AcknowledgePlanningInsightRequest extends PlanningInsightRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PlanningInsightAcknowledgement::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + $this->insightKeyRules();
    }
}
