<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductRevision;
use App\Models\ProductType;
use App\Services\CustomFieldService;

/**
 * Option lists the work-order create form needs to render its pickers.
 *
 * Shared by every controller that renders that form — the admin list's create
 * modal, the admin create page, and their supervisor twins — so the copies can't
 * drift into offering different options.
 */
trait BuildsWorkOrderFormOptions
{
    /**
     * @return array<string, mixed>
     */
    protected function createFormOptions(CustomFieldService $customFields): array
    {
        return [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bomTemplates' => $this->bomTemplateOptions(),
            'productRevisions' => $this->productRevisionOptions(),
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name', 'tier']),
            'customFields' => $customFields->clientConfig('work_order'),
        ];
    }

    /**
     * Selectable BOMs (process templates) for the work-order forms - every
     * template a user could pick as a variant/alternative bill of materials,
     * newest version first. The forms scope the picker to the order's product
     * type client-side (each option carries its product_type_id).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function bomTemplateOptions()
    {
        return ProcessTemplate::orderBy('product_type_id')
            ->orderByDesc('version')
            ->get(['id', 'name', 'version', 'is_active', 'product_type_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'version' => $t->version,
                'is_active' => (bool) $t->is_active,
                'product_type_id' => $t->product_type_id,
            ]);
    }

    /**
     * Released product revisions (#180) selectable on the work-order forms. Each
     * option carries its product_type_id so the form can scope it to the order's
     * product type.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function productRevisionOptions()
    {
        return ProductRevision::selectable()
            ->orderBy('product_type_id')
            ->orderBy('revision_code')
            ->get(['id', 'revision_code', 'product_type_id'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'revision_code' => $r->revision_code,
                'product_type_id' => $r->product_type_id,
            ]);
    }
}
