<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CsvImport\CsvParserService;
use App\Services\CsvImport\WorkOrderImportService;
use App\Models\CsvImport;
use App\Models\CsvImportMapping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CsvImportController extends Controller
{
    public function __construct(
        protected CsvParserService $csvParser,
        protected WorkOrderImportService $importService
    ) {}

    /**
     * Upload CSV file and return preview.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $filePath = $this->csvParser->storeTemporary($file);

            // Parse and get preview
            $parseResult = $this->csvParser->parse($filePath, 5);

            // Generate upload ID for tracking
            $uploadId = uniqid('upload_');

            // Store metadata in session/cache for later use
            cache()->put("csv_upload_{$uploadId}", [
                'file_path' => $filePath,
                'filename' => $file->getClientOriginalName(),
                'headers' => $parseResult['headers'],
                'total_rows' => $parseResult['total_rows'],
            ], now()->addHours(2));

            return response()->json([
                'data' => [
                    'upload_id' => $uploadId,
                    'filename' => $file->getClientOriginalName(),
                    'headers' => $parseResult['headers'],
                    'preview' => $parseResult['preview'],
                    'total_rows' => $parseResult['total_rows'],
                ],
                'message' => 'CSV uploaded successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('CSV upload failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to process CSV file',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Execute CSV import with mapping.
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => 'required|string',
            'mapping' => 'required|array',
            'mapping.import_strategy' => 'required|in:update_or_create,skip_existing,error_on_duplicate',
            'mapping.columns' => 'required|array',
            'save_mapping_template' => 'boolean',
            'mapping_template_name' => 'nullable|string|max:255',
        ]);

        // Retrieve upload metadata
        $uploadData = cache()->get("csv_upload_{$validated['upload_id']}");

        if (!$uploadData) {
            return response()->json([
                'message' => 'Upload session expired. Please re-upload the CSV file.',
            ], 404);
        }

        try {
            // Validate mapping
            $errors = $this->csvParser->validateMapping(
                $uploadData['headers'],
                $validated['mapping']['columns']
            );

            if (!empty($errors)) {
                return response()->json([
                    'message' => 'Invalid column mapping',
                    'errors' => $errors,
                ], 422);
            }

            // Save mapping template if requested
            if ($request->boolean('save_mapping_template') && $request->filled('mapping_template_name')) {
                CsvImportMapping::updateOrCreate(
                    [
                        'name' => $validated['mapping_template_name'],
                        'user_id' => $request->user()->id,
                    ],
                    [
                        'mapping_config' => $validated['mapping'],
                    ]
                );
            }

            // Create import record
            $csvImport = CsvImport::create([
                'user_id' => $request->user()->id,
                'filename' => $uploadData['filename'],
                'import_strategy' => $validated['mapping']['import_strategy'],
                'total_rows' => $uploadData['total_rows'],
                'status' => 'PENDING',
            ]);

            // Dispatch import job (async)
            \App\Jobs\ProcessCsvImport::dispatch(
                $csvImport->id,
                $uploadData['file_path'],
                $validated['mapping']
            );

            return response()->json([
                'data' => [
                    'import_id' => $csvImport->id,
                    'status' => 'PENDING',
                    'total_rows' => $uploadData['total_rows'],
                ],
                'message' => 'CSV import started. You will be notified when it completes.',
            ], 202);
        } catch (\Exception $e) {
            Log::error('CSV import execution failed', [
                'upload_id' => $validated['upload_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to start CSV import',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get import status.
     */
    public function status(CsvImport $csvImport): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $csvImport->id,
                'filename' => $csvImport->filename,
                'status' => $csvImport->status,
                'total_rows' => $csvImport->total_rows,
                'successful_rows' => $csvImport->successful_rows,
                'failed_rows' => $csvImport->failed_rows,
                'error_log' => $csvImport->error_log,
                'started_at' => $csvImport->started_at,
                'completed_at' => $csvImport->completed_at,
            ],
        ]);
    }

    /**
     * List all imports for current user.
     */
    public function index(Request $request): JsonResponse
    {
        $imports = CsvImport::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $imports->items(),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'per_page' => $imports->perPage(),
                'total' => $imports->total(),
            ],
        ]);
    }

    /**
     * Get saved mapping templates.
     */
    public function mappings(Request $request): JsonResponse
    {
        $mappings = CsvImportMapping::where('user_id', $request->user()->id)
            ->orWhere('is_default', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $mappings,
        ]);
    }

    /**
     * Save or update a mapping template.
     */
    public function saveMapping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mapping_config' => 'required|array',
            'is_default' => 'boolean',
        ]);

        $mapping = CsvImportMapping::updateOrCreate(
            [
                'name' => $validated['name'],
                'user_id' => $request->user()->id,
            ],
            [
                'mapping_config' => $validated['mapping_config'],
                'is_default' => $validated['is_default'] ?? false,
            ]
        );

        return response()->json([
            'data' => $mapping,
            'message' => 'Mapping template saved successfully',
        ]);
    }
}
