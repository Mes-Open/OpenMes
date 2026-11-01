<?php

namespace App\Http\Requests;

use App\Support\ViewAs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The administrator's view switcher: which role's interface to show.
 *
 * Only an Admin may switch — checked here on the server, not in the bar, so an
 * operator or a shared workstation login can never be put into another view.
 */
class SwitchViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ViewAs::available($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'view' => ['required', 'string', Rule::in(ViewAs::VIEWS)],
        ];
    }
}
