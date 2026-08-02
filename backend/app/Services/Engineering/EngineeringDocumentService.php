<?php

namespace App\Services\Engineering;

use App\Enums\EngineeringDocumentLifecycle;
use App\Models\EngineeringDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores engineering documents (#179) on the private disk and drives their
 * lifecycle. Phase 1 handles every package type as a single stored blob; the
 * interactive-HTML extraction + sandboxed viewer land in a later phase.
 */
class EngineeringDocumentService
{
    public function __construct(private EngineeringPackageService $packages) {}

    public function disk(): string
    {
        return config('engineering.disk', 'local');
    }

    /**
     * Store an uploaded engineering file and its metadata.
     *
     * @param  array<string, mixed>  $meta  Validated metadata (entity_type, entity_id, revision, …).
     */
    public function store(array $meta, UploadedFile $file, User $uploader): EngineeringDocument
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $allow = config('engineering.extensions', []);

        if (! isset($allow[$ext])) {
            throw ValidationException::withMessages([
                'file' => __('File type “:ext” is not an allowed engineering format.', ['ext' => $ext]),
            ]);
        }

        $packageType = $allow[$ext];
        $checksum = hash_file('sha256', $file->getRealPath());

        // Non-predictable, entity-scoped path on the private disk.
        $dir = "engineering/{$meta['entity_type']}/{$meta['entity_id']}";
        $name = Str::uuid()->toString().'.'.$ext;
        $path = "{$dir}/{$name}";

        Storage::disk($this->disk())->putFileAs($dir, $file, $name);

        $doc = EngineeringDocument::create([
            'entity_type' => $meta['entity_type'],
            'entity_id' => $meta['entity_id'],
            'original_filename' => $file->getClientOriginalName(),
            'package_type' => $packageType,
            'document_type' => $meta['document_type'] ?? null,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'entry_point' => $meta['entry_point'] ?? null,
            'revision' => $meta['revision'],
            'checksum' => $checksum,
            'storage_path' => $path,
            'lifecycle_status' => EngineeringDocumentLifecycle::Draft,
            'effective_from' => $meta['effective_from'] ?? null,
            'effective_to' => $meta['effective_to'] ?? null,
            'uploaded_by_id' => $uploader->id,
        ]);

        // Interactive HTML is active content: a zip is validated + extracted into
        // the document's isolated directory (served later behind the signed,
        // CSP-locked viewer); a single .html is its own entry point.
        if ($packageType === 'interactive_html') {
            try {
                $abs = Storage::disk($this->disk())->path($path);
                if ($ext === 'zip') {
                    $result = $this->packages->extract($doc, $abs, $meta['entry_point'] ?? null);
                    $doc->update(['extracted_size' => $result['extracted_size'], 'entry_point' => $result['entry_point']]);
                } else { // single-file .html
                    $doc->update(['entry_point' => $name]);
                }
            } catch (ValidationException $e) {
                // Reject the whole upload: force-delete removes the row, the stored
                // zip and any partially-extracted files, then re-surface the 422.
                $doc->forceDelete();
                throw $e;
            }
        }

        return $doc;
    }

    /** Mark a document released (immutable from here on). No-op if already released. */
    public function release(EngineeringDocument $doc, User $user): EngineeringDocument
    {
        if ($doc->isReleased()) {
            return $doc;
        }

        $doc->update([
            'lifecycle_status' => EngineeringDocumentLifecycle::Released,
            'released_at' => now(),
            'released_by_id' => $user->id,
        ]);

        return $doc;
    }

    /** Retire a document. Released documents can still be obsoleted (kept for history). */
    public function obsolete(EngineeringDocument $doc): EngineeringDocument
    {
        $doc->update(['lifecycle_status' => EngineeringDocumentLifecycle::Obsolete]);

        return $doc;
    }
}
