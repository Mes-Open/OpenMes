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

        // For the types we ever render inline (image / pdf), the real bytes must
        // match the claimed extension. Blocks uploading e.g. an HTML/SVG-with-script
        // payload named `drawing.png` that would otherwise be sniffed as active
        // content (defence in depth — the download route also fixes the served type).
        $this->assertContentMatchesType($packageType, $file);

        // Reject a duplicate up front with a clear 422 rather than letting the
        // partial-unique index (entity_type, entity_id, revision, package_type
        // WHERE deleted_at IS NULL) throw a QueryException AFTER the blob is
        // written — which would 500 and orphan the file on disk.
        if (EngineeringDocument::query()
            ->where('entity_type', $meta['entity_type'])
            ->where('entity_id', $meta['entity_id'])
            ->where('revision', $meta['revision'])
            ->where('package_type', $packageType)
            ->exists()) {
            throw ValidationException::withMessages([
                'file' => __('A :type package already exists for this entity and revision.', ['type' => $packageType]),
            ]);
        }

        $checksum = hash_file('sha256', $file->getRealPath());

        // Non-predictable, entity-scoped path on the private disk.
        $dir = "engineering/{$meta['entity_type']}/{$meta['entity_id']}";
        $name = Str::uuid()->toString().'.'.$ext;
        $path = "{$dir}/{$name}";

        Storage::disk($this->disk())->putFileAs($dir, $file, $name);

        try {
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
        } catch (\Throwable $e) {
            // The row never persisted (e.g. a concurrent duplicate slipping past the
            // pre-check) — remove the just-written blob so it isn't orphaned.
            Storage::disk($this->disk())->delete($path);
            throw $e;
        }

        // Interactive HTML is active content: a zip is validated + extracted into
        // the document's isolated directory (served later behind the signed,
        // CSP-locked viewer); a single .html is copied into that same directory as
        // its entry point (the viewer only ever serves from the isolated dir).
        if ($packageType === 'interactive_html') {
            try {
                $disk = Storage::disk($this->disk());
                $abs = $disk->path($path);
                if ($ext === 'zip') {
                    $result = $this->packages->extract($doc, $abs, $meta['entry_point'] ?? null);
                    $doc->update(['extracted_size' => $result['extracted_size'], 'entry_point' => $result['entry_point']]);
                } else { // single-file .html
                    $disk->put($this->packages->extractDir($doc).'/index.html', $disk->get($path));
                    $doc->update(['entry_point' => 'index.html', 'extracted_size' => $file->getSize()]);
                }
            } catch (\Throwable $e) {
                // Reject the whole upload on ANY failure (validation, a null-byte
                // entry name, a disk/OOM error, …): force-delete removes the row, the
                // stored zip and any partially-extracted files, then re-surface the
                // error — never leave an orphaned row or half-extracted package.
                $doc->forceDelete();
                throw $e;
            }
        }

        return $doc;
    }

    /**
     * Reject an upload whose real content type contradicts the claimed extension,
     * for the package types served inline (image / pdf). CAD/eDrawings/zip types
     * have unreliable sniffed MIME and are download-only, so they're not checked.
     */
    private function assertContentMatchesType(string $packageType, UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();

        $ok = match ($packageType) {
            'image' => str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml',
            'pdf' => $mime === 'application/pdf',
            default => true,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'file' => __('The file content does not match its :type extension.', ['type' => $packageType]),
            ]);
        }
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
