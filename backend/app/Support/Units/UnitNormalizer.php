<?php

namespace App\Support\Units;

use App\Models\UnitAlias;
use App\Scopes\TenantScope;
use App\Support\TenantContext;

/**
 * Turns the unit names an ERP sends into the units OpenMES stores, using the
 * tenant's unit aliases (Settings → Units of Measure).
 *
 * Built once per import call — one query, then an in-memory map — so a
 * 5000-row sync costs one lookup, not one per row.
 *
 * Resolution, in order:
 *  1. the alias dictionary — the tenant's own aliases (or, without a tenant,
 *     the install-wide ones stored with tenant NULL);
 *  2. a canonical unit spelled differently ("KG" → "kg") — known;
 *  3. anything else is unknown: the trimmed original comes back, flagged.
 *
 * One hop only — an alias never resolves through another alias.
 */
final class UnitNormalizer
{
    /**
     * @param  array<string, string>  $aliases  normalized alias => canonical unit
     * @param  array<string, string>  $canonical  normalized unit => canonical unit
     */
    public function __construct(
        private array $aliases = [],
        private array $canonical = [],
    ) {}

    /**
     * The lookup key for a unit name: trimmed, whitespace collapsed,
     * lower-cased, trailing dots dropped — so "Szt.", " szt " and "SZT" match.
     */
    public static function key(?string $raw): string
    {
        $key = preg_replace('/\s+/u', ' ', trim((string) $raw)) ?? '';

        return trim(rtrim(mb_strtolower($key), '.'));
    }

    /**
     * The dictionary for whoever is importing: the logged-in user's tenant, or
     * the API key's tenant for headless ERP calls — the same resolution
     * TenantScope uses.
     *
     * A tenant reads its own aliases and nothing else: every tenant gets its
     * own copy of the defaults when it is created, so an alias its admin
     * deleted must stop resolving rather than fall back to the install-wide
     * row. The install-wide rows (tenant NULL) serve only installs and users
     * without a tenant. Rows of other tenants are never read.
     */
    public static function forCurrentTenant(): self
    {
        $tenantId = auth()->hasUser()
            ? auth()->user()?->tenant_id
            : app(TenantContext::class)->id();

        $rows = UnitAlias::withoutGlobalScope(TenantScope::class)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->get(['alias_normalized', 'unit']);

        $aliases = [];
        $canonical = [];

        foreach ($rows as $row) {
            $aliases[$row->alias_normalized] = $row->unit;
            $canonical[self::key($row->unit)] = $row->unit;
        }

        return new self($aliases, $canonical);
    }

    /**
     * @return array{unit: string|null, known: bool} `unit` is null only for an
     *                                               empty input, which counts as known
     */
    public function resolve(?string $raw): array
    {
        $key = self::key($raw);

        if ($key === '') {
            return ['unit' => null, 'known' => true];
        }

        if (isset($this->aliases[$key])) {
            return ['unit' => $this->aliases[$key], 'known' => true];
        }

        if (isset($this->canonical[$key])) {
            return ['unit' => $this->canonical[$key], 'known' => true];
        }

        return ['unit' => trim((string) $raw), 'known' => false];
    }

    /**
     * Whether two unit names mean the same unit once both are resolved —
     * "szt." and "pcs" do; "g" and "kg" do not.
     */
    public function same(?string $a, ?string $b): bool
    {
        return self::key($this->resolve($a)['unit']) === self::key($this->resolve($b)['unit']);
    }
}
