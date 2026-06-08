<?php

namespace App\Services\Media;

use InvalidArgumentException;

/**
 * Security gate for user-uploaded images.
 *
 * Every accepted image is fully DECODED and RE-ENCODED from raw pixels via GD.
 * This is the core defense: the stored file contains only pixel data produced
 * by this server, so anything smuggled inside the original is destroyed —
 * PHP/script payloads appended to a valid image (polyglot files), malicious
 * EXIF/ICC chunks, and embedded metadata (incl. GPS) never reach the disk.
 *
 * Only raster formats are accepted. SVG is rejected by design (XML — can
 * carry scripts), as is GIF (low value for work instructions, extra parser
 * surface). The output format always matches the validated input format.
 */
class ImageSanitizer
{
    /** Formats we re-encode. Keys are the GD image types. */
    private const SUPPORTED = [
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'extension' => 'jpg'],
        IMAGETYPE_PNG => ['mime' => 'image/png', 'extension' => 'png'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'extension' => 'webp'],
    ];

    public const MAX_DIMENSION = 8000; // px — decompression-bomb guard

    /**
     * Decode + re-encode an uploaded image file.
     *
     * @param  string  $sourcePath  absolute path of the uploaded temp file
     * @return array{bytes: string, mime: string, extension: string, width: int, height: int}
     *
     * @throws InvalidArgumentException when the file is not a clean raster image
     */
    public function sanitize(string $sourcePath): array
    {
        // Type detection from CONTENT (magic bytes), never from name/headers.
        $info = @getimagesize($sourcePath);
        if ($info === false || ! isset(self::SUPPORTED[$info[2]])) {
            throw new InvalidArgumentException('File is not a supported image (JPEG, PNG, WebP).');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Image has invalid dimensions.');
        }
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new InvalidArgumentException(
                'Image dimensions exceed the '.self::MAX_DIMENSION.'px limit.'
            );
        }

        $format = self::SUPPORTED[$info[2]];

        // Full decode — fails on truncated/corrupt files that spoof magic bytes.
        $image = @imagecreatefromstring((string) file_get_contents($sourcePath));
        if ($image === false) {
            throw new InvalidArgumentException('Image could not be decoded.');
        }

        // PNG/WebP may carry alpha — preserve it through the re-encode.
        imagesavealpha($image, true);

        ob_start();
        $encoded = match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($image, null, 90),
            IMAGETYPE_PNG => imagepng($image, null, 6),
            IMAGETYPE_WEBP => imagewebp($image, null, 90),
        };
        $bytes = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || $bytes === false || $bytes === '') {
            throw new InvalidArgumentException('Image could not be re-encoded.');
        }

        return [
            'bytes' => $bytes,
            'mime' => $format['mime'],
            'extension' => $format['extension'],
            'width' => $width,
            'height' => $height,
        ];
    }
}
