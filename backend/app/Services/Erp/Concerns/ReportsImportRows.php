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
 */
trait ReportsImportRows
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $handler  returns
     *                                                                         ['status' => 'success'|'skipped'|'error', 'action' => 'created'|'updated', …]
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>}
     */
    protected function processRows(array $rows, callable $handler): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $rowNumber = $index + 1;

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
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                } else {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => $result['field'] ?? null,
                        'message' => $result['error'] ?? __('Unknown error'),
                    ];
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

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /** @return array<string, mixed> */
    protected function error(string $field, string $message): array
    {
        return ['status' => 'error', 'field' => $field, 'error' => $message];
    }

    /** @return array<string, mixed> */
    protected function created(): array
    {
        return ['status' => 'success', 'action' => 'created'];
    }

    /** @return array<string, mixed> */
    protected function updated(): array
    {
        return ['status' => 'success', 'action' => 'updated'];
    }

    /** @return array<string, mixed> */
    protected function skipped(): array
    {
        return ['status' => 'skipped'];
    }
}
