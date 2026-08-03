<?php

namespace App\Services\Erp\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Shared row loop for the ERP master-data importers (#212).
 *
 * Every importer processes rows independently and reports per row, matching the
 * contract the work-order importer established: a bad reference in one row never
 * fails the batch, the caller gets counts plus a structured error list, and the
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
                $result = $handler($row);

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
                $errors[] = ['row' => $rowNumber, 'field' => null, 'message' => $e->getMessage()];

                Log::error('ERP import row failed', [
                    'importer' => static::class,
                    'row' => $rowNumber,
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
