<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The CORS allowlist from Settings → System: empty (no cross-origin access),
 * `*`, or a comma-separated list of exact origins (`https://host[:port]`).
 *
 * The middleware matches origins as exact strings, so anything that is not an
 * origin either never matches (a path, a trailing slash) or matches far too
 * much: `null` is the Origin of sandboxed iframes and local files, which any
 * site can produce, so allowing it would hand credentialed access to everyone.
 */
class CorsOrigins implements ValidationRule
{
    private const ORIGIN = '~^https?://(\[[0-9a-fA-F:.]+\]|[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?)(:\d{1,5})?$~';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value)) {
            $fail(__('Enter * or a comma-separated list of origins such as https://erp.example.com.'));

            return;
        }

        $value = trim($value);
        if ($value === '' || $value === '*') {
            return;
        }

        foreach (explode(',', $value) as $origin) {
            $origin = trim($origin);
            if ($origin === '') {
                continue;
            }
            if (! preg_match(self::ORIGIN, $origin)) {
                $fail(__('":origin" is not an origin. Use scheme and host only, such as https://erp.example.com, or * alone.', ['origin' => $origin]));

                return;
            }
        }
    }
}
