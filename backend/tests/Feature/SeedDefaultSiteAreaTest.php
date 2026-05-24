<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Smoke test for the data-shape produced by the ISA-95 hierarchy seed migration.
 *
 * The migration itself runs as part of RefreshDatabase. Here we re-run its core
 * logic against freshly-created lines to verify it provisions a Site + Area
 * per tenant and links orphaned lines.
 */
class SeedDefaultSiteAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_logic_creates_default_site_and_area_per_tenant_and_attaches_orphan_lines(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        // Simulate "before migration" state: lines without an area, no sites yet for this tenant.
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        // Create an orphaned line directly (bypassing factory area linkage)
        DB::table('lines')->insert([
            'code'      => 'LEGACY-1',
            'name'      => 'Legacy Line 1',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $lineId = DB::table('lines')->where('code', 'LEGACY-1')->value('id');

        // Sanity precondition
        $this->assertNotNull($lineId, 'Test fixture line was not inserted.');
        $this->assertNull(
            DB::table('lines')->where('id', $lineId)->value('area_id'),
            'Test fixture should be orphaned before re-running seed.'
        );

        // Wipe any auto-created defaults that came from RefreshDatabase seed migration
        // run for empty test DB (none should exist since lines table was empty then).
        DB::table('areas')->where('tenant_id', $tenant->id)->delete();
        DB::table('sites')->where('tenant_id', $tenant->id)->delete();

        // Re-run the seed migration's logic directly
        $migration = include database_path('migrations/2026_05_22_210300_seed_default_site_area_for_existing_lines.php');
        $migration->up();

        // Assertions
        $site = DB::table('sites')->where('tenant_id', $tenant->id)->first();
        $area = DB::table('areas')->where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($site, 'Default Site should be created for tenant.');
        $this->assertNotNull($area, 'Default Area should be created for tenant.');
        $this->assertSame($site->id, $area->site_id);
        $this->assertSame('Default Site', $site->name);
        $this->assertSame('Default Area', $area->name);
        $this->assertSame('AREA-DEFAULT', $area->code);

        // The orphan line should now be linked to the area
        $linkedAreaId = DB::table('lines')->where('id', $lineId)->value('area_id');
        $this->assertSame($area->id, $linkedAreaId);
    }

    public function test_seed_is_idempotent_for_tenant_with_existing_site(): void
    {
        $tenant = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

        // Pre-existing site for tenant — seed must NOT create a duplicate.
        DB::table('sites')->insert([
            'name'       => 'Existing Site',
            'code'       => 'SITE-EXIST',
            'is_active'  => true,
            'tenant_id'  => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add an orphan line
        DB::table('lines')->insert([
            'code'      => 'L1',
            'name'      => 'L1',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = include database_path('migrations/2026_05_22_210300_seed_default_site_area_for_existing_lines.php');
        $migration->up();

        // Only the pre-existing site should remain — no Default Site was injected.
        $this->assertSame(1, DB::table('sites')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(
            'Existing Site',
            DB::table('sites')->where('tenant_id', $tenant->id)->value('name')
        );
    }
}
