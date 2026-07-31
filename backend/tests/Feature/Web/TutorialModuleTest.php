<?php

namespace Tests\Feature\Web;

use App\Events\Resource\ResourceChanged;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Models\Customer;
use App\Models\WorkOrder;
use App\Services\MenuRegistry;
use App\Services\ModuleManager;
use App\Services\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\OrderPinger\Providers\OrderPingerServiceProvider;
use Tests\TestCase;

/**
 * Dogfoods the module-writing tutorial (backend/modules/README.md): OrderPinger is
 * the module a developer builds by following it verbatim. This test asserts that
 * following the tutorial produces a working module — so the walkthrough can never
 * silently rot. If a documented step is wrong, this test fails.
 */
class TutorialModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutorial_module_is_discoverable_and_disabled_by_default(): void
    {
        $module = app(ModuleManager::class)->discover()->firstWhere('name', 'OrderPinger');

        $this->assertNotNull($module, 'OrderPinger should be discovered from modules/.');
        $this->assertFalse($module['enabled'], 'A new module ships disabled.');
        $this->assertTrue(class_exists($module['provider']), 'Provider class must autoload.');
    }

    public function test_following_the_tutorial_produces_a_working_module(): void
    {
        // Step 7: enabling the module registers its provider (ModuleManager does this).
        $this->app->register(OrderPingerServiceProvider::class);

        // Step 3: event hooks are wired.
        $this->assertTrue(Event::hasListeners(WorkOrderCreated::class));
        $this->assertTrue(Event::hasListeners(ResourceChanged::class));

        // Step 4: the menu link landed in the Production dropdown.
        $labels = array_column(app(MenuRegistry::class)->getItems('production'), 'label');
        $this->assertContains('Order Pinger', $labels);

        // Step 5: the dashboard KPI widget is registered.
        $widgets = app(WidgetRegistry::class);
        $this->assertTrue($widgets->hasWidgets('kpi'));
        $this->assertSame('Order Pinger', $widgets->getWidgets('kpi')[0]['title']);

        // Step 6: the module's own page route exists.
        app('router')->getRoutes()->refreshNameLookups();
        $this->assertTrue(app('router')->has('order-pinger.status'));

        // End to end: real saves fire the events and run the module's handlers.
        // A bug in Hooks.php (bad method/reference) would throw here.
        $wo = WorkOrder::factory()->create();       // → WorkOrderCreated + ResourceChanged
        $customer = Customer::factory()->create();   // → ResourceChanged (Customer branch)

        $this->assertModelExists($wo);
        $this->assertModelExists($customer);
    }
}
