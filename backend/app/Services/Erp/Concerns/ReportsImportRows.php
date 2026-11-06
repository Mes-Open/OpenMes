<?php

namespace App\Services\Erp\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared row loop for the ERP master-data importers (#212).
 *
 * Every importer processes rows independently and reports per row, matching the
 * contract the work-order importer established: a bad reference in one row never
 * fails the batch (each row runs in its own transaction so a database-level
 * failure cannot cascade into the rows after it), the caller gets counts plus a structured error list, and the
 * controller turns a non-empty error list into 207 Multi-Status.
 *
 * A row can also succeed with a caveat — e.g. a unit no alias maps — which the
 * handler reports through warn(). Warnings sit next to `errors` in the report
 * with the same shape, and never change the HTTP status.
 */
trait ReportsImportRows
{
    /** @var list<array{field: string, message: string}> warnings of the row being processed */
    private array $pendingWarnings = [];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $handler  returns
     *                                                                         ['status' => 'success'|'skipped'|'error', 'action' => 'created'|'updated', 'report' => [...], …]
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>, warnings: list<array{row: int, field: string, message: string}>, rows?: array<int, array<string, mixed>>}
     */
    protected function processRows(array $rows, callable $handler): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];
        // Per-row details a handler wants the caller to see (e.g. a default
        // process template created alongside a product). Only rows with
        // something to say are listed, and the key is omitted when none do,
        // so the response is unchanged for importers that never report.
        $reports = [];

        foreach (array_values($rows) as $index => $row) {
            $rowNumber = $index + 1;
            $this->pendingWarnings = [];

            try {
                // Each row gets its own transaction — a savepoint when the
                // caller already opened one (the importer's dry run wraps a
                // whole chunk). Without it a row that fails at the database
                // takes the rest of the batch with it on PostgreSQL: the
                // failed statement aborts the transaction and every later
                // statement dies with "current transaction is aborted", so one
                // bad row is reported as dozens. It also stops a row that
                // throws mid-write from leaving half of itself behind.
                $result = DB::transaction(fn () => $handler($row));

                if ($result['status'] === 'success') {
                    ($result['action'] ?? null) === 'updated' ? $updated++ : $imported++;

                    if (! empty($result['report'])) {
                        $reports[] = ['row' => $rowNumber, ...$result['report']];
                    }
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                } else {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => $result['field'] ?? null,
                        'message' => $result['error'] ?? __('Unknown error'),
                    ];
                }

                // An error row already reports its problem; a caveat on top of
                // it would only be noise.
                if ($result['status'] !== 'error') {
                    foreach ($this->pendingWarnings as $warning) {
                        $warnings[] = ['row' => $rowNumber, ...$warning];
                    }
                }
            } catch (\Throwable $e) {
                // Never hand the exception text to the caller: a QueryException
                // carries the SQL, table names and bound values, and an ERP API key
                // is not a debugging credential. The detail goes to the log.
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => null,
                    'message' => __('Row could not be processed'),
                ];

                Log::error('ERP import row failed', [
                    'importer' => static::class,
                    'row' => $rowNumber,
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $result = [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'warnings' => $warnings,
        ];

        if ($reports !== []) {
            $result['rows'] = $reports;
        }

        return $result;
    }

    /**
     * Note a caveat on the row being processed. It is reported only if the
     * row does not end in an error.
     */
    protected function warn(string $field, string $message): void
    {
        $this->pendingWarnings[] = ['field' => $field, 'message' => $message];
    }

    /** @return array<string, mixed> */
    protected function error(string $field, string $message): array
    {
        return ['status' => 'error', 'field' => $field, 'error' => $message];
    }

    /**
     * @param  array<string, mixed>  $report  listed under `rows` in the response when non-empty
     * @return array<string, mixed>
     */
    protected function created(array $report = []): array
    {
        return ['status' => 'success', 'action' => 'created', 'report' => $report];
    }

    /**
     * @param  array<string, mixed>  $report  listed under `rows` in the response when non-empty
     * @return array<string, mixed>
     */
    protected function updated(array $report = []): array
    {
        return ['status' => 'success', 'action' => 'updated', 'report' => $report];
    }

    /** @return array<string, mixed> */
    protected function skipped(): array
    {
        return ['status' => 'skipped'];
    }
}
