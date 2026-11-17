<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Administrator-managed allowlist of private networks that outgoing webhooks
 * may reach (T2a) — e.g. an on-prem ERP in the same LAN as OpenMES.
 *
 * Entries are CIDR ranges (`192.168.1.0/24`, `fd00::/64`), single IPs (stored
 * as `/32` / `/128`) or exact hostnames (`odoo.lan`). An empty list — the
 * default — keeps the strict behaviour: no private address is reachable.
 *
 * The list only ever unlocks WebhookUrlGuard's private pool. Loopback,
 * link-local, multicast and cloud-metadata addresses stay blocked whatever is
 * on it (the guard checks those first).
 *
 * Persisted system-wide in `system_settings.webhook_private_allowlist` (a JSON
 * array), mirroring PriorityBandRegistry. Read from the database on every call
 * on purpose: Octane workers are long-lived, a static cache would keep serving
 * a list an admin has already narrowed.
 */
class WebhookNetworkAllowlist
{
    public const SETTING_KEY = 'webhook_private_allowlist';

    /** Upper bound on stored entries (also enforced by the Form Request). */
    public const MAX_ENTRIES = 50;

    /** @return list<string> normalized entries, empty when unset/unreadable */
    public static function entries(): array
    {
        try {
            $row = DB::table('system_settings')->where('key', self::SETTING_KEY)->first();
        } catch (\Throwable) {
            return [];
        }

        if (! $row) {
            return [];
        }

        $values = json_decode((string) $row->value, true);
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, 'is_string'));
    }

    /**
     * Persist the list (normalized, de-duplicated). Callers validate entries
     * first (WebhookAllowlistEntry rule); this only canonicalizes.
     *
     * @param  array<int, string>  $entries
     * @return list<string> what was stored
     */
    public static function save(array $entries): array
    {
        $clean = [];
        foreach ($entries as $entry) {
            $normalized = self::normalize((string) $entry);
            if ($normalized !== '' && ! in_array($normalized, $clean, true)) {
                $clean[] = $normalized;
            }
        }

        $clean = array_slice($clean, 0, self::MAX_ENTRIES);

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode($clean),
                'description' => 'Private networks/hosts outgoing webhooks may reach (CIDR or hostname)',
                'updated_at' => now(),
            ],
        );

        return $clean;
    }

    /**
     * Canonical form: trimmed, lowercased, trailing dot stripped from hosts,
     * bare IPs widened to a single-address CIDR.
     */
    public static function normalize(string $entry): string
    {
        $entry = strtolower(trim($entry));

        if ($entry === '') {
            return '';
        }

        if (filter_var($entry, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $entry.'/32';
        }

        if (filter_var($entry, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $entry.'/128';
        }

        if (str_contains($entry, '/')) {
            return $entry;
        }

        return rtrim($entry, '.');
    }

    /** Whether the entry is an IP range (as opposed to a hostname). */
    public static function isCidr(string $entry): bool
    {
        return str_contains($entry, '/');
    }

    /** Whether the IP falls inside any allowlisted range. */
    public static function matchesIp(string $ip, ?array $entries = null): bool
    {
        $cidrs = array_values(array_filter($entries ?? self::entries(), [self::class, 'isCidr']));

        return $cidrs !== [] && IpUtils::checkIp($ip, $cidrs);
    }

    /** Whether the URL host is an allowlisted hostname (exact, case-insensitive). */
    public static function matchesHost(string $host, ?array $entries = null): bool
    {
        $host = rtrim(strtolower($host), '.');
        if ($host === '') {
            return false;
        }

        foreach ($entries ?? self::entries() as $entry) {
            if (! self::isCidr($entry) && $entry === $host) {
                return true;
            }
        }

        return false;
    }
}
