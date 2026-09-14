<?php

namespace Tests\Feature\Extension;

use App\Support\SoftDeleteRegistry;
use App\Support\TabRegistry;
use App\Sync\ShapeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresNoModules;
use Tests\TestCase;

/**
 * The application with no modules installed.
 *
 * This is the other half of a module's installation test, and the half that is
 * easy to forget: proving the host is whole on its own. Every extension point
 * has to be empty here — no leftover route, no tab prefix pointing at a screen
 * that does not exist, no synced collection whose table was never created.
 *
 * It also guards the direction that actually breaks people. Extracting a
 * feature is the moment a stale reference gets left behind, and a stale
 * reference does not announce itself: a tab prefix for a missing page 403s, a
 * synced collection for a missing table 500s on the first browser poll.
 */
class CommunityWithoutModulesTest extends TestCase
{
    use RefreshDatabase;
    use RequiresNoModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfAnyModuleIsInstalled();
    }

    /** Screens that ship as a module, by the URL they used to occupy in core. */
    private const EXTRACTED_PATHS = [
        '/admin/anomaly-reasons',
        '/admin/production-anomalies',
    ];

    /** Tables a module creates, which a plain installation never has. */
    private const EXTRACTED_TABLES = [
        'anomaly_reasons',
        'production_anomalies',
    ];

    public function test_no_module_is_enabled_by_default(): void
    {
        // A fresh install is the community edition. Anything else would mean a
        // paid feature switched itself on.
        $this->assertSame([], app(\App\Services\ModuleManager::class)->enabledNames());
    }

    public function test_it_holds_no_route_for_an_extracted_screen(): void
    {
        foreach (['admin.anomaly-reasons.index', 'admin.production-anomalies.index'] as $name) {
            $this->assertFalse(Route::has($name), "Core still registers {$name}.");
        }
    }

    public function test_no_tab_claims_an_extracted_url(): void
    {
        // A prefix left behind in TabRegistry is worse than useless: the URL is
        // gone, but the matrix still offers the permission, and an operator
        // granted it gets a 404 from a menu that told them otherwise.
        foreach (self::EXTRACTED_PATHS as $path) {
            $this->assertNull(
                TabRegistry::tabForPath($path),
                "Core still gates {$path}, whose page it no longer has.",
            );
        }
    }

    public function test_no_synced_collection_points_at_a_missing_table(): void
    {
        // Every shape core offers must resolve to a table core creates —
        // otherwise the browser's first poll of that collection is a 500.
        $registry = app(ShapeRegistry::class);

        foreach (array_keys($registry->shapes()) as $name) {
            $shape = $registry->find($name);

            $this->assertTrue(
                Schema::hasTable($shape->table()),
                "Collection '{$name}' reads table '{$shape->table()}', which does not exist.",
            );
        }
    }

    public function test_the_trash_lists_no_model_core_does_not_have(): void
    {
        foreach (SoftDeleteRegistry::all() as $type => $class) {
            $this->assertTrue(class_exists($class), "Trash offers '{$type}', whose model {$class} is gone.");
        }
    }

    public function test_it_creates_none_of_the_extracted_tables(): void
    {
        foreach (self::EXTRACTED_TABLES as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "Core still creates '{$table}', which belongs to a module.",
            );
        }
    }

    public function test_the_admin_menu_offers_nothing_that_left(): void
    {
        $nav = file_get_contents(resource_path('js/layouts/adminNav.js'));

        foreach (self::EXTRACTED_PATHS as $path) {
            $this->assertStringNotContainsString(
                "href: '{$path}'",
                $nav,
                "The sidebar still links {$path}, which core no longer serves.",
            );
        }
    }
}
