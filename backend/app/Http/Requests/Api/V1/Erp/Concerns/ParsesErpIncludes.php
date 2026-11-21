<?php

namespace App\Http\Requests\Api\V1\Erp\Concerns;

/**
 * Optional `?include=` on the ERP work-order export: a comma-separated subset
 * of the settlement sections (consumption, scrap, costs). Anything else is a
 * 422 on `include`, so a typo never silently returns a smaller payload.
 */
trait ParsesErpIncludes
{
    /** Sections an ERP may request on top of the base work-order shape. */
    public const ERP_INCLUDES = ['consumption', 'scrap', 'costs'];

    protected function includeRules(): array
    {
        $one = implode('|', self::ERP_INCLUDES);

        return [
            'include' => ['nullable', 'string', 'max:100', "regex:/^\\s*({$one})(\\s*,\\s*({$one}))*\\s*$/"],
        ];
    }

    /**
     * The validated, de-duplicated list of requested sections ([] when absent).
     *
     * @return list<string>
     */
    public function includes(): array
    {
        $raw = (string) $this->input('include', '');

        return array_values(array_intersect(
            self::ERP_INCLUDES,
            array_map('trim', explode(',', $raw)),
        ));
    }
}
