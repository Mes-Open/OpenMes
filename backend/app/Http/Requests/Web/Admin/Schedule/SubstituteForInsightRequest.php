<?php

namespace App\Http\Requests\Web\Admin\Schedule;

/**
 * "Suggest substitute": assign one of the operators the insight suggests to
 * its line. The server checks the person is still on the suggested list.
 */
class SubstituteForInsightRequest extends AcknowledgePlanningInsightRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + [
            'user_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
        ];
    }
}
