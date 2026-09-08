<?php

namespace App\Jobs;

use App\Import\ImportRegistry;
use App\Models\CsvImport;
use App\Services\Import\RowMapper;
use App\Services\Import\RowMappingException;
use App\Services\Import\SpreadsheetReader;
use App\Support\TenantContext;
use App\Sync\CollectionBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs one unified-importer job (Admin → Import): reads the stored file, maps
 * columns to the entity's fields and feeds the entity importer chunk by chunk,
 * writing the counters after every chunk. The csv_imports row is live-synced,
 * so each save is what moves the progress bar in the browser.
 *
 * `$timeout` is the job's own ceiling (it wins over the worker's --timeout).
 * A run that outlives the queue's retry_after is handed to a second worker;
 * the atomic PENDING -> PROCESSING claim at the top is what makes that a
 * no-op, so no queue tuning is required for correctness.
 */
class ProcessDataImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function handle(SpreadsheetReader $reader, RowMapper $mapper, ImportRegistry $registry, TenantContext $tenant): void
    {
        $import = CsvImport::withoutGlobalScopes()->find($this->importId);

        if (! $import) {
            return; // gone
        }

        // Claim the run atomically. The database queue hands a job to a second
        // worker once retry_after elapses, and a long import outlives any
        // sensible value — so the guard against running twice has to be the
        // claim itself, not the queue's timing. Exactly one UPDATE can move the
        // row out of PENDING; every other worker sees 0 rows and leaves.
        $claimed = CsvImport::withoutGlobalScopes()
            ->whereKey($import->id)
            ->where('status', CsvImport::STATUS_PENDING)
            ->update(['status' => CsvImport::STATUS_PROCESSING, 'started_at' => now()]);

        if ($claimed === 0) {
            return; // another worker already has it, or it is finished
        }

        $import->refresh();

        $importer = $registry->get($import->entity);

        $tenant->set($import->tenant_id);

        try {
            if (! $importer) {
                throw new \RuntimeException("Unknown import entity '{$import->entity}'.");
            }

            $options = $import->options ?? [];
            $mapping = $options['mapping'] ?? [];
            $fileOptions = ['delimiter' => $options['delimiter'] ?? 'auto', 'encoding' => $options['encoding'] ?? 'utf-8'];
            $runOptions = $options['options'] ?? [];

            $parsed = $reader->read($this->fullPath($import), $fileOptions);
            $rows = $parsed['rows'];

            $import->update(['total_rows' => $parsed['total']]);

            $chunkSize = $importer->chunkSize() ?? max(1, count($rows));
            $errors = [];
            $storedErrors = 0;

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $canonical = [];
                $lineOf = [];          // canonical index (1-based) => file line
                $chunkErrors = [];

                foreach ($chunk as $fileRow) {
                    $line = (int) ($fileRow[SpreadsheetReader::ROW_KEY] ?? 0);

                    try {
                        $canonical[] = $mapper->map($fileRow, $mapping, $importer);
                        $lineOf[count($canonical)] = $line;
                    } catch (RowMappingException $e) {
                        $chunkErrors[] = ['row' => $line, 'field' => $e->field, 'message' => $e->getMessage()];
                    }
                }

                $result = $canonical !== []
                    ? $this->runChunk($import, $importer, $canonical, $runOptions)
                    : ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

                foreach ($result['errors'] as $error) {
                    $error['row'] = $lineOf[$error['row']] ?? $error['row'];
                    $chunkErrors[] = $error;
                }

                usort($chunkErrors, fn ($a, $b) => $a['row'] <=> $b['row']);

                foreach ($chunkErrors as $error) {
                    if ($storedErrors < CsvImport::MAX_STORED_ERRORS) {
                        $errors[] = $error;
                        $storedErrors++;
                    }
                }

                $import->processed_rows += count($chunk);
                $import->created_rows += $result['imported'];
                $import->updated_rows += $result['updated'];
                $import->skipped_rows += $result['skipped'];
                $import->failed_rows += count($chunkErrors);
                $import->successful_rows = $import->created_rows + $import->updated_rows;
                $import->error_log = $errors;
                $import->save();
            }

            $import->update([
                'status' => CsvImport::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            Log::info('Data import completed', [
                'import_id' => $import->id,
                'entity' => $import->entity,
                'created' => $import->created_rows,
                'updated' => $import->updated_rows,
                'skipped' => $import->skipped_rows,
                'failed' => $import->failed_rows,
            ]);
        } catch (Throwable $e) {
            // The run records its own failure; rethrowing would only turn it
            // into a 500 on the sync queue (the non-Docker default) and a
            // failed_jobs row that says nothing the row does not.
            $this->markFailed($import, $e);
        } finally {
            $tenant->clear();
            $this->deleteFile($import);
        }
    }

    public function failed(?Throwable $e): void
    {
        $import = CsvImport::withoutGlobalScopes()->find($this->importId);

        if ($import && $import->status !== CsvImport::STATUS_COMPLETED && $import->status !== CsvImport::STATUS_FAILED) {
            $this->markFailed($import, $e);
        }

        if ($import) {
            $this->deleteFile($import);
        }
    }

    private function markFailed(CsvImport $import, ?Throwable $e): void
    {
        // The message stays in the log: an exception from the database layer
        // carries SQL and bound values, which the history page must not show.
        Log::error('Data import failed', [
            'import_id' => $import->id,
            'entity' => $import->entity,
            'exception' => $e ? $e::class : null,
            'error' => $e?->getMessage(),
        ]);

        $import->update([
            'status' => CsvImport::STATUS_FAILED,
            'error_log' => array_merge($import->error_log ?? [], [[
                'row' => 0,
                'field' => null,
                'message' => __('The import stopped unexpectedly. Details are in the server log.'),
            ]]),
            'completed_at' => now(),
        ]);
    }

    /**
     * One chunk through the entity importer.
     *
     * A dry run takes the same path — same mapping, same importer, same writes
     * — inside a transaction that always rolls back, so it reports what a real
     * run would do, including what only the database can tell us (unique
     * collisions, missing foreign keys). Broadcasting is muted for the
     * duration: the rows exist for the length of the transaction and would
     * otherwise be pushed to every open browser and never taken back.
     *
     * The counters are returned, not saved, so the caller writes them outside
     * the transaction and the run still shows up in the history.
     *
     * @param  list<array<string, mixed>>  $canonical
     * @param  array<string, mixed>  $runOptions
     * @return array{imported: int, updated: int, skipped: int, errors: list<array{row: int, field: string|null, message: string}>}
     */
    private function runChunk(CsvImport $import, $importer, array $canonical, array $runOptions): array
    {
        if (! $import->dry_run) {
            return $importer->import($canonical, $runOptions);
        }

        $result = null;

        try {
            DB::transaction(function () use (&$result, $importer, $canonical, $runOptions): void {
                $result = CollectionBroadcaster::muted(fn () => $importer->import($canonical, $runOptions));

                throw new DryRunRollback;
            });
        } catch (DryRunRollback) {
            // Expected: the only way out of the transaction without committing.
        }

        return $result ?? ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    private function fullPath(CsvImport $import): string
    {
        return Storage::disk('local')->path((string) $import->file_path);
    }

    /**
     * A finished run no longer needs its upload — except a dry run, whose whole
     * purpose is to be followed by the real thing. Deleting there would force a
     * re-upload and a re-map to act on what the validation just reported, so
     * the file (and the session's mapping token) outlive a validation and are
     * cleaned up by the real run that follows.
     */
    private function deleteFile(CsvImport $import): void
    {
        if ($import->dry_run) {
            return;
        }

        if ($import->file_path && Storage::disk('local')->exists($import->file_path)) {
            Storage::disk('local')->delete($import->file_path);
        }
    }
}
