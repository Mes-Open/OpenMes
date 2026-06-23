<?php

namespace App\Rules;

use App\Support\WebhookUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects webhook URLs that are malformed or resolve to loopback/private/
 * reserved/cloud-metadata addresses (SSRF guard, #20). Delivery re-checks at
 * send time, but failing fast at save time is better UX.
 */
class SafeWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $reason = WebhookUrlGuard::reason($value);
        if ($reason !== null) {
            $fail($reason);
        }
    }
}
