<?php

namespace App\Http\Requests\Api\V1\Erp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the read-only master-data lists (lines, warehouses, product
 * types). All optional: an ERP polls `?since=<last sync>` for rows changed
 * since then, and `?active=1` / `?active=0` narrows to active or inactive rows
 * (both when absent). Authorization is handled by the auth.apikey + scope
 * middleware.
 */
class MasterDataExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    /**
     * `?limit=` (present but empty) must fall back to the default — (int) '' is 0,
     * and a zero page size is not a page size.
     */
    public function perPage(): int
    {
        $limit = (int) $this->input('limit');

        return $limit > 0 ? $limit : 100;
    }
}
