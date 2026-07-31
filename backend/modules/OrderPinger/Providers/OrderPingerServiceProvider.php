<?php

namespace Modules\OrderPinger\Providers;

use App\Events\Resource\ResourceChanged;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Services\MenuRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\OrderPinger\Hooks;

/**
 * Tutorial module — the exact output of backend/modules/README.md. Kept in the
 * repo as the tutorial's living proof: TutorialModuleTest asserts this wires up,
 * so the walkthrough can never silently rot.
 */
class OrderPingerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load our own views + routes FIRST (menu links may point at them).
        $this->loadViewsFrom(__DIR__.'/../views', 'order-pinger');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        // 1) EVENT HOOKS — react to production activity
        Event::listen(WorkOrderCreated::class, [Hooks::class, 'onWorkOrderCreated']);
        Event::listen(ResourceChanged::class, [Hooks::class, 'onResourceChanged']);

        // 2) MENU HOOK — a link in the sidebar
        app(MenuRegistry::class)->addItem('production', 'Order Pinger', url('/modules/order-pinger'), order: 90);

        // 3) WIDGET HOOK — a card on the dashboard
        app(WidgetRegistry::class)->register('kpi', [
            'title' => 'Order Pinger',
            'metric' => 'ON',
            'body' => 'Logging order saves',
            'href' => url('/modules/order-pinger'),
            'external' => true,
        ], order: 90);
    }
}
