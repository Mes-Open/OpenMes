<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $tenantIds = DB::table('lines')
            ->select('tenant_id')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            // Skip tenants that already have at least one site
            $siteExists = DB::table('sites')
                ->where(function ($q) use ($tenantId) {
                    if ($tenantId === null) {
                        $q->whereNull('tenant_id');
                    } else {
                        $q->where('tenant_id', $tenantId);
                    }
                })
                ->exists();

            if ($siteExists) {
                continue;
            }

            // First company for this tenant (companies has no tenant_id today,
            // so simply grab the first company globally as a sensible default).
            $companyId = DB::table('companies')->orderBy('id')->value('id');

            $siteSuffix = $tenantId === null
                ? '0000'
                : str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT);

            // Sites.code is globally unique — disambiguate per tenant.
            $siteCode = 'SITE-' . $siteSuffix;
            $i = 1;
            while (DB::table('sites')->where('code', $siteCode)->exists()) {
                $siteCode = 'SITE-' . $siteSuffix . '-' . $i++;
            }

            $now = now();

            $siteId = DB::table('sites')->insertGetId([
                'name'        => 'Default Site',
                'code'        => $siteCode,
                'company_id'  => $companyId,
                'description' => 'Auto-created during ISA-95 hierarchy migration.',
                'is_active'   => true,
                'tenant_id'   => $tenantId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $areaId = DB::table('areas')->insertGetId([
                'name'        => 'Default Area',
                'code'        => 'AREA-DEFAULT',
                'site_id'     => $siteId,
                'description' => 'Auto-created during ISA-95 hierarchy migration.',
                'is_active'   => true,
                'tenant_id'   => $tenantId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            DB::table('lines')
                ->where(function ($q) use ($tenantId) {
                    if ($tenantId === null) {
                        $q->whereNull('tenant_id');
                    } else {
                        $q->where('tenant_id', $tenantId);
                    }
                })
                ->whereNull('area_id')
                ->update(['area_id' => $areaId]);
        }
    }

    public function down(): void
    {
        // No-op: rolling back would orphan lines and remove user-visible
        // structure. The down() in the create-table migrations handles cleanup.
    }
};
