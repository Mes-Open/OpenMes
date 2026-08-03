<?php

namespace App\Services\Erp;

use App\Models\Material;
use App\Models\MaterialType;
use App\Services\Erp\Concerns\ReportsImportRows;

/**
 * ERP → OpenMES material (raw material / component) master-data import (#212).
 *
 * Mirrors ProductImportService, including the `only_categories` filter, because
 * products and materials arrive from the same ERP item table and are told apart
 * only by their classification. The material type is resolved (and created on
 * demand) from a code so the ERP never needs OpenMES ids.
 */
class MaterialImportService
{
    use ReportsImportRows;

    /** Material type used when a row names none — created on first use. */
    private const FALLBACK_TYPE_CODE = 'ERP';

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $onlyCategories  empty = accept every category
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function import(array $rows, string $strategy = 'update_or_create', array $onlyCategories = [], ?string $system = null): array
    {
        $allowed = array_map(fn (string $c) => mb_strtolower(trim($c)), $onlyCategories);

        return $this->processRows($rows, function (array $row) use ($strategy, $allowed, $system) {
            $code = trim((string) ($row['code'] ?? ''));

            if ($code === '') {
                return $this->error('code', __('Material code is required'));
            }

            $category = isset($row['category']) ? trim((string) $row['category']) : null;

            if ($allowed !== [] && ! in_array(mb_strtolower((string) $category), $allowed, true)) {
                return $this->skipped();
            }

            $existing = Material::where('code', $code)->first();

            if ($existing && $strategy === 'skip_existing') {
                return $this->skipped();
            }

            if ($existing && $strategy === 'error_on_duplicate') {
                return $this->error('code', __("Material ':code' already exists", ['code' => $code]));
            }

            $attributes = [
                'name' => trim((string) ($row['name'] ?? $code)),
                'description' => $row['description'] ?? null,
                'unit_of_measure' => $row['unit_of_measure'] ?? 'pcs',
                'external_code' => $row['external_code'] ?? $code,
                'external_system' => $row['external_system'] ?? $system,
                'supplier_name' => $row['supplier_name'] ?? null,
                'supplier_code' => $row['supplier_code'] ?? null,
                'ean' => $row['ean'] ?? null,
            ];

            // The ERP classification has no dedicated column on materials — it is
            // the material type, which is what OpenMES groups materials by.
            $typeCode = trim((string) ($row['material_type_code'] ?? $category ?? ''));
            $attributes['material_type_id'] = $this->resolveTypeId($typeCode !== '' ? $typeCode : self::FALLBACK_TYPE_CODE);

            if (isset($row['tracking_type'])) {
                if (! in_array($row['tracking_type'], ['none', 'batch', 'serial'], true)) {
                    return $this->error('tracking_type', __('Tracking type must be none, batch or serial'));
                }
                $attributes['tracking_type'] = $row['tracking_type'];
            }

            if (isset($row['unit_price'])) {
                $attributes['unit_price'] = (float) $row['unit_price'];
                $attributes['price_currency'] = $row['price_currency'] ?? null;
            }

            if (isset($row['min_stock_level'])) {
                $attributes['min_stock_level'] = (float) $row['min_stock_level'];
            }

            if (array_key_exists('is_active', $row)) {
                $attributes['is_active'] = (bool) $row['is_active'];
            }

            if ($existing) {
                $existing->update($attributes);

                return $this->updated();
            }

            Material::create([
                'code' => $code,
                'is_active' => true,
                ...$attributes,
            ]);

            return $this->created();
        });
    }

    /** Resolve a material type by code, creating it if the ERP knows a group OpenMES does not. */
    private function resolveTypeId(string $code): int
    {
        $type = MaterialType::where('code', $code)->first();

        return $type?->id ?? MaterialType::create(['code' => $code, 'name' => $code])->id;
    }
}
