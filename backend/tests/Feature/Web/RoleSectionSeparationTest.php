<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admins and supervisors have separate route trees. A supervisor works entirely
 * under `/supervisor`; `/admin` is the admin's, gated by the per-tab access
 * matrix. Screens both roles need are one controller mounted under both
 * prefixes (App\Http\Controllers\Concerns\ServesBothSections).
 */
class RoleSectionSeparationTest extends TestCase
{
    use RefreshDatabase;

    /** Every supervisor screen, by route name. */
    private const SUPERVISOR_ROUTES = [
        'supervisor.dashboard',
        'supervisor.shift-monitor.index',
        'supervisor.work-orders.index',
        'supervisor.customers.index',
        'supervisor.customers.create',
        'supervisor.priority-rules.index',
        'supervisor.csv-import',
        'supervisor.issues.index',
        'supervisor.quality-tasks.index',
        'supervisor.shift-handover.index',
        'supervisor.reports',
    ];

    /**
     * The /admin twins a supervisor used to reach and must not any more.
     * AdminNavSweepTest already sweeps dashboard/work-orders/csv-import/reports
     * for both roles; these are the ones only this change introduced.
     */
    private const ADMIN_TWINS = [
        '/admin/shift-monitor',
        '/admin/customers',
        '/admin/priority-rules',
    ];

    private function supervisor(): User
    {
        // Seeded, not hand-rolled: the behaviour under test is which tabs the
        // *seeder* grants Supervisor — exactly one, `orders`. A role created bare
        // here would hold no permissions either way, so every "forbidden"
        // assertion below would pass without proving anything.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        return $user;
    }

    public function test_the_seeder_grants_a_supervisor_only_the_orders_tab(): void
    {
        $tabs = $this->supervisor()->getAllPermissions()
            ->pluck('name')
            ->filter(fn (string $name) => str_starts_with($name, 'tab:'))
            ->values()
            ->all();

        // One exception to "supervisors work under /supervisor": production stops
        // and change requests (#182) are raised and reviewed on the /admin
        // work-order pages, and a supervisor who cannot open those cannot record
        // why a line stopped. `orders` covers exactly those screens — the
        // customer list, priority rules and CSV importer moved to `order_data`,
        // which is asserted forbidden by ADMIN_TWINS below.
        $this->assertSame(['tab:orders'], $tabs, 'Supervisors get the order screens and nothing else in /admin.');
    }

    public function test_a_supervisor_can_reach_every_screen_in_their_own_section(): void
    {
        $supervisor = $this->supervisor();

        $failures = [];

        foreach (self::SUPERVISOR_ROUTES as $name) {
            // No 403, no 404 — the tree is complete and reachable.
            $status = $this->actingAs($supervisor)->get(route($name))->getStatusCode();
            if ($status >= 400) {
                $failures[] = "{$name} → {$status}";
            }
        }

        $this->assertSame([], $failures, "Supervisor screens not reachable:\n".implode("\n", $failures));
    }

    public function test_a_supervisor_is_refused_the_admin_section(): void
    {
        $supervisor = $this->supervisor();

        $failures = [];

        // None of these sit on `orders` — the one tab the seeder grants — so
        // TabAccessMiddleware refuses each. (Customers and priority rules moved
        // to `order_data` precisely so the change-control grant does not carry
        // them.) A plant that wants an exception grants the tab.
        foreach (self::ADMIN_TWINS as $path) {
            $status = $this->actingAs($supervisor)->get($path)->getStatusCode();
            if ($status !== 403) {
                $failures[] = "{$path} → {$status}";
            }
        }

        $this->assertSame([], $failures, "Admin pages a supervisor could still reach:\n".implode("\n", $failures));
    }

    public function test_an_admin_still_reaches_the_admin_section(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $failures = [];
        foreach (self::ADMIN_TWINS as $path) {
            $status = $this->actingAs($admin)->get($path)->getStatusCode();
            if ($status !== 200) {
                $failures[] = "{$path} → {$status}";
            }
        }

        $this->assertSame([], $failures, "Admin pages not returning 200:\n".implode("\n", $failures));
    }

    public function test_a_shared_screen_keeps_its_links_inside_the_section_that_served_it(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        // Same controller, same React page — only the prefix differs, and the
        // page builds every URL from this prop.
        $this->actingAs($this->supervisor())
            ->get(route('supervisor.customers.index'))
            ->assertInertia(fn ($page) => $page->where('basePath', '/supervisor/customers'));

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertInertia(fn ($page) => $page->where('basePath', '/admin/customers'));
    }

    public function test_a_supervisor_screen_is_gone_when_its_module_is_off(): void
    {
        // The /admin twins get this from tab.access. Without the same gate on
        // /supervisor, switching a module off would hide it from admins and
        // leave it fully live for every supervisor.
        \App\Support\ModuleRegistry::save(
            array_values(array_diff(\App\Support\ModuleRegistry::optionalKeys(), ['reports'])),
        );

        $this->actingAs($this->supervisor())
            ->get(route('supervisor.reports'))
            ->assertNotFound();
    }

    public function test_a_supervisor_create_lands_back_in_the_supervisor_section(): void
    {
        // A redirect built from a hardcoded admin.* route name would bounce a
        // supervisor into a section they cannot enter.
        $this->actingAs($this->supervisor())
            ->post(route('supervisor.customers.store'), [
                'name' => 'Acme Tooling',
                'code' => 'ACME',
                'tier' => 'bronze',
            ])
            ->assertRedirect(route('supervisor.customers.index'));

        $this->assertDatabaseHas('customers', ['code' => 'ACME']);
    }
}
