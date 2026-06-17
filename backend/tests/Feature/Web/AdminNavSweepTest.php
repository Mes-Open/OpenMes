<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\TabRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke-sweep every admin-panel subpage for each role. An admin must load every
 * page (200) — this catches 500s, missing routes and tab-mapping gaps. A role
 * without the tab is forbidden (the access matrix gates it), and gains access
 * once its tab is granted.
 */
class AdminNavSweepTest extends TestCase
{
    use RefreshDatabase;

    /** Every admin-nav leaf page, grouped by the tab it belongs to. */
    private const PAGES = [
        'dashboard' => ['/admin/dashboard'],
        'orders' => ['/admin/work-orders', '/admin/work-orders/create', '/admin/csv-import'],
        'production' => [
            '/admin/product-types', '/admin/product-types/create', '/admin/materials', '/admin/material-lots',
            '/admin/traceability', '/admin/lot-sequences', '/admin/process-segments', '/admin/lines',
            '/admin/line-statuses', '/admin/view-templates', '/admin/shifts', '/admin/issues',
            '/admin/companies', '/admin/anomaly-reasons', '/admin/scrap-reasons',
        ],
        'reports' => ['/admin/reports', '/admin/cost-reports', '/admin/scrap-reports'],
        'structure' => [
            '/admin/sites', '/admin/areas', '/admin/factories', '/admin/divisions',
            '/admin/workstation-types', '/admin/subassemblies',
        ],
        'hr' => [
            '/admin/workers', '/admin/worker-absences', '/admin/personnel-classes', '/admin/crews',
            '/admin/crew-break-windows', '/admin/skills', '/admin/wage-groups',
        ],
        'maintenance' => [
            '/admin/maintenance-events', '/admin/maintenance-schedules', '/admin/tools', '/admin/cost-sources',
            '/admin/production-anomalies', '/admin/inspection-plans', '/admin/oee',
        ],
        'connectivity' => ['/admin/connectivity', '/admin/machine-monitor'],
        'admin' => ['/admin/users', '/admin/logs/activity', '/admin/logs/system', '/admin/audit-logs', '/admin/trash', '/admin/custom-fields'],
        'modules' => ['/admin/modules'],
    ];

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('Admin');

        return $u;
    }

    public function test_admin_loads_every_admin_page(): void
    {
        $admin = $this->admin();
        $failures = [];

        foreach (self::PAGES as $pages) {
            foreach ($pages as $url) {
                $status = $this->actingAs($admin)->get($url)->getStatusCode();
                if ($status !== 200) {
                    $failures[] = "{$url} → {$status}";
                }
            }
        }

        $this->assertSame([], $failures, "Admin pages not returning 200:\n".implode("\n", $failures));
    }

    public function test_supervisor_is_forbidden_without_tabs_then_allowed_when_granted(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        // No tabs by default → every admin page is forbidden.
        foreach (self::PAGES as $pages) {
            $this->actingAs($supervisor)->get($pages[0])->assertForbidden();
        }

        // Grant the HR tab → only HR pages open up.
        Role::findByName('Supervisor', 'web')->givePermissionTo(TabRegistry::permission('hr'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($supervisor)->get('/admin/workers')->assertOk();
        $this->actingAs($supervisor)->get('/admin/crews')->assertOk();
        $this->actingAs($supervisor)->get('/admin/users')->assertForbidden(); // not granted
    }
}
