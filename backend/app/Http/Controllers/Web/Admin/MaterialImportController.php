<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialImportProcessRequest;
use App\Http\Requests\MaterialImportUploadRequest;
use App\Models\Material;
use App\Models\MaterialType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MaterialImportController extends Controller
{
    /**
     * Show the material import page.
     */
    public function index()
    {
        $materialTypes = MaterialType::orderBy('name')->get();
        $recentCount = Material::where('last_stock_sync_at', '>=', now()->subDay())->count();

        return view('admin.materials.import', compact('materialTypes', 'recentCount'));
    }

    /**
     * Handle file upload and show column-mapping UI.
     */
    public function upload(MaterialImportUploadRequest $request)
    {

        $file = $request->file('import_file');
        $path = $file->store('material-imports', 'local');

        [$headers, $allRows] = $this->parseFile(Storage::disk('local')->path($path));

        $previewRows = array_slice($allRows, 0, 5);
        $totalRows = count($allRows);

        $systemFields = $this->systemFields();
        $importStrategy = $request->import_strategy;
        $externalSystem = $request->input('external_system', '');

        return view('admin.materials.import-mapping', compact(
            'headers', 'previewRows', 'totalRows',
            'path', 'systemFields', 'importStrategy', 'externalSystem'
        ));
    }

    /**
     * Process the import with the provided column mapping.
     */
    public function process(MaterialImportProcessRequest $request)
    {

        $filePath = $request->input('file_path');
        if (! str_starts_with($filePath, 'material-imports/')
            || str_contains($filePath, '..')
            || str_contains($filePath, "\0")) {
            abort(422, 'Invalid file path.');
        }

        $strategy = $request->input('import_strategy');
        $mapping = $request->input('mapping');
        $externalSystem = $request->input('external_system', '');

        // Validate required fields are mapped
        $mappedTargets = array_filter(array_values($mapping), fn ($v) => $v && $v !== '_ignore');

        if (! in_array('code', $mappedTargets) && ! in_array('external_code', $mappedTargets)) {
            return redirect()->route('admin.materials.import')
                ->with('error', 'You must map at least "Code" or "External Code" to identify materials.');
        }
        if (! in_array('name', $mappedTargets)) {
            return redirect()->route('admin.materials.import')
                ->with('error', 'You must map the "Name" field.');
        }

        $fullPath = Storage::disk('local')->path($filePath);
        if (! file_exists($fullPath)) {
            return redirect()->route('admin.materials.import')
                ->with('error', 'Upload file not found. Please upload again.');
        }

        [, $rows] = $this->parseFile($fullPath);

        $typeCache = MaterialType::pluck('id', 'code')->toArray();
        $typeNameCache = MaterialType::pluck('id', 'name')->toArray();
        $defaultTypeId = $typeCache['raw_material'] ?? array_values($typeCache)[0] ?? null;

        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $total = 0;

        foreach ($rows as $rowData) {
            $total++;
            try {
                $data = $this->mapRow($rowData, $mapping, $typeCache, $typeNameCache, $defaultTypeId, $externalSystem);

                $existing = $this->findExistingMaterial($data);

                if ($existing) {
                    if ($strategy === 'create_only') {
                        $skipped++;

                        continue;
                    }
                    // Update existing
                    $updateData = array_filter($data, fn ($v) => $v !== null && $v !== '');
                    unset($updateData['code']); // don't change code on update
                    $existing->update($updateData);
                    $updated++;
                } else {
                    if ($strategy === 'skip_existing') {
                        // skip_existing = only update, don't create
                        $skipped++;

                        continue;
                    }
                    // Generate code if not provided
                    if (empty($data['code'])) {
                        $data['code'] = $this->generateUniqueCode($data['external_code'] ?? $data['name']);
                    }
                    if (empty($data['material_type_id'])) {
                        $data['material_type_id'] = $defaultTypeId;
                    }
                    Material::create($data);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$total}: ".$e->getMessage();
            }
        }

        Storage::disk('local')->delete($filePath);

        return redirect()->route('admin.materials.import')
            ->with('import_result', [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'total' => $total,
                'errors' => array_slice($errors, 0, 30),
            ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function findExistingMaterial(array $data): ?Material
    {
        // Try external_code + external_system first (best for Subiekt etc.)
        if (! empty($data['external_code']) && ! empty($data['external_system'])) {
            $m = Material::where('external_code', $data['external_code'])
                ->where('external_system', $data['external_system'])
                ->first();
            if ($m) {
                return $m;
            }
        }

        // Try EAN
        if (! empty($data['ean'])) {
            $m = Material::where('ean', $data['ean'])->first();
            if ($m) {
                return $m;
            }
        }

        // Try code
        if (! empty($data['code'])) {
            return Material::where('code', $data['code'])->first();
        }

        return null;
    }

    private function mapRow(array $rowData, array $mapping, array $typeCache, array $typeNameCache, ?int $defaultTypeId, string $externalSystem): array
    {
        $data = [
            'is_active' => true,
            'unit_of_measure' => 'pcs',
            'tracking_type' => 'none',
        ];

        if ($externalSystem !== '') {
            $data['external_system'] = $externalSystem;
            $data['last_stock_sync_at'] = now();
        }

        foreach ($mapping as $csvColumn => $target) {
            $value = trim($rowData[$csvColumn] ?? '');

            if (empty($target) || $target === '_ignore' || $value === '') {
                continue;
            }

            switch ($target) {
                case 'code':
                    $data['code'] = $value;
                    break;
                case 'name':
                    $data['name'] = $value;
                    break;
                case 'description':
                    $data['description'] = $value;
                    break;
                case 'external_code':
                    $data['external_code'] = $value;
                    break;
                case 'ean':
                    $data['ean'] = $value;
                    break;
                case 'unit_of_measure':
                    $data['unit_of_measure'] = $value;
                    break;
                case 'material_type':
                    $typeId = $typeCache[$value] ?? $typeNameCache[$value] ?? null;
                    if ($typeId) {
                        $data['material_type_id'] = $typeId;
                    }
                    break;
                case 'stock_quantity':
                    $data['stock_quantity'] = (float) str_replace(',', '.', $value);
                    break;
                case 'min_stock_level':
                    $data['min_stock_level'] = (float) str_replace(',', '.', $value);
                    break;
                case 'unit_price':
                    $data['unit_price'] = (float) str_replace(',', '.', $value);
                    break;
                case 'price_currency':
                    $data['price_currency'] = strtoupper(Str::limit($value, 3, ''));
                    break;
                case 'supplier_name':
                    $data['supplier_name'] = $value;
                    break;
                case 'supplier_code':
                    $data['supplier_code'] = $value;
                    break;
                case 'tracking_type':
                    if (in_array($value, ['none', 'batch', 'serial'])) {
                        $data['tracking_type'] = $value;
                    }
                    break;
                case 'default_scrap_percentage':
                    $data['default_scrap_percentage'] = (float) str_replace(',', '.', $value);
                    break;
            }
        }

        if (empty($data['name'])) {
            throw new \Exception('Name is empty');
        }

        return $data;
    }

    private function generateUniqueCode(string $source): string
    {
        $code = Str::upper(Str::slug($source, '-'));
        $code = Str::limit($code, 47, '');

        if (! Material::where('code', $code)->exists()) {
            return $code;
        }

        $i = 1;
        while (Material::where('code', "{$code}-{$i}")->exists()) {
            $i++;
        }

        return "{$code}-{$i}";
    }

    /**
     * System fields available for mapping.
     */
    private function systemFields(): array
    {
        return [
            '_ignore' => '-- Ignore this column --',
            'code' => 'Code (internal)',
            'name' => 'Name *',
            'description' => 'Description',
            'external_code' => 'External Code (e.g. Subiekt symbol)',
            'ean' => 'EAN / Barcode',
            'material_type' => 'Material Type (code or name)',
            'unit_of_measure' => 'Unit of Measure',
            'stock_quantity' => 'Stock Quantity',
            'min_stock_level' => 'Min Stock Level',
            'unit_price' => 'Unit Price',
            'price_currency' => 'Price Currency',
            'supplier_name' => 'Supplier Name',
            'supplier_code' => 'Supplier Code',
            'tracking_type' => 'Tracking Type (none/batch/serial)',
            'default_scrap_percentage' => 'Default Scrap %',
        ];
    }

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
        $handle = fopen($fullPath, 'r');
        $headers = fgetcsv($handle, 0, ',') ?: [];

        // Try semicolon if only one header detected
        if (count($headers) <= 1) {
            rewind($handle);
            $headers = fgetcsv($handle, 0, ';') ?: [];
            $delimiter = ';';
        } else {
            $delimiter = ',';
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return [$headers, $rows];
    }

    private function parseSpreadsheet(string $fullPath): array
    {
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rawData = $sheet->toArray(null, true, true, false);

        if (empty($rawData)) {
            return [[], []];
        }

        $rawHeaders = array_map(fn ($v) => trim((string) ($v ?? '')), array_shift($rawData));
        $headerMap = [];
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
            if (empty(array_filter($assoc, fn ($v) => $v !== ''))) {
                continue;
            }
            $rows[] = $assoc;
        }

        return [$headers, $rows];
    }
}
