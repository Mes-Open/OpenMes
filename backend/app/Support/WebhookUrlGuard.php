<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * SSRF guard for outgoing webhook URLs (#20, OWASP A10). A target URL is only
 * safe when it is a well-formed http(s) URL whose host resolves exclusively to
 * addresses it may reach. Every resolved address is classified, in this order:
 *
 *  1. Always blocked — cloud metadata, loopback, unspecified, link-local,
 *     multicast/broadcast and other reserved ranges. Nothing unlocks these.
 *  2. Private pool (RFC 1918, IPv6 ULA) — blocked unless the address, or the
 *     URL's hostname, is on the administrator's WebhookNetworkAllowlist (T2a,
 *     an on-prem ERP in the same LAN). Empty allowlist = blocked, as before.
 *  3. Anything else PHP flags as private/reserved — blocked.
 *  4. Public — allowed.
 *
 * Checked at save time (SafeWebhookUrl rule, fast feedback) and again at
 * delivery time (DeliverWebhookJob) with the very same rule. At delivery the
 * job pins the connection to the vetted IP via `safeTarget()` (curl
 * CURLOPT_RESOLVE), so there is no DNS-rebinding/TOCTOU window between the
 * final check and the actual connect.
 */
class WebhookUrlGuard
{
    /** Cloud metadata endpoints blocked regardless of range classification. */
    private const METADATA_IPS = ['169.254.169.254', '169.254.170.2', '100.100.100.200'];

    /** Never reachable, even when an allowlist entry covers them. */
    public const ALWAYS_BLOCKED_CIDRS = [
        '0.0.0.0/8',        // "this network" / unspecified
        '127.0.0.0/8',      // loopback
        '169.254.0.0/16',   // link-local (cloud metadata lives here)
        '224.0.0.0/4',      // multicast
        '240.0.0.0/4',      // reserved, incl. 255.255.255.255 broadcast
        '::/128',           // unspecified
        '::1/128',          // loopback
        'fe80::/10',        // link-local
        'ff00::/8',         // multicast
        // AWS IMDS over IPv6. It sits inside the ULA pool (fc00::/7), so it
        // must be listed here or an fd00::/8 allowlist entry would unlock it.
        'fd00:ec2::254/128',
        // IPv6 forms that embed an IPv4 address (IPv4-compatible, SIIT,
        // NAT64, 6to4, Teredo) or are deprecated (site-local): a webhook has
        // no business there, and each could tunnel to a blocked IPv4 target.
        '::/96',
        '::ffff:0:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '2001::/32',
        '2002::/16',
        'fec0::/10',
    ];

    /** Private ranges an administrator may open up via the allowlist. */
    public const UNLOCKABLE_PRIVATE_CIDRS = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',         // IPv6 unique local addresses
    ];

    public const PRIVATE_ADDRESS_MESSAGE = 'Webhook URL resolves to a private/reserved address.';

    public const ALLOWLIST_HINT = 'An administrator can allow local network receivers on the Webhooks page.';

    /** @var (callable(string): array<int, string>)|null test seam for DNS */
    private static $resolver = null;

    /**
     * Replace DNS resolution of hostnames (tests only — literal IPs never hit
     * it). Pass null to restore the real resolver.
     */
    public static function resolveUsing(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /** Whether the address can never be a webhook target (metadata, loopback, …). */
    public static function isAlwaysBlocked(string $ip): bool
    {
        $ip = self::unmapIpv4($ip);

        return in_array($ip, self::METADATA_IPS, true)
            || IpUtils::checkIp($ip, self::ALWAYS_BLOCKED_CIDRS);
    }

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

        // Reject if ANY resolved address is unsafe. The allowlist is read once
        // per check — from the database, so a narrowed list applies at once.
        $allowlist = null;
        foreach ($ips as $ip) {
            $ip = self::unmapIpv4($ip);

            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                return $fail(self::PRIVATE_ADDRESS_MESSAGE);
            }

            if (self::isAlwaysBlocked($ip)) {
                return $fail(self::PRIVATE_ADDRESS_MESSAGE);
            }

            if (IpUtils::checkIp($ip, self::UNLOCKABLE_PRIVATE_CIDRS)) {
                $allowlist ??= WebhookNetworkAllowlist::entries();
                if (WebhookNetworkAllowlist::matchesIp($ip, $allowlist)
                    || WebhookNetworkAllowlist::matchesHost($host, $allowlist)) {
                    continue;
                }

                return $fail(self::PRIVATE_ADDRESS_MESSAGE.' '.self::ALLOWLIST_HINT);
            }

            foreach ([FILTER_FLAG_NO_PRIV_RANGE, FILTER_FLAG_NO_RES_RANGE] as $flag) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
                    return $fail(self::PRIVATE_ADDRESS_MESSAGE);
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

        // A literal IP is its own resolution (IPv6 literals arrive bracketed).
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP)) {
            return [$literal];
        }

        if (self::$resolver !== null) {
            return array_values(array_unique((self::$resolver)($host)));
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

    /** `::ffff:a.b.c.d` (IPv4-mapped IPv6) → `a.b.c.d`, so it is classified as IPv4. */
    private static function unmapIpv4(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16
            && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return $ip;
    }
}
