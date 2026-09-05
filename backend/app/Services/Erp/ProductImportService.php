<?php

namespace App\Services\Erp;

use App\Models\ProductType;
use App\Services\Erp\Concerns\ReportsImportRows;

/**
 * ERP → OpenMES product (product type) master-data import (#212).
 *
 * ERPs keep every item — finished products and raw materials alike — in one item
 * table, grouped by a classification code (Pantheon's acClassif). Passing
 * `only_categories` limits an import to the classifications that really are
 * manufactured products, so a full item dump can be sent and filtered here
 * instead of being hand-picked by the caller.
 */
class ProductImportService
{
    use ReportsImportRows;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $onlyCategories  empty = accept every category
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function import(array $rows, string $strategy = 'update_or_create', array $onlyCategories = [], ?string $system = null): array
    {
        // Case-insensitive match: ERP classification codes are inconsistently cased.
        $allowed = array_map(fn (string $c) => mb_strtolower(trim($c)), $onlyCategories);

        return $this->processRows($rows, function (array $row) use ($strategy, $allowed, $system) {
            $code = trim((string) ($row['code'] ?? ''));

            if ($code === '') {
                return $this->error('code', __('Product code is required'));
            }

            $category = isset($row['category']) ? trim((string) $row['category']) : null;

            if ($allowed !== [] && ! in_array(mb_strtolower((string) $category), $allowed, true)) {
                return $this->skipped();
            }

            $existing = ProductType::where('code', $code)->first();

            if ($existing && $strategy === 'skip_existing') {
                return $this->skipped();
            }

            if ($existing && $strategy === 'error_on_duplicate') {
                return $this->error('code', __("Product ':code' already exists", ['code' => $code]));
            }

            // Only what the row says. A file that maps two columns must not
            // blank the other six on every existing product; the create path
            // fills in the defaults below.
            $attributes = [];

            foreach (['name', 'description', 'category', 'unit_of_measure', 'external_code', 'external_system'] as $field) {
                if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
                    $attributes[$field] = is_string($row[$field]) ? trim($row[$field]) : $row[$field];
                }
            }

            if (! isset($attributes['external_system']) && $system !== null) {
                $attributes['external_system'] = $system;
            }

            // A row that omits is_active leaves the current flag alone on update
            // and defaults to active on create.
            if (array_key_exists('is_active', $row)) {
                $attributes['is_active'] = (bool) $row['is_active'];
            }

            if ($existing) {
                $existing->update($attributes);

                return $this->updated();
            }

            ProductType::create([
                'code' => $code,
                'name' => $code,
                'unit_of_measure' => 'pcs',
                'external_code' => $code,
                'is_active' => true,
                ...$attributes,
            ]);

            return $this->created();
        });
    }
}
