<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Models\StockDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the warehouse read endpoints (#212): current balances and the
 * stock-document backlog. All optional — an ERP normally polls
 * `?since=<last sync>` (documents) or a single `warehouse` (balances).
 * Authorization is handled by the auth.apikey + scope middleware.
 */
class StockExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'warehouse' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(StockDocument::TYPES)],
            'status' => ['nullable', Rule::in(StockDocument::STATUSES)],
            // Documents the ERP has not acknowledged yet — the usual polling mode.
            'unsynced_only' => ['nullable', 'boolean'],
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
