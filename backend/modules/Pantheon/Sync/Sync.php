<?php

namespace Modules\Pantheon\Sync;

use Modules\Pantheon\Services\PantheonSettings;
use Modules\Pantheon\Services\PawsClient;

/**
 * Shared shape of one Pantheon → OpenMES sync.
 *
 * Each subclass answers two questions: which Pantheon object it reads (`run`) and
 * how its columns map onto the canonical fields (`map`). Batching, counting and
 * error collection are the same for all of them and live here.
 *
 * The core importers cap a request at 2000–5000 rows, so a full item table has to
 * be fed in chunks; BATCH stays under the smallest of those caps.
 */
abstract class Sync
{
    /** Rows per call into a core import service. */
    protected const BATCH = 500;

    public function __construct(
        protected PawsClient $paws,
        protected PantheonSettings $settings,
    ) {}

    /** Short identifier used in the command, the log and the status page. */
    abstract public function name(): string;

    /** @return array{imported: int, updated: int, skipped: int, errors: array<int, mixed>} */
    abstract public function run(): array;

    /** @param  array<string, mixed>  $row */
    abstract protected function map(array $row): array;

    /**
     * Map, chunk, import, and add the per-batch reports up into one.
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @param  callable(array<int, array<string, mixed>>): array{imported: int, updated: int, skipped: int, errors: array<int, mixed>}  $import
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, mixed>}
     */
    protected function importInBatches(iterable $rows, callable $import): array
    {
        $total = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $batch = [];

        foreach ($rows as $row) {
            $mapped = $this->map($row);

            // A row without the key the importer resolves by is Pantheon data we
            // cannot act on; count it as skipped rather than sending a blank code.
            if (($mapped['code'] ?? '') === '' && ($mapped['lot_number'] ?? '') === ''
                && ($mapped['order_no'] ?? '') === '' && ($mapped['product_type_code'] ?? '') === '') {
                $total['skipped']++;

                continue;
            }

            $batch[] = $mapped;

            if (count($batch) >= static::BATCH) {
                $total = $this->merge($total, $import($batch));
                $batch = [];
            }
        }

        if ($batch !== []) {
            $total = $this->merge($total, $import($batch));
        }

        return $total;
    }

    /**
     * @param  array{imported: int, updated: int, skipped: int, errors: array<int, mixed>}  $total
     * @param  array{imported: int, updated: int, skipped: int, errors: array<int, mixed>}  $report
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, mixed>}
     */
    private function merge(array $total, array $report): array
    {
        return [
            'imported' => $total['imported'] + ($report['imported'] ?? 0),
            'updated' => $total['updated'] + ($report['updated'] ?? 0),
            'skipped' => $total['skipped'] + ($report['skipped'] ?? 0),
            // Keep every row error: a nightly sync is judged by what it could not
            // apply, and truncating that list hides exactly what needs fixing.
            'errors' => [...$total['errors'], ...($report['errors'] ?? [])],
        ];
    }
}
