<?php

namespace App\Http\Requests\Web\Admin;

use App\Support\PriorityBandRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PriorityBandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bands' => ['required', 'array', 'size:4'],
            'bands.*' => ['required', 'integer', 'min:-1000', 'max:100000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bands = $this->input('bands');
            if (is_array($bands) && ! PriorityBandRegistry::isValid($bands)) {
                $validator->errors()->add('bands', __('Band thresholds must be four strictly ascending numbers.'));
            }
        });
    }
}
