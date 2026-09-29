<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Scrapping a serialised unit at the station - final, so the reason is mandatory. */
class ScrapUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
