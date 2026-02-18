<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CsvImport;
use App\Models\CsvImportMapping;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CsvImportController extends Controller
{
    /**
     * Show the import page with history and saved mappings.
     */
    public function index()
    {
        $recentImports = CsvImport::with(['user', 'mapping'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $savedMappings = CsvImportMapping::where('user_id', auth()->id())
            ->orWhere('is_default', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $systemFields = CsvImportMapping::systemFields();

        return view('admin.csv-import', compact('recentImports', 'savedMappings', 'systemFields'));
    }

    /**
     * Handle CSV file upload and show mapping UI.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'csv_file'        => 'required|file|mimes:csv,txt|max:5120',
            'import_strategy' => 'required|in:update_or_create,skip_existing,error_on_duplicate',
            'mapping_id'      => 'nullable|exists:csv_import_mappings,id',
        ]);

        $file = $request->file('csv_file');
        $path = $file->store('csv-imports', 'local');

        // Parse headers and first 5 preview rows
        $handle      = fopen(Storage::disk('local')->path($path), 'r');
        $headers     = fgetcsv($handle);
        $previewRows = [];
        $rowCount    = 0;
        while (($row = fgetcsv($handle)) !== false && $rowCount < 5) {
            $previewRows[] = array_combine(
                $headers,
                array_pad($row, count($headers), '')
            );
            $rowCount++;
        }
        fclose($handle);

        // Count total rows
        $totalRows    = 0;
        $countHandle  = fopen(Storage::disk('local')->path($path), 'r');
        fgetcsv($countHandle); // skip header
        while (fgetcsv($countHandle) !== false) {
            $totalRows++;
        }
        fclose($countHandle);

        // Load existing mapping if selected
        $existingMapping = null;
        if ($request->filled('mapping_id')) {
            $existingMapping = CsvImportMapping::find($request->mapping_id);
        }

        $savedMappings = CsvImportMapping::where('user_id', auth()->id())
            ->orWhere('is_default', true)
            ->orderBy('name')
            ->get();

        $systemFields    = CsvImportMapping::systemFields();
        $importStrategy  = $request->import_strategy;

        return view('admin.csv-import-mapping', compact(
            'headers', 'previewRows', 'totalRows',
            'path', 'savedMappings', 'systemFields',
            'existingMapping', 'importStrategy'
        ));
    }

    /**
     * Process the import with the provided column mapping.
     */
    public function process(Request $request)
    {
        $request->validate([
            'file_path'       => 'required|string',
            'import_strategy' => 'required|in:update_or_create,skip_existing,error_on_duplicate',
            'mapping'         => 'required|array',
        ]);

        $filePath = $request->input('file_path');
        $strategy = $request->input('import_strategy');
        $mapping  = $request->input('mapping');

        // Save mapping profile if requested
        if ($request->filled('save_mapping_name')) {
            CsvImportMapping::create([
                'name'           => $request->input('save_mapping_name'),
                'user_id'        => auth()->id(),
                'mapping_config' => ['column_mappings' => $mapping],
                'is_default'     => false,
            ]);
        }

        $import = CsvImport::create([
            'user_id'         => auth()->id(),
            'filename'        => basename($filePath),
            'import_strategy' => $strategy,
            'status'          => 'PROCESSING',
            'started_at'      => now(),
            'total_rows'      => 0,
            'successful_rows' => 0,
            'failed_rows'     => 0,
        ]);

        $errors = [];
        $success = 0;
        $failed  = 0;
        $total   = 0;

        $lines        = Line::pluck('id', 'code');
        $productTypes = ProductType::pluck('id', 'code');

        $fullPath = Storage::disk('local')->path($filePath);
        if (!file_exists($fullPath)) {
            $import->update(['status' => 'FAILED', 'error_log' => ['File not found']]);
            return redirect()->route('admin.csv-import')
                ->with('error', 'Upload file not found. Please upload again.');
        }

        $handle  = fopen($fullPath, 'r');
        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $total++;
            $rowData = array_combine($headers, array_pad($row, count($headers), ''));

            try {
                $workOrderData = $this->mapRow($rowData, $mapping, $lines, $productTypes);
                $this->importRow($workOrderData, $strategy);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Row {$total}: " . $e->getMessage();
            }
        }
        fclose($handle);

        Storage::disk('local')->delete($filePath);

        $import->update([
            'total_rows'      => $total,
            'successful_rows' => $success,
            'failed_rows'     => $failed,
            'status'          => ($failed === 0) ? 'COMPLETED' : ($success === 0 ? 'FAILED' : 'COMPLETED'),
            'error_log'       => $errors ?: null,
            'completed_at'    => now(),
        ]);

        return redirect()->route('admin.csv-import')
            ->with('import_result', [
                'success' => $success,
                'failed'  => $failed,
                'total'   => $total,
                'errors'  => array_slice($errors, 0, 20),
            ]);
    }

    /**
     * Delete a saved mapping profile.
     */
    public function destroyMapping(CsvImportMapping $mapping)
    {
        if ($mapping->user_id !== auth()->id()) {
            abort(403);
        }
        $mapping->delete();

        return redirect()->route('admin.csv-import')
            ->with('success', 'Mapping profile deleted.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function mapRow(array $rowData, array $mapping, $lines, $productTypes): array
    {
        $workOrderData = [
            'status'     => WorkOrder::STATUS_PENDING,
            'extra_data' => [],
        ];

        foreach ($mapping as $csvColumn => $target) {
            $value = trim($rowData[$csvColumn] ?? '');

            if (empty($target) || $target === '_ignore') {
                continue;
            }

            if (str_starts_with($target, 'custom:')) {
                $key = substr($target, 7);
                if ($key !== '' && $value !== '') {
                    $workOrderData['extra_data'][$key] = $value;
                }
                continue;
            }

            switch ($target) {
                case 'order_no':
                    if ($value === '') throw new \Exception("order_no is empty");
                    $workOrderData['order_no'] = $value;
                    break;

                case 'product_name':
                    if ($value !== '') {
                        $workOrderData['extra_data']['product_name'] = $value;
                    }
                    break;

                case 'quantity':
                    if ($value === '') throw new \Exception("quantity is empty");
                    $workOrderData['planned_qty'] = (float) str_replace(',', '.', $value);
                    break;

                case 'line_code':
                    if ($value !== '') {
                        $lineId = $lines[$value] ?? null;
                        if (!$lineId) throw new \Exception("Line '{$value}' not found");
                        $workOrderData['line_id'] = $lineId;
                    }
                    break;

                case 'product_type_code':
                    if ($value !== '') {
                        $ptId = $productTypes[$value] ?? null;
                        if (!$ptId) throw new \Exception("Product type '{$value}' not found");
                        $workOrderData['product_type_id'] = $ptId;
                    }
                    break;

                case 'priority':
                    if ($value !== '') {
                        $workOrderData['priority'] = (int) $value;
                    }
                    break;

                case 'due_date':
                    if ($value !== '') {
                        try {
                            $workOrderData['due_date'] = \Carbon\Carbon::parse($value);
                        } catch (\Throwable) {
                            throw new \Exception("Invalid due_date: '{$value}'");
                        }
                    }
                    break;

                case 'description':
                    $workOrderData['description'] = $value;
                    break;
            }
        }

        if (empty($workOrderData['order_no'])) {
            throw new \Exception("Missing required field: order_no");
        }
        if (!isset($workOrderData['planned_qty'])) {
            throw new \Exception("Missing required field: quantity");
        }

        if (empty($workOrderData['extra_data'])) {
            unset($workOrderData['extra_data']);
        }

        return $workOrderData;
    }

    private function importRow(array $data, string $strategy): void
    {
        $orderNo = $data['order_no'];

        match ($strategy) {
            'update_or_create'    => WorkOrder::updateOrCreate(['order_no' => $orderNo], $data),
            'skip_existing'       => WorkOrder::where('order_no', $orderNo)->exists()
                                        ? null
                                        : WorkOrder::create($data),
            'error_on_duplicate'  => (function () use ($orderNo, $data) {
                if (WorkOrder::where('order_no', $orderNo)->exists()) {
                    throw new \Exception("Duplicate order_no '{$orderNo}'");
                }
                WorkOrder::create($data);
            })(),
        };
    }
}
