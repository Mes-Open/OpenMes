<?php

namespace App\Http\Requests;

use App\Support\InspectionPermissions;
use App\Support\TabRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleTabAccessRequest extends FormRequest
{
    /** Route middleware (role:Admin) gates access. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // access = { "<roleName>": ["orders", "hr", ...] }
            'access' => 'present|array',
            'access.*' => 'array',
            'access.*.*' => ['string', Rule::in(TabRegistry::keys())],
            // grants = { "<roleName>": ["inspections.perform", ...] } — optional;
            // a role left out keeps the quality permissions it has.
            'grants' => 'sometimes|array',
            'grants.*' => 'array',
            // No `distinct`: on a nested wildcard it compares across roles, and
            // two roles holding the same grant is the normal case. A repeat
            // within one role is harmless — the controller de-duplicates.
            'grants.*.*' => ['string', Rule::in(InspectionPermissions::ALL)],
        ];
    }
}
