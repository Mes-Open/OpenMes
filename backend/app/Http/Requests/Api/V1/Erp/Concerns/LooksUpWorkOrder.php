<?php

namespace App\Http\Requests\Api\V1\Erp\Concerns;

use Illuminate\Validation\Rule;

/**
 * `?by=` for the ERP endpoints addressing one work order: `id` (the default, so
 * existing callers are unchanged) or `order_no`, the number the ERP itself knows.
 */
trait LooksUpWorkOrder
{
    public const LOOKUPS = ['id', 'order_no'];

    /** @return array<string, array<int, mixed>> */
    protected function lookupRules(): array
    {
        return [
            'by' => ['nullable', 'string', Rule::in(self::LOOKUPS)],
        ];
    }

    public function lookup(): string
    {
        return (string) ($this->query('by') ?? 'id');
    }
}
