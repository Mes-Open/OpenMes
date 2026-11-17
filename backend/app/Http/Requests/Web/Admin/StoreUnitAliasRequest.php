<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\UnitAlias;
use App\Scopes\TenantScope;
use App\Support\Units\UnitNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUnitAliasRequest extends FormRequest
{
    /**
     * Letters (any script), digits, then spaces and the punctuation real unit
     * names use: "szt.", "m²", "1/2 pal", "°C", "µm", "kg-net".
     */
    private const UNIT_PATTERN = '/^[\pL\pN][\pL\pN .\/%²³°µ_-]*$/u';

    public function authorize(): bool
    {
        // Route middleware already restricts admin routes to the Admin role.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'alias' => is_string($this->input('alias')) ? trim($this->input('alias')) : $this->input('alias'),
            'unit' => is_string($this->input('unit')) ? trim($this->input('unit')) : $this->input('unit'),
        ]);
    }

    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'max:20', 'regex:'.self::UNIT_PATTERN],
            'unit' => ['required', 'string', 'max:20', 'regex:'.self::UNIT_PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'alias.regex' => __('Use letters, digits, spaces and . / % - _ only.'),
            'unit.regex' => __('Use letters, digits, spaces and . / % - _ only.'),
        ];
    }

    /**
     * Uniqueness is on the lookup key, not the typed text: "SZT." and "szt"
     * are the same alias to an import, so allowing both would make the result
     * depend on which row the database returned last.
     *
     * Checked by hand rather than with Rule::unique because the key is derived
     * from `alias` and the message must land under `alias`. Scoped to the
     * admin's tenant explicitly — the DB index is per tenant.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('alias')) {
                    return;
                }

                $tenantId = $this->user()?->tenant_id;

                $taken = UnitAlias::withoutGlobalScope(TenantScope::class)
                    ->where('alias_normalized', UnitNormalizer::key($this->input('alias')))
                    ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
                    ->when($this->ignoredId(), fn ($q, $id) => $q->whereKeyNot($id))
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('alias', __('This alias is already defined.'));
                }
            },
        ];
    }

    /** The alias being edited, which must not collide with itself. */
    protected function ignoredId(): ?int
    {
        return null;
    }
}
