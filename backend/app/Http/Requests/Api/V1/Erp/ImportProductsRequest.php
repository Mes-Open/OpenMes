<?php

namespace App\Http\Requests\Api\V1\Erp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an ERP product master-data payload (#212).
 *
 * Shape and types only: whether a row belongs in OpenMES at all is decided by
 * ProductImportService (category filter) and reported per row, so a filtered-out
 * item is a "skipped" row, not a 422.
 *
 * Authorization is handled upstream by the auth.apikey + scope middleware.
 */
class ImportProductsRequest extends FormRequest
{
    /** Upper bound per request, paired with the erp-import rate limit. */
    public const MAX_ROWS = 2000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy' => ['nullable', Rule::in(['update_or_create', 'skip_existing', 'error_on_duplicate'])],
            // Import only these ERP classifications (Pantheon acClassif); omit to accept all.
            'only_categories' => ['nullable', 'array', 'max:100'],
            'only_categories.*' => ['string', 'max:100'],
            'external_system' => ['nullable', 'string', 'max:50'],

            'products' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'products.*.code' => ['required', 'string', 'max:50'],
            'products.*.name' => ['nullable', 'string', 'max:255'],
            'products.*.description' => ['nullable', 'string', 'max:2000'],
            'products.*.category' => ['nullable', 'string', 'max:100'],
            'products.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
            'products.*.external_code' => ['nullable', 'string', 'max:100'],
            'products.*.external_system' => ['nullable', 'string', 'max:50'],
            'products.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    public function strategy(): string
    {
        return $this->input('strategy', 'update_or_create');
    }

    /** @return list<string> */
    public function onlyCategories(): array
    {
        return array_values(array_filter((array) $this->input('only_categories', [])));
    }
}
