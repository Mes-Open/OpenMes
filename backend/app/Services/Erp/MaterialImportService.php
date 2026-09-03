<?php

namespace App\Services\Erp;

use App\Models\Material;
use App\Models\MaterialType;
use App\Services\Erp\Concerns\ReportsImportRows;
use Illuminate\Support\Str;

/**
 * ERP → OpenMES material (raw material / component) master-data import (#212).
 *
 * Mirrors ProductImportService, including the `only_categories` filter, because
 * products and materials arrive from the same ERP item table and are told apart
 * only by their classification. The material type is resolved (and created on
 * demand) from a code so the ERP never needs OpenMES ids.
 *
 * The unified file importer (Admin → Import) runs through here too, with the
 * extras the old material CSV screen offered switched on via `$options`:
 * matching by external code / EAN before the internal code, generating a code
 * for rows that only carry the ERP's symbol, and an update-only strategy.
 */
class MaterialImportService
{
    use ReportsImportRows;

    /** Material type used when a row names none — created on first use. */
    private const FALLBACK_TYPE_CODE = 'ERP';

    public const STRATEGIES = ['update_or_create', 'skip_existing', 'error_on_duplicate', 'update_only'];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $onlyCategories  empty = accept every category
     * @param  array{match?: 'code'|'cascade', generate_code?: bool, default_material_type?: string|null, require_name?: bool}  $options
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function import(array $rows, string $strategy = 'update_or_create', array $onlyCategories = [], ?string $system = null, array $options = []): array
    {
        $allowed = array_map(fn (string $c) => mb_strtolower(trim($c)), $onlyCategories);
        $cascade = ($options['match'] ?? 'code') === 'cascade';
        $generateCode = (bool) ($options['generate_code'] ?? false);
        $defaultType = trim((string) ($options['default_material_type'] ?? ''));

        return $this->processRows($rows, function (array $row) use ($strategy, $allowed, $system, $cascade, $generateCode, $defaultType, $options) {
            $code = trim((string) ($row['code'] ?? ''));
            $externalCode = trim((string) ($row['external_code'] ?? ''));
            $externalSystem = trim((string) ($row['external_system'] ?? $system ?? ''));
            $ean = trim((string) ($row['ean'] ?? ''));

            // Without the cascade the code is the identity; with it any of the
            // three will do to find the row, and a code can be generated on create.
            if ($code === '' && (! $cascade || ($externalCode === '' && $ean === ''))) {
                return $this->error('code', __('Material code is required'));
            }

            // The ERP payload calls this `category` and derives material_type from
            // it; the CSV importer has no `category` field at all and maps the
            // column straight onto `material_type`. Read both, or the "Only
            // material types" option silently skips every row of every file.
            $category = $row['category'] ?? $row['material_type'] ?? null;
            $category = $category !== null ? trim((string) $category) : null;

            if ($allowed !== [] && ! in_array(mb_strtolower((string) $category), $allowed, true)) {
                return $this->skipped();
            }

            $existing = $cascade
                ? $this->findByCascade($code, $externalCode, $externalSystem, $ean)
                : Material::where('code', $code)->first();

            if ($existing && $strategy === 'skip_existing') {
                return $this->skipped();
            }

            if ($existing && $strategy === 'error_on_duplicate') {
                return $this->error('code', __("Material ':code' already exists", ['code' => $code !== '' ? $code : $externalCode]));
            }

            if (! $existing && $strategy === 'update_only') {
                return $this->skipped();
            }

            // Only what the row says: an update must not overwrite the ERP
            // identity or the name with derived defaults just because a column
            // was not mapped. Create fills the defaults in below.
            $attributes = [];

            foreach (['name', 'description', 'unit_of_measure', 'supplier_name', 'supplier_code'] as $field) {
                if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
                    $attributes[$field] = is_string($row[$field]) ? trim($row[$field]) : $row[$field];
                }
            }

            if ($externalCode !== '') {
                $attributes['external_code'] = $externalCode;
            }

            if ($externalSystem !== '' && ($externalCode !== '' || ! $existing)) {
                $attributes['external_system'] = $externalSystem;
            }

            if ($ean !== '') {
                $attributes['ean'] = $ean;
            }

            if (! $existing && ! isset($attributes['name'])) {
                if (! empty($options['require_name'])) {
                    return $this->error('name', __('Material name is required'));
                }

                $attributes['name'] = $code !== '' ? $code : $externalCode;
            }

            // The ERP classification has no dedicated column on materials — it is
            // the material type, which is what OpenMES groups materials by.
            $typeRef = trim((string) ($row['material_type_code'] ?? $row['material_type'] ?? $category ?? ''));

            if ($typeRef === '' && $defaultType !== '') {
                $typeRef = $defaultType;
            }

            if ($typeRef !== '' || ! $existing) {
                $attributes['material_type_id'] = $this->resolveTypeId($typeRef !== '' ? $typeRef : self::FALLBACK_TYPE_CODE);
            }

            if (isset($row['tracking_type'])) {
                if (! in_array($row['tracking_type'], ['none', 'batch', 'serial'], true)) {
                    return $this->error('tracking_type', __('Tracking type must be none, batch or serial'));
                }
                $attributes['tracking_type'] = $row['tracking_type'];
            }

            if (isset($row['unit_price'])) {
                $attributes['unit_price'] = (float) $row['unit_price'];

                if (isset($row['price_currency'])) {
                    $attributes['price_currency'] = strtoupper(Str::limit((string) $row['price_currency'], 3, ''));
                }
            }

            foreach (['min_stock_level', 'stock_quantity', 'default_scrap_percentage'] as $field) {
                if (isset($row[$field])) {
                    $attributes[$field] = (float) $row[$field];
                }
            }

            if (array_key_exists('is_active', $row)) {
                $attributes['is_active'] = (bool) $row['is_active'];
            }

            if ($existing) {
                // An update never changes the code — it is what everything else
                // (lots, BOM lines, ERP references) points at.
                $existing->update($attributes);

                return $this->updated();
            }

            if ($code === '') {
                if (! $generateCode) {
                    return $this->error('code', __('Material code is required'));
                }

                $code = $this->generateUniqueCode($externalCode !== '' ? $externalCode : $attributes['name']);
            }

            Material::create([
                'code' => $code,
                'unit_of_measure' => 'pcs',
                'external_code' => $externalCode !== '' ? $externalCode : $code,
                'is_active' => true,
                ...$attributes,
            ]);

            return $this->created();
        });
    }

    /**
     * The old material CSV screen's matching order, kept for ERP exports (Subiekt
     * and friends) whose stable identity is the ERP symbol or the barcode rather
     * than an OpenMES code: external code + system, then EAN, then code.
     */
    private function findByCascade(string $code, string $externalCode, string $externalSystem, string $ean): ?Material
    {
        if ($externalCode !== '' && $externalSystem !== '') {
            $found = Material::where('external_code', $externalCode)
                ->where('external_system', $externalSystem)
                ->first();

            if ($found) {
                return $found;
            }
        }

        if ($ean !== '') {
            $found = Material::where('ean', $ean)->first();

            if ($found) {
                return $found;
            }
        }

        if ($code !== '') {
            return Material::where('code', $code)->first();
        }

        return null;
    }

    private function generateUniqueCode(string $source): string
    {
        $code = Str::upper(Str::slug($source, '-'));
        $code = Str::limit($code !== '' ? $code : 'MAT', 47, '');

        if (! Material::where('code', $code)->exists()) {
            return $code;
        }

        $i = 1;
        while (Material::where('code', "{$code}-{$i}")->exists()) {
            $i++;
        }

        return "{$code}-{$i}";
    }

    /**
     * Resolve a material type by code — or, for files typed by hand, by name —
     * creating it if the ERP knows a group OpenMES does not. firstOrCreate keeps
     * the lookup and the insert in one statement — a separate check then create
     * lets two concurrent imports of the same new code both miss and both insert.
     */
    private function resolveTypeId(string $ref): int
    {
        $byName = MaterialType::where('name', $ref)->value('id');

        if ($byName) {
            return $byName;
        }

        return MaterialType::firstOrCreate(['code' => $ref], ['name' => $ref])->id;
    }
}
