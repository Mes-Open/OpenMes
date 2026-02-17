<?php

namespace App\Services\CsvImport;

use App\Models\WorkOrder;
use App\Models\Line;
use App\Models\ProductType;
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
     * @param array $mappedData Parsed and mapped CSV data
     * @param string $strategy Import strategy (update_or_create, skip_existing, error_on_duplicate)
     * @return array Import results
     */
    public function import(array $mappedData, string $strategy): array
    {
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $errorLog = [];

        foreach ($mappedData as $row) {
            try {
                $result = $this->importRow($row, $strategy);

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
     * Import a single row.
     */
    protected function importRow(array $row, string $strategy): array
    {
        // Validate required fields
        if (empty($row['order_no'])) {
            return ['status' => 'error', 'error' => 'Order number is required'];
        }

        // Find line by code
        $line = Line::where('code', $row['line_code'])->first();
        if (!$line) {
            return ['status' => 'error', 'error' => "Line '{$row['line_code']}' not found"];
        }

        // Find product type by code
        $productType = ProductType::where('code', $row['product_type_code'])->first();
        if (!$productType) {
            return ['status' => 'error', 'error' => "Product type '{$row['product_type_code']}' not found"];
        }

        // Validate planned quantity
        if (empty($row['planned_qty']) || $row['planned_qty'] <= 0) {
            return ['status' => 'error', 'error' => 'Planned quantity must be greater than 0'];
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
                return ['status' => 'error', 'error' => "Duplicate order number: {$row['order_no']}"];

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
            $existing->update([
                'line_id' => $line->id,
                'product_type_id' => $productType->id,
                'planned_qty' => $row['planned_qty'],
                'priority' => $row['priority'] ?? 0,
                'due_date' => $row['due_date'] ?? null,
                'description' => $row['description'] ?? null,
            ]);

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

            if (!$processTemplate) {
                throw new \Exception("No active process template found for product type '{$productType->code}'");
            }

            // Generate process snapshot
            $snapshot = $this->snapshotService->createSnapshot($processTemplate);

            WorkOrder::create([
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
            ]);

            Log::info('Work order created via CSV import', [
                'order_no' => $row['order_no'],
            ]);
        });

        return ['status' => 'success', 'action' => 'created'];
    }
}
