<?php

namespace Tests\Feature\Web;

use App\Services\MenuRegistry;
use App\Services\ModuleManager;
use App\Services\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ExampleShowcase\Providers\ExampleShowcaseServiceProvider;
use Tests\TestCase;

/**
 * The ExampleShowcase reference module ships disabled and, once enabled, wires
 * into every extension point (events, menu, widgets) without touching core.
 */
class ExampleShowcaseModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_is_discoverable_and_disabled_by_default(): void
    {
        $showcase = app(ModuleManager::class)->discover()->firstWhere('name', 'ExampleShowcase');

        $this->assertNotNull($showcase, 'ExampleShowcase should be discovered from modules/.');
        $this->assertFalse($showcase['enabled'], 'It must ship disabled by default.');
        $this->assertTrue(class_exists($showcase['provider']), 'Provider class must autoload.');
    }

    public function test_enabling_the_provider_registers_menu_and_widget_hooks(): void
    {
        // ModuleManager::loadEnabled does exactly this for an enabled module.
        $this->app->register(ExampleShowcaseServiceProvider::class);

        // Menu: a link injected into a built-in dropdown + a custom top-level group.
        $menu = app(MenuRegistry::class);
        $this->assertNotEmpty($menu->getItems('production'));
        $this->assertContains('showcase', array_column($menu->getGroups(), 'id'));

        // Widgets: one registered per dashboard zone (structured data, not Blade).
        $widgets = app(WidgetRegistry::class);
        $this->assertTrue($widgets->hasWidgets('kpi'));
        $this->assertTrue($widgets->hasWidgets('main'));
        $this->assertTrue($widgets->hasWidgets('sidebar'));
        $this->assertSame('Example Showcase', $widgets->getWidgets('kpi')[0]['title']);

        // The module's own page route was registered (menu links resolve to it).
        // Name lookups are built lazily on match, so refresh them as a request would.
        app('router')->getRoutes()->refreshNameLookups();
        $this->assertTrue(app('router')->has('example-showcase.index'));
    }
}
