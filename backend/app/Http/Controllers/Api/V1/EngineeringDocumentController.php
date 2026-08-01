<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEngineeringDocumentRequest;
use App\Models\EngineeringDocument;
use App\Services\Engineering\EngineeringDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST surface for engineering CAD documents (#179). Listing/viewing/downloading
 * needs `view engineering documents`; uploading and lifecycle changes need
 * `manage engineering documents`. Files are streamed from the private disk with
 * X-Content-Type-Options: nosniff; only PDF/image are inline, everything else is
 * a forced download. The sandboxed interactive-HTML viewer is a later phase.
 */
class EngineeringDocumentController extends Controller
{
    public function __construct(private EngineeringDocumentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $q = EngineeringDocument::query()->latest('id');

        if ($request->filled('entity_type')) {
            $q->where('entity_type', $request->string('entity_type'));
        }
        if ($request->filled('entity_id')) {
            $q->where('entity_id', $request->integer('entity_id'));
        }
        if ($request->filled('revision')) {
            $q->where('revision', $request->string('revision'));
        }
        if ($request->filled('lifecycle_status')) {
            $q->where('lifecycle_status', $request->string('lifecycle_status'));
        }

        return response()->json(['data' => $q->paginate(50)]);
    }

    public function show(Request $request, EngineeringDocument $engineeringDocument): JsonResponse
    {
        $this->authorizeView($request);

        return response()->json(['data' => $engineeringDocument]);
    }

    public function store(StoreEngineeringDocumentRequest $request): JsonResponse
    {
        $doc = $this->service->store($request->validated(), $request->file('file'), $request->user());

        return response()->json(['data' => $doc], 201);
    }

    public function download(Request $request, EngineeringDocument $engineeringDocument): StreamedResponse
    {
        $this->authorizeView($request);

        $disk = Storage::disk($engineeringDocument->disk());
        abort_unless($disk->exists($engineeringDocument->storage_path), 404, 'File no longer exists on storage.');

        $headers = [
            'Content-Type' => $engineeringDocument->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // PDF and image may be shown inline; every other engineering format is a
        // forced download (never sniffed, never rendered as active content here).
        $disposition = $engineeringDocument->package_type->isInlineViewable() ? 'inline' : 'attachment';

        return $disk->download($engineeringDocument->storage_path, $engineeringDocument->original_filename, $headers + [
            'Content-Disposition' => $disposition.'; filename="'.addslashes($engineeringDocument->original_filename).'"',
        ]);
    }

    public function release(Request $request, EngineeringDocument $engineeringDocument): JsonResponse
    {
        $this->authorizeManage($request);
        $doc = $this->service->release($engineeringDocument, $request->user());

        return response()->json(['data' => $doc]);
    }

    public function obsolete(Request $request, EngineeringDocument $engineeringDocument): JsonResponse
    {
        $this->authorizeManage($request);
        $doc = $this->service->obsolete($engineeringDocument);

        return response()->json(['data' => $doc]);
    }

    public function destroy(Request $request, EngineeringDocument $engineeringDocument): JsonResponse
    {
        $this->authorizeManage($request);

        // Released documents are immutable and kept for traceability — obsolete
        // them instead of deleting.
        abort_if($engineeringDocument->isImmutable(), 422, 'A released document cannot be deleted; obsolete it instead.');

        $engineeringDocument->delete();

        return response()->json(['message' => 'Engineering document deleted.']);
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can('view engineering documents'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can('manage engineering documents'), 403);
    }
}
