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

    /**
     * Extracted files for a document live under this isolated per-document prefix.
     * The id is a sequential key, not a secret — access is authorised by the
     * signed viewer URL, never by the path being unguessable.
     */
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
        $maxFileBytes = (int) config('engineering.max_extracted_file_bytes', 64 * 1024 * 1024);
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

            // Directory entry — nothing to write.
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }

            // Zip-slip: reject null bytes, absolute paths and any `..` path SEGMENT
            // (backslashes are normalised to `/` first, so `..\` is covered too).
            $normalized = str_replace('\\', '/', $name);
            if (
                str_contains($normalized, "\0")
                || str_starts_with($normalized, '/')
                || in_array('..', explode('/', $normalized), true)
            ) {
                $zip->close();
                $this->fail('The interactive package contains an unsafe path.');
            }

            $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
            if ($ext === '' || ! isset($allowed[$ext])) {
                $zip->close();
                $this->fail("The interactive package contains a disallowed file type “{$ext}”.");
            }

            // Stream the entry out, counting the REAL decompressed bytes (never the
            // archive's declared size, which an attacker controls) and aborting the
            // moment a per-file or cumulative cap is exceeded. A php://temp buffer
            // spills to disk past 2 MB, so a zip-bomb entry can't inflate into
            // memory — it is stopped after at most $maxFileBytes.
            $stream = $zip->getStreamIndex($i);
            if ($stream === false) {
                $zip->close();
                $this->fail('The interactive package could not be read.');
            }

            $buffer = fopen('php://temp/maxmemory:'.(2 * 1024 * 1024), 'r+');
            if ($buffer === false) {
                fclose($stream);
                $zip->close();
                $this->fail('The interactive package could not be read.');
            }
            $fileBytes = 0;
            while (! feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    // A read error must fail the whole extraction, never write a
                    // truncated asset and count it as successfully extracted.
                    fclose($stream);
                    fclose($buffer);
                    $zip->close();
                    $this->fail('The interactive package could not be read.');
                }
                $len = strlen($chunk);
                $fileBytes += $len;
                $total += $len;
                if ($fileBytes > $maxFileBytes || $total > $maxBytes) {
                    fclose($stream);
                    fclose($buffer);
                    $zip->close();
                    $this->fail('The interactive package is larger than the extraction limit.');
                }
                fwrite($buffer, $chunk);
            }
            fclose($stream);

            rewind($buffer);
            $written = $disk->writeStream("{$prefix}/{$normalized}", $buffer);
            fclose($buffer);
            if ($written === false) {
                $zip->close();
                $this->fail('The interactive package could not be stored.');
            }

            $names[] = $normalized;
            $count++;
        }

        $zip->close();

        if ($count === 0) {
            $this->fail('The interactive package is empty.');
        }

        // Resolve the entry point: a declared one (normalised the same way as the
        // entry names) must exist; otherwise fall back to index.html at the root,
        // then to the first HTML document. Never fall back to a non-HTML asset
        // (a .png/.js entry point would serve the wrong thing).
        // Strip only leading `./` sequences — NOT a `ltrim(..., './')` char set,
        // which would also eat a leading dot from names like `.config/index.html`.
        $entry = (string) preg_replace('#^(?:\./)+#', '', str_replace('\\', '/', (string) $declaredEntry)) ?: 'index.html';
        if (! in_array($entry, $names, true)) {
            $html = array_values(array_filter(
                $names,
                fn (string $n) => in_array(strtolower(pathinfo($n, PATHINFO_EXTENSION)), ['html', 'htm'], true),
            ));
            if ($html === []) {
                $this->fail('The interactive package has no HTML entry point.');
            }
            $entry = in_array('index.html', $html, true) ? 'index.html' : $html[0];
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
