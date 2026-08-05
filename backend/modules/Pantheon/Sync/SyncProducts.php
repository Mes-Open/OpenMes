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

        return $this->importInBatches(
            // Stays lazy end to end: the client yields page by page and
            // importInBatches consumes in chunks, so the whole item table — which
            // can be tens of thousands of rows — is never held in memory at once.
            $this->readItems($classifications),
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
     * Stream the item rows, filtered in Pantheon where possible — reading the whole
     * table to throw most of it away is the difference between seconds and minutes.
     * One request per classification, because PAWS conditions are equality-based.
     *
     * @param  list<string>  $classifications
     * @return \Generator<int, array<string, mixed>>
     */
    private function readItems(array $classifications): \Generator
    {
        if ($classifications === []) {
            yield from $this->paws->select(self::TABLE, $this->fields());

            return;
        }

        foreach ($classifications as $classification) {
            yield from $this->paws->select(self::TABLE, $this->fields(), ['acClassif' => $classification]);
        }
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
            // anActive is Pantheon's own active flag. Only recognised values decide
            // it — a plain (bool) cast made 'F', 'N' and even 'false' come out
            // ACTIVE, quietly resurrecting items the customer had retired. An
            // unrecognised value leaves the OpenMES flag untouched.
            ...$this->activeFlag($row),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{is_active?: bool}
     */
    private function activeFlag(array $row): array
    {
        if (! array_key_exists('anActive', $row)) {
            return [];
        }

        $value = $row['anActive'];

        if (is_bool($value)) {
            return ['is_active' => $value];
        }

        return match (strtoupper(trim((string) $value))) {
            '1', 'T', 'TRUE', 'Y', 'YES' => ['is_active' => true],
            '0', 'F', 'FALSE', 'N', 'NO' => ['is_active' => false],
            default => [],
        };
    }
}
