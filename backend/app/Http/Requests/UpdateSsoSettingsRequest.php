<?php

namespace App\Http\Requests;

use App\Services\Auth\Sso\OidcClient;
use App\Services\Auth\Sso\SsoException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSsoSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('Admin');
    }

    public function rules(): array
    {
        $scopeToken = '[A-Za-z0-9_:.\/\-]+';

        return [
            'enabled' => ['required', 'boolean'],
            'provider_name' => ['required', 'string', 'max:60'],
            'issuer' => ['nullable', 'required_if:enabled,true,1', 'string', 'max:500', 'url:http,https'],
            'client_id' => ['nullable', 'required_if:enabled,true,1', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:1000'],
            'clear_client_secret' => ['sometimes', 'boolean'],
            'scopes' => ['required', 'string', 'max:255', "regex:/^{$scopeToken}(\\s+{$scopeToken})*$/"],
            'username_claim' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:\-]+$/'],
            'auto_provision' => ['required', 'boolean'],
            // Never Admin: anyone the provider knows would become an administrator.
            'default_role' => [
                'required', 'string', 'not_in:Admin',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'link_by_email' => ['required', 'boolean'],
            'allowed_domains' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'issuer.required_if' => __('The issuer URL is required to enable single sign-on.'),
            'client_id.required_if' => __('The client ID is required to enable single sign-on.'),
            'scopes.regex' => __('Scopes must be separated by spaces.'),
            'username_claim.regex' => __('The claim name may only contain letters, digits, dots, colons, hyphens and underscores.'),
            'default_role.not_in' => __('New accounts cannot be created as administrators.'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! preg_match('/(^|\s)openid(\s|$)/', (string) $this->input('scopes'))) {
                    $validator->errors()->add('scopes', __('The scopes must include "openid".'));
                }

                foreach (preg_split('/[\s,;]+/', (string) $this->input('allowed_domains')) ?: [] as $domain) {
                    $domain = ltrim(trim($domain), '@');
                    if ($domain !== '' && ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                        $validator->errors()->add('allowed_domains', __('":domain" is not a valid domain.', ['domain' => $domain]));
                        break;
                    }
                }

                // Switching it on proves the provider answers — a typo would
                // otherwise only surface on the login screen.
                if ($this->boolean('enabled') && ! $validator->errors()->has('issuer') && $this->filled('issuer')) {
                    try {
                        app(OidcClient::class)->discover((string) $this->input('issuer'), fresh: true);
                    } catch (SsoException) {
                        $validator->errors()->add('issuer', __('Could not load the OpenID Connect configuration from this issuer.'));
                    }
                }
            },
        ];
    }
}
