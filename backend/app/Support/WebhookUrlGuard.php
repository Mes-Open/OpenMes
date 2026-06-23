<?php

namespace App\Support;

/**
 * SSRF guard for outgoing webhook URLs (#20, OWASP A10). A target URL is only
 * safe when it is a well-formed http(s) URL whose host does not resolve to a
 * loopback, private, reserved or cloud-metadata address.
 *
 * Checked both at save time (SafeWebhookUrl rule, fast feedback) and again at
 * delivery time (DeliverWebhookJob), since DNS can change between the two.
 */
class WebhookUrlGuard
{
    /** Cloud metadata endpoints blocked regardless of range classification. */
    private const METADATA_IPS = ['169.254.169.254', '169.254.170.2', '100.100.100.200'];

    public static function isSafe(string $url): bool
    {
        return self::reason($url) === null;
    }

    /** @throws \RuntimeException when the URL is unsafe */
    public static function assert(string $url): void
    {
        $reason = self::reason($url);
        if ($reason !== null) {
            throw new \RuntimeException($reason);
        }
    }

    /** Returns null when safe, otherwise a human-readable reason. */
    public static function reason(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Invalid webhook URL.';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'Webhook URL must use http or https.';
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return 'Webhook URL has no host.';
        }

        // Resolve every A record; reject if ANY of them is unsafe.
        $ips = self::resolve($host);
        if ($ips === []) {
            return 'Webhook URL host could not be resolved.';
        }

        foreach ($ips as $ip) {
            if (in_array($ip, self::METADATA_IPS, true)) {
                return 'Webhook URL resolves to a private/reserved address.';
            }

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                foreach ([FILTER_FLAG_NO_PRIV_RANGE, FILTER_FLAG_NO_RES_RANGE] as $flag) {
                    if (! filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
                        return 'Webhook URL resolves to a private/reserved address.';
                    }
                }
            }
        }

        return null;
    }

    /** @return array<int, string> resolved IPv4/IPv6 addresses */
    private static function resolve(string $host): array
    {
        // A literal IP is its own resolution.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (! empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
