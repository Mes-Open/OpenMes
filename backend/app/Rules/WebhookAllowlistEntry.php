<?php

namespace App\Rules;

use App\Support\WebhookUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One entry of the webhook private-network allowlist (T2a): an IPv4/IPv6 CIDR,
 * a bare IP or an exact hostname. Refuses entries that would make the list
 * meaningless or dangerous — `/0` (everything), prefixes broader than the
 * private pools themselves, and ranges that start in always-blocked space
 * (loopback, link-local, cloud metadata). The guard would still refuse those
 * addresses, but storing them would mislead the administrator.
 */
class WebhookAllowlistEntry implements ValidationRule
{
    /** Broadest prefix accepted: the size of the largest private pool. */
    private const MIN_PREFIX_V4 = 8;

    private const MIN_PREFIX_V6 = 7;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail(__('Enter a valid IP, CIDR range or hostname.'));

            return;
        }

        $entry = strtolower(trim($value));

        if (str_contains($entry, '/')) {
            $this->validateCidr($entry, $fail);

            return;
        }

        if (filter_var($entry, FILTER_VALIDATE_IP)) {
            if (WebhookUrlGuard::isAlwaysBlocked($entry)) {
                $fail(__('Loopback, link-local and cloud-metadata addresses can never be allowed.'));
            }

            return;
        }

        $this->validateHost($entry, $fail);
    }

    private function validateCidr(string $entry, Closure $fail): void
    {
        $parts = explode('/', $entry);
        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            $fail(__('Enter a valid IP, CIDR range or hostname.'));

            return;
        }

        [$address, $prefix] = [$parts[0], (int) $parts[1]];

        $isV4 = (bool) filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $isV6 = ! $isV4 && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if (! $isV4 && ! $isV6) {
            $fail(__('Enter a valid IP, CIDR range or hostname.'));

            return;
        }

        $max = $isV4 ? 32 : 128;
        if ($prefix > $max) {
            $fail(__('Enter a valid IP, CIDR range or hostname.'));

            return;
        }

        if ($prefix === 0) {
            $fail(__('Allowing every address (/0) is not permitted.'));

            return;
        }

        if ($prefix < ($isV4 ? self::MIN_PREFIX_V4 : self::MIN_PREFIX_V6)) {
            $fail(__('The prefix is too broad.'));

            return;
        }

        if (WebhookUrlGuard::isAlwaysBlocked($address)) {
            $fail(__('Loopback, link-local and cloud-metadata addresses can never be allowed.'));
        }
    }

    private function validateHost(string $entry, Closure $fail): void
    {
        $host = rtrim($entry, '.');

        // A bare host name only: no scheme, port, path, wildcard or spaces, and
        // it must contain a letter (an all-digit "host" is an obfuscated IP).
        $valid = $host !== ''
            && strlen($host) <= 253
            && preg_match('/^[a-z0-9.-]+$/', $host)
            && preg_match('/[a-z]/', $host)
            && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);

        if (! $valid) {
            $fail(__('Enter a valid IP, CIDR range or hostname.'));

            return;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            $fail(__('Loopback, link-local and cloud-metadata addresses can never be allowed.'));
        }
    }
}
