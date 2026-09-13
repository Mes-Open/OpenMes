<?php

namespace App\Services\Erp;

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
                if (! empty($component['material_code']) && ! empty($component['component_code']) && trim($component['material_code']) !== trim($component['component_code'])) {
                    return $this->error('components', __('Conflicting component codes'));
                }
                $kind = $component['component_kind'] ?? 'material';
                $code = trim((string) ($component['component_code'] ?? $component['material_code'] ?? ''));
                if (! in_array($kind, ['material', 'product_type'], true)) {
                    return $this->error('components', __('Invalid component kind'));
                }
                $entity = $kind === 'material' ? Material::where('code', $code)->first() : ProductType::where('code', $code)->first();
                if (! $entity) {
                    return $this->error('components', __("Component ':code' not found", ['code' => $code]));
                }
                $quantity = (float) ($component['quantity_per_unit'] ?? 0);
                $scrap = (float) ($component['scrap_percentage'] ?? 0);
                if (! is_finite($quantity) || $quantity <= 0 || $quantity > 99999999 || $scrap < 0 || $scrap > 100) {
                    return $this->error('components', __('Invalid component quantity or scrap percentage'));
                }
                $key = $kind.':'.$entity->id;
                if (isset($resolved[$key])) {
                    return $this->error('components', __("Component ':code' is listed twice in one recipe", ['code' => $code]));
                }
                $componentTemplate = null;
                if (! empty($component['component_template_version'])) {
                    if ($kind !== 'product_type') {
                        return $this->error('components', __('A component version requires a product component'));
                    }
                    $componentTemplate = $this->resolveTemplate($entity, $component['component_template_version']);
                    if (! $componentTemplate) {
                        return $this->error('components', __('Component process version not found'));
                    }
                }
                $extra = $component['extra_data'] ?? [];
                foreach (['foam_grade', 'length_mm', 'width_mm', 'thickness_mm'] as $field) {
                    if (isset($component[$field]) && $component[$field] !== '') {
                        if ($field !== 'foam_grade' && (! is_numeric($component[$field]) || (float) $component[$field] <= 0)) {
                            return $this->error('components', __('Component dimensions must be positive millimetres'));
                        }
                        $extra[$field] = $component[$field];
                    }
                }
                $resolved[$key] = [
                    'material_id' => $kind === 'material' ? $entity->id : null,
                    'product_type_id' => $kind === 'product_type' ? $entity->id : null,
                    'component_template_id' => $componentTemplate?->id,
                    'quantity_per_unit' => $quantity,
                    'scrap_percentage' => $scrap,
                    'extra_data' => $extra,
                    'notes' => $component['notes'] ?? null,
                    'sort_order' => (int) ($component['sort_order'] ?? $position),
                ];
            }

            $hadItems = $template->bomItems()->exists();
            DB::transaction(function () use ($template, $resolved, $mode) {
                $kept = [];
                foreach ($resolved as $attributes) {
                    $existing = $template->bomItems()
                        ->where('material_id', $attributes['material_id'])
                        ->where('product_type_id', $attributes['product_type_id'])->first();
                    $service = app(\App\Services\Material\BomService::class);
                    $item = $existing ? $service->updateItem($existing, $attributes) : $service->addItem($template, $attributes);
                    $kept[] = $item->id;
                }
                if ($mode === 'replace') {
                    $template->bomItems()->whereNotIn('id', $kept)->get()->each->delete();
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
