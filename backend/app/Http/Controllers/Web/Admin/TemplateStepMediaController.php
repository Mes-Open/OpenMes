<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateStepMediaRequest;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStepMedia;
use App\Services\Media\ImageSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Rich work-instruction media (image / PDF / video) for process template steps.
 *
 * Security model mirrors ProcessTemplatePhotoController:
 *  - Upload/delete are admin-only + throttled; images are re-encoded by
 *    ImageSanitizer (polyglots/EXIF stripped). PDFs/videos are stored as-is
 *    after strict type validation and always served with X-Content-Type-Options:
 *    nosniff and an inline Content-Type, so the browser can't sniff them into
 *    something executable.
 *  - Files live on the PRIVATE disk under a server-generated random name;
 *    viewing goes through the authenticated streaming endpoint (Range-enabled
 *    so videos seek), never a public URL.
 *  - Every route is scoped to its template/product-type (mismatch = 404, IDOR).
 */
class TemplateStepMediaController extends Controller
{
    public function store(
        StoreTemplateStepMediaRequest $request,
        ProductType $productType,
        ProcessTemplate $processTemplate,
        ImageSanitizer $sanitizer,
    ) {
        $this->ensureBelongs($productType, $processTemplate);

        $stepId = $request->validated('template_step_id');
        if ($stepId) {
            abort_unless($processTemplate->steps()->whereKey($stepId)->exists(), 404);
        }

        $type = $request->validated('media_type');
        $file = $request->file('file');

        if ($type === TemplateStepMedia::TYPE_IMAGE) {
            try {
                $clean = $sanitizer->sanitize($file->getRealPath());
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['file' => $e->getMessage()]);
            }
            $path = $this->path($processTemplate, $clean['extension']);
            Storage::put($path, $clean['bytes']);
            $mime = $clean['mime'];
            $size = strlen($clean['bytes']);
        } else {
            // PDF / video: stored as-is (re-encoding is impractical). The strict
            // type rule + nosniff serving header are the defence.
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $path = $this->path($processTemplate, $ext);
            Storage::putFileAs(dirname($path), $file, basename($path));
            $mime = $file->getClientMimeType();
            $size = $file->getSize();
        }

        $processTemplate->stepMedia()->create([
            'template_step_id' => $stepId,
            'media_type' => $type,
            'title' => $request->validated('title'),
            'storage_path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $mime,
            'file_size' => $size,
            'sort_order' => ($processTemplate->stepMedia()->max('sort_order') ?? 0) + 1,
            'uploaded_by_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Media uploaded.');
    }

    public function destroy(
        ProductType $productType,
        ProcessTemplate $processTemplate,
        TemplateStepMedia $media,
    ) {
        $this->ensureBelongs($productType, $processTemplate, $media);

        $media->delete(); // model event removes the file from disk

        return back()->with('success', 'Media deleted.');
    }

    /**
     * Stream media to any authenticated user (operators need work instructions).
     * Uses a BinaryFileResponse so HTTP Range requests work - videos seek and
     * large PDFs load progressively.
     */
    public function show(
        ProcessTemplate $processTemplate,
        TemplateStepMedia $media,
    ) {
        abort_unless($media->process_template_id === $processTemplate->id, 404);
        // Deleted from the template: only for someone who can open an open order
        // that was released with it (it is still that order's instruction).
        abort_if($media->trashed() && ! $this->stillReleasedTo($media, request()->user()), 404);
        abort_unless(Storage::exists($media->storage_path), 404);

        return response()->file(Storage::path($media->storage_path), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Whether a non-terminal order the user may view carries this media in its
     * snapshot. Orders are read in chunks and the scan stops at the first match;
     * the answer is cached briefly, because a video or a large PDF is fetched in
     * many Range requests.
     */
    private function stillReleasedTo(TemplateStepMedia $media, ?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        return Cache::remember(
            "template-step-media:{$media->id}:released-to:{$user->id}",
            now()->addMinute(),
            fn () => \App\Models\WorkOrder::query()
                ->whereNotIn('status', \App\Models\WorkOrder::TERMINAL_STATUSES)
                ->where('process_snapshot->template_id', $media->process_template_id)
                ->lazyById(100)
                ->contains(fn (\App\Models\WorkOrder $order) => collect($order->process_snapshot['steps'] ?? [])
                    ->contains(fn (array $step) => in_array($media->id, array_map('intval', array_column($step['media'] ?? [], 'id')), true))
                    && $user->can('view', $order)),
        );
    }

    /** Server-generated private path; never the client filename. */
    private function path(ProcessTemplate $processTemplate, string $extension): string
    {
        return 'template-step-media/'.$processTemplate->id.'/'.Str::random(40).'.'.$extension;
    }

    private function ensureBelongs(
        ProductType $productType,
        ProcessTemplate $processTemplate,
        ?TemplateStepMedia $media = null,
    ): void {
        abort_unless($processTemplate->product_type_id === $productType->id, 404);

        if ($media) {
            abort_unless($media->process_template_id === $processTemplate->id, 404);
        }
    }
}
