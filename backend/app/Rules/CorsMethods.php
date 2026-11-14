<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The HTTP methods a CORS preflight may allow: a comma-separated list of
 * standard method names. The value goes out verbatim as a response header.
 */
class CorsMethods implements ValidationRule
{
    public const METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        $methods = is_string($value) ? array_filter(array_map('trim', explode(',', $value)), 'strlen') : [];

        if ($methods === [] || array_diff(array_map('strtoupper', $methods), self::METHODS) !== []) {
            $fail(__('Allowed methods must be a comma-separated list of: :methods.', ['methods' => implode(', ', self::METHODS)]));
        }
    }
}
