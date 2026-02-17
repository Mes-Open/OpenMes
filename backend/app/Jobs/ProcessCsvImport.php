<?php

namespace App\Jobs;

use App\Models\CsvImport;
use App\Services\CsvImport\CsvParserService;
use App\Services\CsvImport\WorkOrderImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $csvImportId,
        public string $filePath,
        public array $mapping
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        CsvParserService $csvParser,
        WorkOrderImportService $importService
    ): void {
        $csvImport = CsvImport::findOrFail($this->csvImportId);

        try {
            // Update status to processing
            $csvImport->update([
                'status' => 'PROCESSING',
                'started_at' => now(),
            ]);

            // Parse CSV with mapping
            $mappedData = $csvParser->parseWithMapping(
                $this->filePath,
                $this->mapping['columns']
            );

            // Import work orders
            $result = $importService->import(
                $mappedData,
                $this->mapping['import_strategy']
            );

            // Update import record
            $csvImport->update([
                'status' => 'COMPLETED',
                'successful_rows' => $result['successful'],
                'failed_rows' => $result['failed'],
                'error_log' => $result['error_log'],
                'completed_at' => now(),
            ]);

            Log::info('CSV import completed', [
                'import_id' => $this->csvImportId,
                'successful' => $result['successful'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
            ]);

            // Clean up temporary file
            $csvParser->cleanupTemporary($this->filePath);
        } catch (\Exception $e) {
            // Mark as failed
            $csvImport->update([
                'status' => 'FAILED',
                'error_log' => [
                    [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ],
                ],
                'completed_at' => now(),
            ]);

            Log::error('CSV import failed', [
                'import_id' => $this->csvImportId,
                'error' => $e->getMessage(),
            ]);

            // Clean up temporary file
            $csvParser->cleanupTemporary($this->filePath);

            throw $e;
        }
    }
}
