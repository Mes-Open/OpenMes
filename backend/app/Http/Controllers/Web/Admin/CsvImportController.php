<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CsvImport;
use App\Models\CsvImportMapping;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $systemFields      = CsvImportMapping::systemFields();
        $lines             = Line::where('is_active', true)->orderBy('name')->get();
        $productionPeriod  = $this->getProductionPeriod();

        return view('admin.csv-import', compact('recentImports', 'savedMappings', 'systemFields', 'lines', 'productionPeriod'));
    }

    /**
     * Handle file upload (CSV / XLS / XLSX) and show the column-mapping UI.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'csv_file'        => 'required|file|mimes:csv,txt,xlsx,xls|max:32768',
            'import_strategy' => 'required|in:update_or_create,skip_existing,error_on_duplicate',
            'mapping_id'      => 'nullable|exists:csv_import_mappings,id',
            'target_line_id'  => 'nullable|exists:lines,id',
            'import_week'     => 'nullable|integer|min:1|max:53',
            'import_month'    => 'nullable|integer|min:1|max:12',
            'production_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $file = $request->file('csv_file');
        $path = $file->store('csv-imports', 'local');

        [$headers, $allRows] = $this->parseFile(Storage::disk('local')->path($path));

        $previewRows = array_slice($allRows, 0, 5);
        $totalRows   = count($allRows);

        // Load existing mapping if selected
        $existingMapping = null;
        if ($request->filled('mapping_id')) {
            $existingMapping = CsvImportMapping::find($request->mapping_id);
        }

        $savedMappings = CsvImportMapping::where('user_id', auth()->id())
            ->orWhere('is_default', true)
            ->orderBy('name')
            ->get();

        $systemFields     = CsvImportMapping::systemFields();
        $importStrategy   = $request->import_strategy;
        $targetLineId     = $request->input('target_line_id');
        $importWeek       = $request->input('import_week');
        $importMonth      = $request->input('import_month');
        $productionYear   = $request->input('production_year', now()->year);
        $productionPeriod = $this->getProductionPeriod();

        return view('admin.csv-import-mapping', compact(
            'headers', 'previewRows', 'totalRows',
            'path', 'savedMappings', 'systemFields',
            'existingMapping', 'importStrategy',
            'targetLineId', 'importWeek', 'importMonth', 'productionYear', 'productionPeriod'
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
            'target_line_id'  => 'nullable|exists:lines,id',
            'import_week'     => 'nullable|integer|min:1|max:53',
            'import_month'    => 'nullable|integer|min:1|max:12',
            'production_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $filePath       = $request->input('file_path');
        $strategy       = $request->input('import_strategy');
        $mapping        = $request->input('mapping');
        $targetLineId   = $request->input('target_line_id');
        $importWeek     = $request->input('import_week') ? (int) $request->input('import_week') : null;
        $importMonth    = $request->input('import_month') ? (int) $request->input('import_month') : null;
        $productionYear = $request->input('production_year') ? (int) $request->input('production_year') : null;

        // --- Server-side pre-validation: required fields must be mapped ---
        $mappedTargets   = array_filter(array_values($mapping), fn($v) => $v && $v !== '_ignore');
        $missingRequired = [];
        if (!in_array('order_no', $mappedTargets)) $missingRequired[] = 'order_no';
        if (!in_array('quantity', $mappedTargets))  $missingRequired[] = 'quantity';

        if (!empty($missingRequired)) {
            $fullPath = Storage::disk('local')->path($filePath);
            if (file_exists($fullPath)) {
                [$headers, $allRows] = $this->parseFile($fullPath);
                $previewRows      = array_slice($allRows, 0, 5);
                $totalRows        = count($allRows);
                $savedMappings    = CsvImportMapping::where('user_id', auth()->id())->orWhere('is_default', true)->orderBy('name')->get();
                $systemFields     = CsvImportMapping::systemFields();
                $existingMapping  = null;
                $importStrategy   = $strategy;
                $productionPeriod = $this->getProductionPeriod();

                $path = $filePath; // view expects $path (the stored file path)

                return view('admin.csv-import-mapping', compact(
                    'headers', 'previewRows', 'totalRows',
                    'path', 'savedMappings', 'systemFields',
                    'existingMapping', 'importStrategy',
                    'targetLineId', 'importWeek', 'importMonth', 'productionYear', 'productionPeriod'
                ))->with('mapping_error', 'Required fields not mapped: ' . implode(', ', $missingRequired) . '. Please assign these columns before importing.')
                  ->with('prev_mapping', $mapping);
            }

            return redirect()->route('admin.csv-import')
                ->with('error', 'Required fields (order_no, quantity) were not mapped. Please upload the file again.');
        }

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

        [, $rows] = $this->parseFile($fullPath);

        foreach ($rows as $rowData) {
            $total++;
            try {
                $workOrderData = $this->mapRow($rowData, $mapping, $lines, $productTypes);

                // Apply target line (overrides column-mapped line if set)
                if ($targetLineId) {
                    $workOrderData['line_id'] = (int) $targetLineId;
                }

                // Apply planning period fields
                if ($importWeek)     $workOrderData['week_number']    = $importWeek;
                if ($importMonth)    $workOrderData['month_number']   = $importMonth;
                if ($productionYear) $workOrderData['production_year'] = $productionYear;

                $this->importRow($workOrderData, $strategy);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Row {$total}: " . $e->getMessage();
            }
        }

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

    /**
     * Dispatch to the correct parser based on file extension.
     * Returns [$headers, $dataRows] where each $dataRow is an assoc array.
     */
    private function parseFile(string $fullPath): array
    {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($ext, ['xlsx', 'xls'])) {
            return $this->parseSpreadsheet($fullPath);
        }

        return $this->parseCsv($fullPath);
    }

    private function parseCsv(string $fullPath): array
    {
        $handle  = fopen($fullPath, 'r');
        $headers = fgetcsv($handle) ?: [];
        $rows    = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, array_pad($row, count($headers), ''));
        }

        fclose($handle);

        return [$headers, $rows];
    }

    private function parseSpreadsheet(string $fullPath): array
    {
        $spreadsheet = IOFactory::load($fullPath);
        $sheet       = $spreadsheet->getActiveSheet();

        // toArray(nullValue, calculateFormulas, formatData, returnCellRef)
        $rawData = $sheet->toArray(null, true, true, false);

        if (empty($rawData)) {
            return [[], []];
        }

        // First row is headers; normalise to strings, skip empty columns
        $rawHeaders = array_map(fn($v) => trim((string) ($v ?? '')), array_shift($rawData));
        $headerMap  = [];
        foreach ($rawHeaders as $i => $h) {
            if ($h !== '') {
                $headerMap[$i] = $h;
            }
        }
        $headers = array_values($headerMap);

        $rows = [];
        foreach ($rawData as $rawRow) {
            $assoc = [];
            foreach ($headerMap as $i => $header) {
                $assoc[$header] = trim((string) ($rawRow[$i] ?? ''));
            }
            // Skip completely empty rows (e.g. trailing blank rows in Excel)
            if (empty(array_filter($assoc, fn($v) => $v !== ''))) {
                continue;
            }
            $rows[] = $assoc;
        }

        return [$headers, $rows];
    }

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

    private function getProductionPeriod(): string
    {
        try {
            $row = DB::table('system_settings')->where('key', 'production_period')->first();
            return json_decode($row->value ?? '"none"', true) ?? 'none';
        } catch (\Throwable) {
            return 'none';
        }
    }
}
