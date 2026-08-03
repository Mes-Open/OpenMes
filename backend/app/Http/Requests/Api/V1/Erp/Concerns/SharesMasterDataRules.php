<?php

namespace App\Http\Requests\Api\V1\Erp\Concerns;

use Illuminate\Validation\Rule;

/**
 * The envelope every ERP master-data import shares (#212).
 *
 * Products and materials arrive from one ERP item table split by classification,
 * so they take the same strategy, category filter and source-system fields. Kept
 * in one place so a change to the contract cannot land on only one of them.
 */
trait SharesMasterDataRules
{
    /** Import strategies the services understand. */
    public const STRATEGIES = ['update_or_create', 'skip_existing', 'error_on_duplicate'];

    /**
     * Envelope rules — merge into the per-row rules of the concrete request.
     *
     * @return array<string, mixed>
     */
    protected function envelopeRules(): array
    {
        return [
            'strategy' => ['nullable', Rule::in(self::STRATEGIES)],
            // Import only these ERP classifications (Pantheon acClassif); omit to accept all.
            'only_categories' => ['nullable', 'array', 'max:100'],
            'only_categories.*' => ['string', 'max:100'],
            'external_system' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Per-row fields products and materials describe the same way.
     *
     * @return array<string, mixed>
     */
    protected function commonRowRules(string $prefix): array
    {
        return [
            "{$prefix}.code" => ['required', 'string', 'max:50'],
            "{$prefix}.name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.description" => ['nullable', 'string', 'max:2000'],
            "{$prefix}.category" => ['nullable', 'string', 'max:100'],
            "{$prefix}.unit_of_measure" => ['nullable', 'string', 'max:20'],
            "{$prefix}.external_code" => ['nullable', 'string', 'max:100'],
            "{$prefix}.external_system" => ['nullable', 'string', 'max:50'],
            "{$prefix}.is_active" => ['nullable', 'boolean'],
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
