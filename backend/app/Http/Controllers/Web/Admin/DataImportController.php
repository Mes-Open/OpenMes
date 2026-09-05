<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\ServesBothSections;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Import\PreviewImportRequest;
use App\Http\Requests\Web\Admin\Import\ProcessImportRequest;
use App\Http\Requests\Web\Admin\Import\UploadImportFileRequest;
use App\Import\Contracts\EntityImporter;
use App\Import\ImportRegistry;
use App\Jobs\ProcessDataImport;
use App\Models\CsvImport;
use App\Models\CsvImportMapping;
use App\Models\User;
use App\Services\Import\ParseHealth;
use App\Services\Import\RowMapper;
use App\Services\Import\SpreadsheetReader;
use App\Support\Csv;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The unified importer (Admin → Import, also mounted for supervisors): pick an
 * entity, upload a CSV/XLS/XLSX, map its columns, queue the run, watch it.
 *
 * Every entity-specific fact — fields, options, warnings, samples, the writes —
 * comes from the entity's importer in App\Import; this controller only moves
 * the file and the mapping between the three screens and the queue.
 */
class DataImportController extends Controller
{
    use ServesBothSections;

    /**
     * Rows read for the preview. Enough to be searched and paged through like
     * any other list on the site — five rows told you the columns had parsed,
     * not whether the data further down the file is what you expect. The cost
     * is bounded: the reader stops here, so a 5k-row file is not loaded to show
     * a sample of it.
     */
    private const PREVIEW_ROWS = 200;

    private const HISTORY_ROWS = 50;

    /** Errors shown inline under a history row; the run page has them all. */
    private const HISTORY_ERRORS = 5;

    private const TEMP_DIR = 'imports';

    public function __construct(
        private ImportRegistry $registry,
        private SpreadsheetReader $reader,
        private ParseHealth $health,
        private RowMapper $mapper,
    ) {}

    public function index(?string $entity = null): InertiaResponse
    {
        $available = $this->registry->forSection($this->section());
        $importer = $entity !== null
            ? $this->resolve($entity)
            : (reset($available) ?: abort(404));

        return Inertia::render('admin/import/Index', [
            'basePath' => $this->basePath('/import'),
            'entities' => $this->entityList(),
            'entity' => $importer->describe(),
            'profiles' => $this->profilesFor($importer),
            'lines' => \App\Models\Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'limits' => [
                'maxSizeMb' => (int) (UploadImportFileRequest::MAX_KB / 1024),
                'delimiters' => SpreadsheetReader::DELIMITERS,
                'encodings' => SpreadsheetReader::ENCODINGS,
            ],
            // Initial history; the row set then stays live through the
            // `data_imports` collection.
            'recentImports' => $this->recentImports(),
            'userNames' => $this->userNames(),
        ]);
    }

    public function upload(UploadImportFileRequest $request, string $entity)
    {
        $importer = $this->resolve($entity);
        $file = $request->file('file');

        $ext = strtolower($file->getClientOriginalExtension());
        $ext = in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true) ? $ext : 'csv';
        $path = $file->storeAs(self::TEMP_DIR, 'imp_'.Str::random(24).'.'.$ext, 'local');

        $fileOptions = [
            'delimiter' => $request->validated('delimiter') ?: 'auto',
            'encoding' => $request->validated('encoding') ?: 'utf-8',
        ];

