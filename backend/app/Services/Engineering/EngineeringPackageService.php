<?php

namespace App\Services\Engineering;

use App\Models\EngineeringDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Validates and extracts interactive-HTML packages (#179 Phase 3). A zip is
 * treated as ACTIVE content: it is strictly validated (zip-slip, entry count,
 * extracted size, inner-extension allowlist) and unpacked into an isolated
 * per-document directory on the private disk. The viewer then serves those files
 * behind a signed, CSP-locked route — never inserted into the app DOM.
 */
class EngineeringPackageService
{
    public function disk(): string
    {
        return config('engineering.disk', 'local');
    }

    /** Extracted files for a document live under this isolated, non-guessable prefix. */
    public function extractDir(EngineeringDocument $doc): string
    {
        return "engineering/interactive/{$doc->id}";
    }

    /**
     * Validate a zip package and extract it to the document's isolated directory.
     *
     * @return array{extracted_size:int, file_count:int, entry_point:?string}
     *
     * @throws ValidationException on any archive-safety violation.
     */
    public function extract(EngineeringDocument $doc, string $absZipPath, ?string $declaredEntry = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($absZipPath) !== true) {
            $this->fail('The interactive package is not a valid archive.');
        }

        $maxFiles = (int) config('engineering.max_files', 2000);
        $maxBytes = (int) config('engineering.max_extracted_bytes', 500 * 1024 * 1024);
        $allowed = array_flip((array) config('engineering.inner_extensions', []));

        if ($zip->numFiles > $maxFiles) {
            $zip->close();
            $this->fail('The interactive package contains too many files.');
        }

        $disk = Storage::disk($this->disk());
        $prefix = $this->extractDir($doc);
        $total = 0;
        $count = 0;
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $stat = $zip->statIndex($i);

            // Directory entry — nothing to write.
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }

            // Zip-slip / absolute path / traversal.
            $normalized = str_replace('\\', '/', $name);
            if (str_starts_with($normalized, '/') || str_contains($normalized, '../') || str_contains($normalized, '..\\')) {
                $zip->close();
                $this->fail('The interactive package contains an unsafe path.');
            }

            $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
            if ($ext === '' || ! isset($allowed[$ext])) {
                $zip->close();
                $this->fail("The interactive package contains a disallowed file type “{$ext}”.");
            }

            $total += (int) ($stat['size'] ?? 0);
            if ($total > $maxBytes) {
                $zip->close();
                $this->fail('The interactive package is larger than the extraction limit.');
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                $zip->close();
                $this->fail('The interactive package could not be read.');
            }

            $disk->put("{$prefix}/{$normalized}", $content);
            $names[] = $normalized;
            $count++;
        }

        $zip->close();

        if ($count === 0) {
            $this->fail('The interactive package is empty.');
        }

        // Resolve the entry point: the declared one must exist; otherwise fall
        // back to an index.html at the package root.
        $entry = $declaredEntry ?: 'index.html';
        if (! in_array($entry, $names, true)) {
            $entry = in_array('index.html', $names, true) ? 'index.html' : $names[0];
        }

        return ['extracted_size' => $total, 'file_count' => $count, 'entry_point' => $entry];
    }

    /** Remove the extracted directory (called when the document is force-deleted). */
    public function deleteExtracted(EngineeringDocument $doc): void
    {
        Storage::disk($this->disk())->deleteDirectory($this->extractDir($doc));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => __($message)]);
    }
}
