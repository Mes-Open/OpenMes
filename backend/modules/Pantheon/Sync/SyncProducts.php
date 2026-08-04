<?php

namespace Modules\Pantheon\Sync;

use App\Services\Erp\ProductImportService;
use Modules\Pantheon\Services\PantheonSettings;
use Modules\Pantheon\Services\PawsClient;

/**
 * Pantheon `tHE_SetItem` → OpenMES product types.
 *
 * Pantheon keeps finished products and raw materials in ONE item table, told
 * apart by their classification (`acClassif`). This sync reads that table and
 * hands the rows to the core importer with the customer's product
 * classifications as the allowlist, so the filtering rule lives in one place and
 * an item outside those codes comes back as "skipped", not as an error.
 *
 * It calls the same ProductImportService the REST endpoint calls. The module runs
 * in-process, so there is no self-HTTP and no API key involved — but the contract,
 * the validation and the per-row report are identical.
 */
class SyncProducts extends Sync
{
    /** Pantheon's item table; the same one materials come from. */
    private const TABLE = 'tHE_SetItem';

    public function __construct(
        PawsClient $paws,
        PantheonSettings $settings,
        private ProductImportService $importer,
    ) {
        parent::__construct($paws, $settings);
    }

    public function name(): string
    {
        return 'products';
    }

    public function run(): array
    {
        $classifications = $this->settings->productClassifications();

        // Filter in Pantheon when we can — reading the whole item table to throw
        // most of it away is the difference between seconds and minutes. One
        // request per classification, because PAWS conditions are equality-based.
        $rows = [];

        if ($classifications === []) {
            $rows = iterator_to_array($this->paws->select(self::TABLE, $this->fields()), false);
        } else {
            foreach ($classifications as $classification) {
                foreach ($this->paws->select(self::TABLE, $this->fields(), ['acClassif' => $classification]) as $row) {
                    $rows[] = $row;
                }
            }
        }

        return $this->importInBatches(
            $rows,
            fn (array $batch) => $this->importer->import(
                rows: $batch,
                strategy: 'update_or_create',
                // Already filtered above; passing them again is a cheap safety net
                // for a Pantheon view that ignores the condition.
                onlyCategories: $classifications,
                system: 'pantheon',
            ),
        );
    }

    /**
     * Columns to read. Named explicitly so an added column in the customer's
     * Pantheon cannot silently change the payload.
     *
     * @return list<string>
     */
    private function fields(): array
    {
        return ['acIdent', 'acName', 'acClassif', 'acUM', 'anActive'];
    }

    /**
     * Pantheon column → canonical field. This is the one method a customer with a
     * customised item table needs changed.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function map(array $row): array
    {
        return [
            'code' => trim((string) ($row['acIdent'] ?? '')),
            'name' => trim((string) ($row['acName'] ?? '')),
            'category' => trim((string) ($row['acClassif'] ?? '')),
            'unit_of_measure' => trim((string) ($row['acUM'] ?? '')) ?: null,
            'external_code' => trim((string) ($row['acIdent'] ?? '')),
            'external_system' => 'pantheon',
            // anActive is Pantheon's own active flag; absent means "leave alone".
            ...array_key_exists('anActive', $row) ? ['is_active' => (bool) $row['anActive']] : [],
        ];
    }
}
