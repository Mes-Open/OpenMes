<?php

namespace App\Support;

/**
 * A number turned into a file name for a download: order numbers such as
 * "WO/2026/0001" or lots with spaces are fine as data, but a slash or a
 * backslash in Content-Disposition is refused outright (and would be a
 * path in the saved file). Everything outside letters, digits, dot, dash and
 * underscore becomes an underscore.
 */
final class DownloadName
{
    public static function safe(?string $value, string $fallback = 'file'): string
    {
        $name = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $value), '_');

        return $name !== '' ? $name : $fallback;
    }
}
