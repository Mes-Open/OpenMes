<?php

namespace App\Services\CsvImport;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Services\ProcessTemplate\SnapshotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderImportService
{
    public function __construct(
        protected CsvParserService $csvParser,
        protected SnapshotService $snapshotService
    ) {}

    /**
     * Import work orders from parsed CSV data.
     *
     * @param  array  $mappedData  Parsed and mapped CSV data
     * @param  string  $strategy  Import strategy (update_or_create, skip_existing, error_on_duplicate)
     * @return array Import results
     */
    public function import(array $mappedData, string $strategy, ?int $targetLineId = null): array
    {
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $errorLog = [];

        foreach ($mappedData as $row) {
            try {
                $result = $this->importRow($row, $strategy, $targetLineId);

                if ($result['status'] === 'success') {
                    $successful++;
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                } else {
                    $failed++;
                    $errorLog[] = [
                        'row' => $row['row_number'],
                        'error' => $result['error'],
                    ];
                }
            } catch (\Exception $e) {
                $failed++;
                $errorLog[] = [
                    'row' => $row['row_number'],
                    'error' => $e->getMessage(),
                ];

                Log::error('CSV import row failed', [
                    'row' => $row['row_number'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
            'skipped' => $skipped,
            'error_log' => $errorLog,
        ];
    }

    /**
     * Bulk-import work orders from an ERP payload (already-validated array of
     * canonical rows, not CSV). Reuses the same per-row logic as the CSV import
     * but returns an ERP-shaped result that distinguishes created vs updated and
     * carries structured per-row errors ({row, field, message}).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>}
     */
    public function importErp(array $rows, string $strategy): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $row['row_number'] = $rowNumber;

            try {
                $result = $this->importRow($row, $strategy);

                if ($result['status'] === 'success') {
                    ($result['action'] ?? null) === 'updated' ? $updated++ : $imported++;
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                } else {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => $result['field'] ?? null,
                        'message' => $result['error'],
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'field' => null, 'message' => $e->getMessage()];

                Log::error('ERP work order import row failed', [
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

    /**
     * Import a single row.
     */
    protected function importRow(array $row, string $strategy, ?int $targetLineId = null): array
    {
        // Validate required fields
        if (empty($row['order_no'])) {
            return ['status' => 'error', 'field' => 'order_no', 'error' => 'Order number is required'];
        }

        // Find the line: an explicit "assign all rows to this line" override wins
        // over the per-row line_code column (mirrors the web importer).
        if ($targetLineId !== null) {
            $line = Line::find($targetLineId);
            if (! $line) {
                return ['status' => 'error', 'field' => 'line_code', 'error' => "Target line #{$targetLineId} not found"];
            }
        } else {
            $line = Line::where('code', $row['line_code'] ?? null)->first();
            if (! $line) {
                return ['status' => 'error', 'field' => 'line_code', 'error' => "Line '".($row['line_code'] ?? '')."' not found"];
            }
        }

        // Find product type by code
        $productType = ProductType::where('code', $row['product_type_code'] ?? null)->first();
        if (! $productType) {
            return ['status' => 'error', 'field' => 'product_type_code', 'error' => "Product type '".($row['product_type_code'] ?? '')."' not found"];
        }

        // Validate planned quantity
        if (empty($row['planned_qty']) || $row['planned_qty'] <= 0) {
            return ['status' => 'error', 'field' => 'planned_qty', 'error' => 'Planned quantity must be greater than 0'];
        }

        // Check if work order exists
        $existing = WorkOrder::where('order_no', $row['order_no'])->first();

        if ($existing) {
            return $this->handleExisting($existing, $row, $strategy, $line, $productType);
        } else {
            return $this->createNew($row, $line, $productType);
        }
    }

    /**
     * Handle existing work order based on strategy.
     */
    protected function handleExisting(
        WorkOrder $existing,
        array $row,
        string $strategy,
        Line $line,
        ProductType $productType
    ): array {
        switch ($strategy) {
            case 'update_or_create':
                return $this->updateExisting($existing, $row, $line, $productType);

            case 'skip_existing':
                return ['status' => 'skipped', 'message' => 'Work order already exists'];

            case 'error_on_duplicate':
                return ['status' => 'error', 'field' => 'order_no', 'error' => "Duplicate order number: {$row['order_no']}"];

            default:
                return ['status' => 'error', 'error' => 'Invalid import strategy'];
        }
    }

    /**
     * Update existing work order.
     */
    protected function updateExisting(
        WorkOrder $existing,
        array $row,
        Line $line,
        ProductType $productType
    ): array {
        // Don't update if work order is already done or cancelled
        if (in_array($existing->status, ['DONE', 'CANCELLED'])) {
            return ['status' => 'skipped', 'message' => 'Work order already completed/cancelled'];
        }

        DB::transaction(function () use ($existing, $row, $line, $productType) {
            $existing->update(array_merge([
                'line_id' => $line->id,
                'product_type_id' => $productType->id,
                'planned_qty' => $row['planned_qty'],
                'priority' => $row['priority'] ?? 0,
                'due_date' => $row['due_date'] ?? null,
                'description' => $row['description'] ?? null,
            ], $this->optionalErpFields($row)));

            Log::info('Work order updated via CSV import', [
                'order_no' => $existing->order_no,
            ]);
        });

        return ['status' => 'success', 'action' => 'updated'];
    }

    /**
     * Create new work order.
     */
    protected function createNew(array $row, Line $line, ProductType $productType): array
    {
        DB::transaction(function () use ($row, $line, $productType) {
            // Get active process template for product type
            $processTemplate = $productType->processTemplates()
                ->where('is_active', true)
                ->first();

            if (! $processTemplate) {
                throw new \Exception("No active process template found for product type '{$productType->code}'");
            }

            // Generate process snapshot
            $snapshot = $this->snapshotService->createSnapshot($processTemplate);

            WorkOrder::create(array_merge([
                'order_no' => $row['order_no'],
                'line_id' => $line->id,
                'product_type_id' => $productType->id,
                'process_snapshot' => $snapshot,
                'planned_qty' => $row['planned_qty'],
                'produced_qty' => 0,
                'status' => 'PENDING',
                'priority' => $row['priority'] ?? 0,
                'due_date' => $row['due_date'] ?? null,
                'description' => $row['description'] ?? null,
            ], $this->optionalErpFields($row)));

            Log::info('Work order created via CSV import', [
                'order_no' => $row['order_no'],
            ]);
        });

        return ['status' => 'success', 'action' => 'created'];
    }

    /**
     * ERP-only columns applied on top of the shared create/update set, and only
     * when present in the row — so the CSV path (which never supplies them) is
     * unaffected.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function optionalErpFields(array $row): array
    {
        $fields = [];

        if (array_key_exists('customer_order_no', $row) && $row['customer_order_no'] !== null) {
            $fields['customer_order_no'] = $row['customer_order_no'];
        }

        if (array_key_exists('unit_price', $row) && $row['unit_price'] !== null) {
            $fields['unit_price'] = $row['unit_price'];
        }

        return $fields;
    }
}
