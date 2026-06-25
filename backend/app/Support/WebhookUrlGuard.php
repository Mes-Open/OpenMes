<?php

namespace App\Support;

/**
 * SSRF guard for outgoing webhook URLs (#20, OWASP A10). A target URL is only
 * safe when it is a well-formed http(s) URL whose host resolves exclusively to
 * public addresses (no loopback, private, reserved or cloud-metadata IPs).
 *
 * Checked at save time (SafeWebhookUrl rule, fast feedback) and again at
 * delivery time (DeliverWebhookJob). At delivery the job pins the connection to
 * the vetted IP via `safeTarget()` (curl CURLOPT_RESOLVE), so there is no
 * DNS-rebinding/TOCTOU window between the final check and the actual connect.
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
        return self::inspect($url)['reason'];
    }

    /**
     * Validate and return the connection target to pin: the vetted public IP
     * plus the original host/port (so Host header + TLS SNI stay correct while
     * the socket connects to the IP we actually checked).
     *
     * @return array{ip: string, host: string, port: int}
     *
     * @throws \RuntimeException when the URL is unsafe
     */
    public static function safeTarget(string $url): array
    {
        $r = self::inspect($url);
        if ($r['reason'] !== null) {
            throw new \RuntimeException($r['reason']);
        }

        return ['ip' => $r['ips'][0], 'host' => $r['host'], 'port' => $r['port']];
    }

    /**
     * @return array{reason: ?string, ips: array<int, string>, host: ?string, port: int}
     */
    private static function inspect(string $url): array
    {
        $fail = fn (string $reason) => ['reason' => $reason, 'ips' => [], 'host' => null, 'port' => 0];

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return $fail('Invalid webhook URL.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return $fail('Webhook URL must use http or https.');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return $fail('Webhook URL has no host.');
        }

        $ips = self::resolve($host);
        if ($ips === []) {
            return $fail('Webhook URL host could not be resolved.');
        }

        // Reject if ANY resolved address is unsafe.
        foreach ($ips as $ip) {
            if (in_array($ip, self::METADATA_IPS, true)) {
                return $fail('Webhook URL resolves to a private/reserved address.');
            }

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                foreach ([FILTER_FLAG_NO_PRIV_RANGE, FILTER_FLAG_NO_RES_RANGE] as $flag) {
                    if (! filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
                        return $fail('Webhook URL resolves to a private/reserved address.');
                    }
                }
            }
        }

        $port = (int) (parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));

        return ['reason' => null, 'ips' => $ips, 'host' => $host, 'port' => $port];
    }

    /** @return array<int, string> resolved IPv4/IPv6 addresses */
    private static function resolve(string $host): array
    {
        // Obfuscated numeric hosts (decimal "2130706433", hex "0x7f000001") are
        // resolved to an IP by libc/curl but slip past the literal-IP fast path,
        // so canonicalize them first. Dotted but non-canonical forms (octal
        // octets like "0177.0.0.1") are refused outright.
        if (preg_match('/^(?:0x[0-9a-fA-F]+|\d+)$/', $host)) {
            $n = stripos($host, '0x') === 0 ? hexdec($host) : (int) $host;
            if ($n < 0 || $n > 4294967295) {
                return [];
            }
            $host = long2ip((int) $n);
        } elseif (preg_match('/^[0-9]+(?:\.[0-9]+)+$/', $host) && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }

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
