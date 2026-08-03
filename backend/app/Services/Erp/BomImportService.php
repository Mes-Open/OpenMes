<?php

namespace App\Services\Erp;

use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Services\Erp\Concerns\ReportsImportRows;
use Illuminate\Support\Facades\DB;

/**
 * ERP → OpenMES recipe (bill of materials) import (#212).
 *
 * ERPs express a recipe as component quantities per ONE unit of the finished
 * product — total consumption is that times the produced quantity — which is
 * exactly what bom_items.quantity_per_unit means, so quantities pass through
 * unscaled.
 *
 * A recipe attaches to the product's process template (BOM items hang off the
 * template, not the product), resolved by version or the active one. In the
 * default `replace` mode the imported component list becomes the template's
 * complete BOM: components the ERP no longer lists are removed, so a recipe
 * change in the ERP does not leave orphaned ingredients behind.
 */
class BomImportService
{
    use ReportsImportRows;

    /**
     * @param  array<int, array<string, mixed>>  $rows  one row per product recipe
     * @param  'replace'|'merge'  $mode
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function import(array $rows, string $mode = 'replace'): array
    {
        return $this->processRows($rows, function (array $row) use ($mode) {
            $productCode = trim((string) ($row['product_type_code'] ?? ''));

            if ($productCode === '') {
                return $this->error('product_type_code', __('Product code is required'));
            }

            $product = ProductType::where('code', $productCode)->first();

            if (! $product) {
                return $this->error('product_type_code', __("Product ':code' not found", ['code' => $productCode]));
            }

            $template = $this->resolveTemplate($product, $row['process_template_version'] ?? null);

            if (! $template) {
                return $this->error('process_template_version', __("Product ':code' has no process template to attach a recipe to", [
                    'code' => $productCode,
                ]));
            }

            $components = $row['components'] ?? [];

            if (! is_array($components) || $components === []) {
                return $this->error('components', __('A recipe needs at least one component'));
            }

            // Resolve every component before writing anything: a recipe with one
            // unknown material is reported as a single failed row, never applied
            // half-way.
            $resolved = [];

            foreach ($components as $position => $component) {
                $materialCode = trim((string) ($component['material_code'] ?? ''));
                $material = Material::where('code', $materialCode)->first();

                if (! $material) {
                    return $this->error('components', __("Material ':code' not found", ['code' => $materialCode]));
                }

                $quantity = (float) ($component['quantity_per_unit'] ?? 0);

                if ($quantity <= 0) {
                    return $this->error('components', __("Quantity per unit for ':code' must be greater than 0", [
                        'code' => $materialCode,
                    ]));
                }

                if (isset($resolved[$material->id])) {
                    return $this->error('components', __("Material ':code' is listed twice in one recipe", [
                        'code' => $materialCode,
                    ]));
                }

                $resolved[$material->id] = [
                    'quantity_per_unit' => $quantity,
                    'scrap_percentage' => (float) ($component['scrap_percentage'] ?? 0),
                    'notes' => $component['notes'] ?? null,
                    'sort_order' => (int) ($component['sort_order'] ?? $position),
                ];
            }

            $hadItems = $template->bomItems()->exists();

            DB::transaction(function () use ($template, $resolved, $mode) {
                foreach ($resolved as $materialId => $attributes) {
                    BomItem::updateOrCreate(
                        ['process_template_id' => $template->id, 'material_id' => $materialId],
                        $attributes,
                    );
                }

                if ($mode === 'replace') {
                    $template->bomItems()
                        ->whereNotIn('material_id', array_keys($resolved))
                        ->get()
                        ->each
                        ->delete();
                }
            });

            return $hadItems ? $this->updated() : $this->created();
        });
    }

    /**
     * The template a recipe belongs to: an explicitly requested version, else the
     * newest active one, else the newest of any state (a draft template is still
     * a legitimate place for a recipe).
     */
    private function resolveTemplate(ProductType $product, mixed $version): ?ProcessTemplate
    {
        $query = ProcessTemplate::where('product_type_id', $product->id);

        if ($version !== null && $version !== '') {
            return $query->where('version', (int) $version)->first();
        }

        return (clone $query)->where('is_active', true)->orderByDesc('version')->first()
            ?? $query->orderByDesc('version')->first();
    }
}
