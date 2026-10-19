<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The query string the identity provider sends the browser back with. A
 * malformed one is answered on the login screen — "back" would be the
 * provider's own page.
 */
class SsoCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'required_without:error', 'string', 'max:4096'],
            'error' => ['nullable', 'string', 'max:255'],
            'error_description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('login')->with('error', __('Sign-in with the identity provider failed. Please try again.'))
        );
    }
}
