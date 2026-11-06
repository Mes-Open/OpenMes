<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the unit aliases ERPs commonly send, for the install (tenant NULL) and
 * for every existing tenant, so a tenant admin sees — and can edit — them on
 * Settings → Units.
 *
 * A literal copy of App\Support\Units\DefaultUnitAliases::MAP on purpose:
 * migrations must not depend on code that can change after they ran.
 * Idempotent — an alias a scope already has is left alone.
 */
return new class extends Migration
{
    private const MAP = [
        'pcs' => ['pcs', 'pc', 'piece', 'pieces', 'szt', 'sztuka', 'sztuk', 'units', 'unit', 'ea', 'each', 'st', 'stk', 'stück', 'stueck', 'C62', 'H87'],
        'kg' => ['kg', 'KGM', 'kilogram', 'kilograms'],
        'g' => ['g', 'GRM', 'gram', 'grams'],
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

    public function up(): void
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
                    $key = mb_strtolower($alias);

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

    public function down(): void
    {
        // The table itself is dropped by the previous migration's down().
    }
};
