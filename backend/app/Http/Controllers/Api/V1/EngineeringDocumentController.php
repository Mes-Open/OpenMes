<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EngineeringDocumentLifecycle;
use App\Enums\EngineeringPackageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEngineeringDocumentRequest;
use App\Models\EngineeringDocument;
use App\Services\Engineering\EngineeringDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\HeaderUtils;
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

        return response()->json([
            // The panel is entity-scoped and reads the first page; 200 comfortably
            // covers every document a single entity would ever have.
            'data' => $q->paginate(200),
            // Lets the UI decide whether to show upload / lifecycle controls
            // without leaking the full permission set to the frontend.
            'can_manage' => (bool) $request->user()?->can('manage engineering documents'),
        ]);
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

    /**
     * The engineering documents frozen onto a work order at release (#179 Phase 2).
     * Returns the immutable snapshot references (id + revision + checksum + lifecycle)
     * — not the live documents — so a newer upload never changes what a released
     * order shows.
     */
    public function forWorkOrder(Request $request, \App\Models\WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeView($request);

        return response()->json([
            'data' => $workOrder->process_snapshot['engineering_documents'] ?? [],
        ]);
    }

    public function download(Request $request, EngineeringDocument $engineeringDocument): StreamedResponse
    {
        $this->authorizeView($request);

        $disk = Storage::disk($engineeringDocument->disk());
        abort_unless($disk->exists($engineeringDocument->storage_path), 404, 'File no longer exists on storage.');

        // Derive the served Content-Type from the package type + stored extension —
        // NEVER from the sniffed mime_type. Otherwise a file whose bytes are HTML/SVG
        // but whose extension is .png (package_type "image") would be served as
        // `Content-Type: text/html` inline and execute as active content on the app
        // origin. Only a fixed set of inert types is served inline; anything else is
        // a forced download as application/octet-stream. nosniff blocks type sniffing.
        [$contentType, $inline] = $this->safeServeType($engineeringDocument);

        $disposition = HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $engineeringDocument->original_filename,
            preg_replace('/[^\x20-\x7e]/', '_', $engineeringDocument->original_filename) ?: 'download',
        );

        return $disk->download($engineeringDocument->storage_path, $engineeringDocument->original_filename, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => $disposition,
        ]);
    }

    /**
     * The safe (Content-Type, inline?) pair for serving a document. Inline is
     * allowed only for PDF and a raster-image allowlist, each with a fixed inert
     * type; every other document downloads as application/octet-stream.
     *
     * @return array{0: string, 1: bool}
     */
    private function safeServeType(EngineeringDocument $doc): array
    {
        if ($doc->package_type === EngineeringPackageType::Pdf) {
            return ['application/pdf', true];
        }

        $imageTypes = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp',
        ];
        $ext = strtolower(pathinfo((string) $doc->storage_path, PATHINFO_EXTENSION));

        if ($doc->package_type === EngineeringPackageType::Image && isset($imageTypes[$ext])) {
            return [$imageTypes[$ext], true];
        }

        return ['application/octet-stream', false];
    }

    /**
     * A short-lived SIGNED URL to the interactive package's entry point (#179
     * Phase 3). The frontend loads this inside a sandboxed <iframe>; the viewer
     * route validates the signature, serves the extracted files with a strict CSP
     * and never touches the app session.
     */
    public function viewerUrl(Request $request, EngineeringDocument $engineeringDocument): JsonResponse
    {
        $this->authorizeView($request);

        abort_unless(
            $engineeringDocument->package_type === EngineeringPackageType::InteractiveHtml && $engineeringDocument->entry_point,
            422,
            'This document has no interactive viewer.'
        );

        $url = URL::temporarySignedRoute(
            'engineering.viewer',
            now()->addSeconds((int) config('engineering.viewer_url_ttl', 300)),
            ['engineeringDocument' => $engineeringDocument->id, 'path' => $engineeringDocument->entry_point],
        );

        return response()->json(['data' => ['url' => $url, 'entry_point' => $engineeringDocument->entry_point]]);
    }

    public function release(Request $request, EngineeringDocument $engineeringDocument): JsonResponse
    {
        $this->authorizeManage($request);

        // A retired (obsolete) document is terminal — it cannot be resurrected to
        // Released. Only Draft -> Released (or a no-op re-release) is allowed.
        abort_if(
            $engineeringDocument->lifecycle_status === EngineeringDocumentLifecycle::Obsolete,
            422,
            'An obsolete document cannot be released again.'
        );

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

        // A document that was EVER released is kept for traceability (a released
        // doc is snapshotted onto work orders). Guarding on `released_at` — not just
        // the current status — closes the release -> obsolete -> delete bypass, since
        // an obsolete document is no longer "immutable" by status alone.
        abort_if(
            $engineeringDocument->isImmutable() || $engineeringDocument->released_at !== null,
            422,
            'A document that has been released cannot be deleted; obsolete it instead.'
        );

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