        try {
            $parsed = $this->reader->read(Storage::disk('local')->path($path), $fileOptions, self::PREVIEW_ROWS);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => __('The file could not be read. Check the format, separator and encoding.'),
            ]);
        }

        if ($parsed['headers'] === []) {
            Storage::disk('local')->delete($path);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => __('The file has no header row.'),
            ]);
        }

        $profile = $request->validated('mapping_id')
            ? CsvImportMapping::find($request->validated('mapping_id'))
            : null;

        // The mapping step lives on its own GET page so a refresh, a 422 from
        // the process step or the browser's back button all land somewhere
        // real. The upload is remembered in the session under a token; the
        // file path never reaches the browser.
        $token = Str::random(32);

        session()->put($this->uploadKey($token), [
            'entity' => $importer->key(),
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_options' => $fileOptions,
            'options' => $request->runOptions(),
            'mapping' => $profile?->columnMappings(),
        ]);

        // The upload screen previews the file in place before committing to the
        // mapping step, so it asks for JSON and stays where it is. Everything
        // else — a plain form post, a browser without JS — still redirects.
        if ($request->expectsJson()) {
            return response()->json([
                'token' => $token,
                'headers' => $parsed['headers'],
                'previewRows' => $this->stripRowKeys($parsed['rows']),
                'totalRows' => $parsed['total'],
                'warnings' => $this->health->inspect(Storage::disk('local')->path($path), $fileOptions, $parsed),
                'fileOptions' => $fileOptions,
                'originalFilename' => $file->getClientOriginalName(),
                'mapUrl' => $this->sectionRoute('import.map', ['entity' => $importer->slug(), 'token' => $token]),
            ]);
        }

        return redirect()->to($this->sectionRoute('import.map', ['entity' => $importer->slug(), 'token' => $token]));
    }

    public function map(string $entity, string $token)
    {
        $importer = $this->resolve($entity);
        $upload = $this->rememberedUpload($importer, $token);

        if (! $upload) {
            return redirect()->to($this->sectionRoute('import.index', ['entity' => $importer->slug()]))
                ->with('error', __('The uploaded file is no longer available. Please upload it again.'));
        }

        // upload() converts a read failure into a validation error; this page is
        // reached again by refresh, by the back button and after a 422 from
        // process(), so a file that has become unreadable since must not 500.
        try {
            $parsed = $this->reader->read(Storage::disk('local')->path($upload['file_path']), $upload['file_options'], self::PREVIEW_ROWS);
        } catch (\Throwable $e) {
            return redirect()->to($this->sectionRoute('import.index', ['entity' => $importer->slug()]))
                ->with('error', __('The file could not be read. Check the format, separator and encoding.'));
        }

        return Inertia::render('admin/import/Mapping', [
            'basePath' => $this->basePath('/import'),
            'entity' => $importer->describe(),
            'profiles' => $this->profilesFor($importer),
            'token' => $token,
            'headers' => $parsed['headers'],
            'previewRows' => $this->stripRowKeys($parsed['rows']),
            'totalRows' => $parsed['total'],
            'warnings' => $this->health->inspect(Storage::disk('local')->path($upload['file_path']), $upload['file_options'], $parsed),
            // The preview offers the same parse settings the upload step did.
            'limits' => [
                'delimiters' => SpreadsheetReader::DELIMITERS,
                'encodings' => SpreadsheetReader::ENCODINGS,
            ],
            'originalFilename' => $upload['original_filename'],
            'fileOptions' => $upload['file_options'],
            'options' => $upload['options'],
            'initialMapping' => $upload['mapping'],
        ]);
    }

    /**
     * Re-read an upload the session already holds with different parse settings.
     *
     * Separator and encoding are chosen on the upload screen, before the user
     * can see a single row; getting either wrong used to mean re-uploading. The
     * file is already on disk, so the mapping screen re-reads it instead — and
     * the choice is remembered, because it is the one the real run will use.
     */
    public function preview(PreviewImportRequest $request, string $entity, string $token)
    {
        $importer = $this->resolve($entity);
        $upload = $this->rememberedUpload($importer, $token);

        if (! $upload) {
            return response()->json(['message' => __('The uploaded file is no longer available. Please upload it again.')], 410);
        }

        $fileOptions = $request->fileOptions();
        $path = Storage::disk('local')->path($upload['file_path']);
        $parsed = $this->reader->read($path, $fileOptions, self::PREVIEW_ROWS);

        // The settings the user just previewed are the ones process() must run
        // with, so remember them alongside the upload. The run options travel
        // here too: the file is uploaded as soon as it is picked, so anything
        // the user changes afterwards would otherwise never reach the session.
        $remember = ['file_options' => $fileOptions] + $upload;

        if ($request->has('options')) {
            $remember['options'] = $request->runOptions();
        }

        if ($request->filled('mapping_id')) {
            $remember['mapping'] = CsvImportMapping::find($request->validated('mapping_id'))?->columnMappings();
        }

        session()->put($this->uploadKey($token), $remember);

        return response()->json([
            'headers' => $parsed['headers'],
            'previewRows' => $this->stripRowKeys($parsed['rows']),
            'totalRows' => $parsed['total'],
            'warnings' => $this->health->inspect($path, $fileOptions, $parsed),
            'problems' => $this->cellProblems($parsed['rows'], $request->mapping(), $importer),
            'fileOptions' => $fileOptions,
        ]);
    }

    /**
     * Per-cell coercion failures for the previewed rows, as
     * [rowIndex => [header => reason]] — what the import would reject, shown on
     * the cell that causes it instead of as a row number after the run.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $mapping
     * @return array<int, array<string, string>>
     */
    private function cellProblems(array $rows, array $mapping, EntityImporter $importer): array
    {
        if ($mapping === []) {
            return [];
        }

        $out = [];

        foreach (array_values($rows) as $i => $row) {
            $problems = $this->mapper->problems($row, $mapping, $importer);

            if ($problems !== []) {
                $out[$i] = $problems;
            }
        }

        return $out;
    }

    public function process(ProcessImportRequest $request, string $entity)
    {
        $importer = $this->resolve($entity);
        $token = $request->validated('token');
        $upload = $this->rememberedUpload($importer, $token);

        if (! $upload) {
            return redirect()->to($this->sectionRoute('import.index', ['entity' => $importer->slug()]))
                ->with('error', __('The uploaded file is no longer available. Please upload it again.'));
        }

        $mapping = $request->mapping();
        $mapUrl = $this->sectionRoute('import.map', ['entity' => $importer->slug(), 'token' => $token]);

        $missing = $importer->missingIdentifiers($mapping);

        if ($missing !== []) {
            // Keep what the user mapped so far and send them back to the page.
            session()->put($this->uploadKey($token), ['mapping' => $mapping] + $upload);

            // Name the fields the way the page does, not by key.
            $labels = array_map(fn ($f) => $f['label'], $importer->fields());
            $named = array_map(
                fn ($m) => implode(' | ', array_map(fn ($k) => $labels[$k] ?? $k, explode(' | ', $m))),
                $missing,
            );

            return redirect()->to($mapUrl)->withErrors([
                'mapping' => __('Required fields not mapped: :fields. Assign these columns before importing.', [
                    'fields' => implode(', ', $named),
                ]),
            ]);
        }

        if ($request->filled('save_mapping_name')) {
            CsvImportMapping::create([
                'name' => $request->validated('save_mapping_name'),
                'entity' => $importer->key(),
                'user_id' => auth()->id(),
                'mapping_config' => ['column_mappings' => $mapping],
                'is_default' => false,
            ]);
        }

        $runOptions = $upload['options'];

        $import = CsvImport::create([
            'user_id' => auth()->id(),
            'entity' => $importer->key(),
            'filename' => basename($upload['file_path']),
            'original_filename' => $upload['original_filename'] ?: basename($upload['file_path']),
            'file_path' => $upload['file_path'],
            'import_strategy' => (string) ($runOptions['strategy'] ?? $runOptions['mode'] ?? 'update_or_create'),
            'dry_run' => $request->isDryRun(),
            'options' => [
                'token' => $token,
                'mapping' => $mapping,
                'delimiter' => $upload['file_options']['delimiter'],
                'encoding' => $upload['file_options']['encoding'],
                'options' => $runOptions,
            ],
            'status' => CsvImport::STATUS_PENDING,
        ]);

        // A validation keeps its upload: the user's next step is to run the real
        // import from the same file and mapping, and re-uploading to do that
        // would defeat the point of validating first.
        if (! $import->dry_run) {
            session()->forget($this->uploadKey($token));
        }

        ProcessDataImport::dispatch($import->id);

        return redirect()->to($this->sectionRoute('import.show', ['import' => $import->id]))
            ->with('success', $import->dry_run
                ? __('Validation queued. The file is checked row by row and nothing is saved.')
                : __('Import queued. Progress updates on this page as rows are processed.'));
    }

    /**
     * A run belongs to this section, or it does not exist here.
     *
     * An entity the registry no longer knows (a legacy or future `entity`
     * value) is not "allowed by default": it cannot be checked, so it is not
     * shown. Supervisors reach the same routes under /supervisor and may only
     * see work orders.
     */
    private function authorizeRun(CsvImport $import): EntityImporter
    {
        $importer = $this->registry->get($import->entity);

        if (! $importer || ! $this->registry->allowedIn($this->section(), $importer)) {
            abort(404);
        }

        return $importer;
    }

    public function show(CsvImport $import): InertiaResponse
    {
        $importer = $this->authorizeRun($import);

        return Inertia::render('admin/import/Show', [
            'basePath' => $this->basePath('/import'),
            'import' => $this->importPayload($import),
            'entity' => ['key' => $importer->key(), 'slug' => $importer->slug(), 'label' => $importer->label()],
            'userName' => $import->user?->name,
        ]);
    }

    public function errors(CsvImport $import): Response
    {
        // The rows carry master-data codes, names and free-text messages, so
        // this needs the same gate as the page it is downloaded from.
        $this->authorizeRun($import);

        $csv = Csv::row([__('Row'), __('Field'), __('Message')]);

        foreach ($import->error_log ?? [] as $error) {
            $csv .= is_array($error)
                ? Csv::row([(string) ($error['row'] ?? ''), (string) ($error['field'] ?? ''), (string) ($error['message'] ?? $error['error'] ?? '')])
                : Csv::row(['', '', (string) $error]);
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="import-'.$import->id.'-errors.csv"',
        ]);
    }

    public function destroyProfile(CsvImportMapping $mapping)
    {
        if ($mapping->user_id !== auth()->id()) {
            abort(403);
        }

        $mapping->delete();

        return redirect()->back()->with('success', __('Mapping profile deleted.'));
    }

    public function sample(string $entity): Response
    {
        $importer = $this->resolve($entity);
        $sample = $importer->sample();

        $csv = Csv::row($sample['headers']);

        foreach ($sample['rows'] as $row) {
            $csv .= Csv::row(array_map('strval', $row));
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$importer->slug().'-sample.csv"',
        ]);
    }

    // -------------------------------------------------------------------------

    private function resolve(string $slug): EntityImporter
    {
        $importer = $this->registry->fromSlug($slug);

        if (! $importer || ! $this->registry->allowedIn($this->section(), $importer)) {
            abort(404);
        }

        return $importer;
    }

    /** @return list<array<string, string>> */
    private function entityList(): array
    {
        return array_values(array_map(fn (EntityImporter $i) => [
            'key' => $i->key(),
            'slug' => $i->slug(),
            'label' => $i->label(),
            'description' => $i->description(),
        ], $this->registry->forSection($this->section())));
    }

    private function profilesFor(EntityImporter $importer)
    {
        return CsvImportMapping::where('entity', $importer->key())
            ->where(fn ($q) => $q->where('user_id', auth()->id())->orWhere('is_default', true))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (CsvImportMapping $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'is_default' => $m->is_default,
                'own' => $m->user_id === auth()->id(),
                'column_mappings' => $m->columnMappings(),
            ])
            ->values();
    }

    private function uploadKey(string $token): string
    {
        return 'import.uploads.'.$token;
    }

    /**
     * The remembered upload for this token, if it belongs to this entity and
     * its file is still on disk.
     *
     * @return array<string, mixed>|null
     */
    private function rememberedUpload(EntityImporter $importer, string $token): ?array
    {
        $upload = session()->get($this->uploadKey($token));

        if (! is_array($upload) || ($upload['entity'] ?? null) !== $importer->key()) {
            return null;
        }

        if (! Storage::disk('local')->exists($upload['file_path'])) {
            session()->forget($this->uploadKey($token));

            return null;
        }

        return $upload;
    }

    private function recentImports()
    {
        // Only the entities this section can open: a supervisor listing an
        // admin-only run would be showing a row whose link 404s, and naming a
        // master-data file they cannot otherwise see.
        return CsvImport::whereIn('entity', array_keys($this->registry->forSection($this->section())))
            ->orderByDesc('id')
            ->limit(self::HISTORY_ROWS)
            ->get()
            ->map(fn (CsvImport $i) => $this->importPayload($i, errorLimit: self::HISTORY_ERRORS));
    }

    /** @return array<int, string> */
    private function userNames(): array
    {
        $ids = CsvImport::orderByDesc('id')->limit(self::HISTORY_ROWS)->pluck('user_id')->filter()->unique();

        return User::whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** @return array<string, mixed> */
    private function importPayload(CsvImport $import, ?int $errorLimit = null): array
    {
        $payload = $import->only([
            'id', 'entity', 'original_filename', 'filename', 'status', 'dry_run', 'total_rows', 'processed_rows',
            'created_rows', 'updated_rows', 'skipped_rows', 'failed_rows', 'user_id',
        ]);

        // Only a validation still has its upload; a real run deleted it.
        $payload['token'] = $import->dry_run ? ($import->options['token'] ?? null) : null;

        $payload['started_at'] = $import->started_at?->toIso8601String();
        $payload['completed_at'] = $import->completed_at?->toIso8601String();
        $payload['created_at'] = $import->created_at?->toIso8601String();
        $payload['progress'] = $import->progress();

        $errors = $import->error_log ?? [];

        // Older rows (the work-order-only importer) stored plain strings.
        $payload['errors'] = array_values(array_map(
            fn ($e) => is_array($e)
                ? ['row' => $e['row'] ?? null, 'field' => $e['field'] ?? null, 'message' => $e['message'] ?? $e['error'] ?? '']
                : ['row' => null, 'field' => null, 'message' => (string) $e],
            $errorLimit === null ? $errors : array_slice($errors, 0, $errorLimit),
        ));

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function stripRowKeys(array $rows): array
    {
        return array_map(function (array $row) {
            unset($row[SpreadsheetReader::ROW_KEY]);

            return $row;
        }, $rows);
    }
}
