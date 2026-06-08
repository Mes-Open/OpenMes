<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcessTemplatePhotoRequest;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplatePhoto;
use App\Models\ProductType;
use App\Services\Media\ImageSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reference photos for process templates (work instructions).
 *
 * Security model:
 *  - Upload/delete: admin-only routes (role:Admin) + throttle.
 *  - Every file is re-encoded from raw pixels (ImageSanitizer) — polyglot
 *    payloads, embedded scripts and EXIF never reach the disk.
 *  - Files live on the PRIVATE local disk under a server-generated random
 *    name; the client filename is metadata only. Nothing is web-reachable
 *    directly — viewing goes through the authenticated streaming endpoint.
 *  - All photo routes are scoped to the template/product-type in the URL
 *    (mismatch = 404), so IDs can't be swapped across entities (IDOR).
 */
class ProcessTemplatePhotoController extends Controller
{
    public function store(
        StoreProcessTemplatePhotoRequest $request,
        ProductType $productType,
        ProcessTemplate $processTemplate,
        ImageSanitizer $sanitizer,
    ) {
        $this->ensureBelongs($productType, $processTemplate);

        // A photo may target one specific step. Verify the step belongs to this
        // template (anti-IDOR) before persisting the link.
        $stepId = $request->validated('template_step_id');
        if ($stepId) {
            abort_unless(
                $processTemplate->steps()->whereKey($stepId)->exists(),
                404,
            );
        } elseif ($processTemplate->photos()->whereNull('template_step_id')->count() >= StoreProcessTemplatePhotoRequest::MAX_PHOTOS_PER_TEMPLATE) {
            // The per-template cap applies only to general (non-step) photos.
            return back()->withErrors([
                'photo' => 'Photo limit reached ('.StoreProcessTemplatePhotoRequest::MAX_PHOTOS_PER_TEMPLATE.' per template).',
            ]);
        }

        try {
            $clean = $sanitizer->sanitize($request->file('photo')->getRealPath());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }

        // Server-generated random name on the private disk — never the
        // client filename, never a client-supplied extension.
        $path = 'process-template-photos/'.$processTemplate->id.'/'.Str::random(40).'.'.$clean['extension'];
        Storage::put($path, $clean['bytes']);

        DB::transaction(function () use ($processTemplate, $stepId, $path, $clean, $request) {
            // One photo per step: a new step photo replaces the existing one
            // (its disk file is removed by the model's deleted event).
            if ($stepId) {
                $processTemplate->photos()->where('template_step_id', $stepId)->get()
                    ->each(fn (ProcessTemplatePhoto $p) => $p->delete());
            }

            $processTemplate->photos()->create([
                'template_step_id' => $stepId,
                'original_name' => Str::limit($request->file('photo')->getClientOriginalName(), 255, ''),
                'storage_path' => $path,
                'mime_type' => $clean['mime'],
                'file_size' => strlen($clean['bytes']),
                'width' => $clean['width'],
                'height' => $clean['height'],
                'caption' => $request->validated('caption'),
                'sort_order' => ($processTemplate->photos()->max('sort_order') ?? 0) + 1,
                'uploaded_by_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Photo uploaded.');
    }

    public function destroy(
        ProductType $productType,
        ProcessTemplate $processTemplate,
        ProcessTemplatePhoto $photo,
    ) {
        $this->ensureBelongs($productType, $processTemplate, $photo);

        $photo->delete(); // model event removes the file from disk

        return back()->with('success', 'Photo deleted.');
    }

    /**
     * Stream a photo to any authenticated user (operators need work
     * instructions too — route lives outside the admin group). Never
     * a public URL.
     */
    public function show(
        ProcessTemplate $processTemplate,
        ProcessTemplatePhoto $photo,
    ) {
        abort_unless($photo->process_template_id === $processTemplate->id, 404);

        abort_unless(Storage::exists($photo->storage_path), 404);

        // mime_type comes from our own re-encode — known-safe raster type.
        return Storage::response($photo->storage_path, null, [
            'Content-Type' => $photo->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * URL-scope guard: every segment must belong to its parent, or 404.
     */
    private function ensureBelongs(
        ProductType $productType,
        ProcessTemplate $processTemplate,
        ?ProcessTemplatePhoto $photo = null,
    ): void {
        abort_unless($processTemplate->product_type_id === $productType->id, 404);

        if ($photo) {
            abort_unless($photo->process_template_id === $processTemplate->id, 404);
        }
    }
}
