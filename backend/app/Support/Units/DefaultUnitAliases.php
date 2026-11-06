<?php

namespace App\Support\Units;

use Illuminate\Support\Facades\DB;

/**
 * The unit aliases every install starts with: the names ERPs commonly send for
 * the units OpenMES uses, including the UN/ECE Recommendation 20 codes (C62,
 * KGM, LTR, …) that EDI-minded ERPs emit.
 *
 * The data migration that first seeded them keeps its own literal copy —
 * migrations must not depend on code that can change after they ran. This is
 * the copy UnitAliasSeeder (fresh installs, demo data) uses.
 */
final class DefaultUnitAliases
{
    /** canonical unit => aliases (the canonical name itself included) */
    public const MAP = [
        'pcs' => ['pcs', 'pc', 'piece', 'pieces', 'szt', 'sztuka', 'sztuk', 'units', 'unit', 'ea', 'each', 'st', 'stk', 'stück', 'stueck', 'C62', 'H87'],
        'kg' => ['kg', 'KGM', 'kilogram', 'kilograms'],
        'g' => ['g', 'GRM', 'gram', 'grams'],
        // Not "ton": a US short ton is 907 kg, so it is no safe alias for t.
        't' => ['t', 'TNE', 'tonne'],
        'l' => ['l', 'LTR', 'litre', 'liter', 'litr'],
        'ml' => ['ml', 'MLT'],
        'm' => ['m', 'MTR', 'metre', 'meter', 'mb'],
        'mm' => ['mm', 'MMT'],
        'cm' => ['cm', 'CMT'],
        'm2' => ['m2', 'm²', 'MTK'],
        'm3' => ['m3', 'm³', 'MTQ'],
        'h' => ['h', 'HUR', 'hour'],
        'min' => ['min'],
    ];

    /**
     * Insert the defaults for the install (tenant NULL) and for every tenant,
     * skipping any alias a scope already has — safe to run again.
     */
    public static function seed(): void
    {
        $scopes = [null, ...DB::table('tenants')->pluck('id')->all()];
        $now = now();

        foreach ($scopes as $tenantId) {
            $taken = DB::table('unit_aliases')
                ->whereNull('deleted_at')
                ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
                ->pluck('alias_normalized')
                ->flip()
                ->all();

            $rows = [];

            foreach (self::MAP as $unit => $aliases) {
                foreach ($aliases as $alias) {
                    $key = UnitNormalizer::key($alias);

                    if (isset($taken[$key])) {
                        continue;
                    }

                    $taken[$key] = true;
                    $rows[] = [
                        'alias' => $alias,
                        'alias_normalized' => $key,
                        'unit' => $unit,
                        'tenant_id' => $tenantId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('unit_aliases')->insert($rows);
            }
        }
    }
}
