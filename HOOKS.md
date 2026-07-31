# OpenMES Module Hooks

OpenMES can be extended with **modules** that plug into the core without editing
it. A module hooks into three kinds of extension point:

1. **Domain events** — react to production activity (an order is saved, a step
   completes, any resource changes, a work order is scheduled).
2. **Menu hooks** — add links and dropdowns to the sidebar.
3. **Dashboard widget hooks** — add cards to the admin dashboard.

> **New to modules?** Start with the step-by-step tutorial —
> [`backend/modules/README.md`](backend/modules/README.md) — which builds a working
> module from an empty folder and maps every core piece. This file is the
> **reference** (every hook + payload); the tutorial is the **how-to**.

Two reference modules ship in the repo (disabled by default):

- [`backend/modules/ExampleShowcase`](backend/modules/ExampleShowcase) — exercises **every** hook; copy it as a starting point.
- [`backend/modules/ExampleHooks`](backend/modules/ExampleHooks) — a smaller "hello world".

## 📋 Contents

- [How modules work](#how-modules-work)
- [Module structure](#module-structure)
- [1. Domain event hooks](#1-domain-event-hooks)
  - [Event catalog](#event-catalog)
  - [Generic CRUD hook — `ResourceChanged`](#generic-crud-hook--resourcechanged)
  - [Scheduling hook — `WorkOrderScheduled`](#scheduling-hook--workorderscheduled)
- [2. Menu hooks](#2-menu-hooks)
- [3. Dashboard widget hooks](#3-dashboard-widget-hooks)
- [Enabling a module](#enabling-a-module)
- [Best practices](#best-practices)
- [Complete hook reference](#complete-hook-reference)

---

## How modules work

- A module lives in `backend/modules/<Name>/` and is described by a `module.json`
  manifest. The `Modules\` namespace is PSR-4-mapped to that directory, so module
  classes autoload with no extra config.
- `App\Services\ModuleManager` discovers modules (`discover()`) and, for the ones
  that are **enabled**, registers their service provider on every boot
  (`loadEnabled()` in `AppServiceProvider`). A disabled module's provider never
  boots — **zero listeners, zero menu entries, zero widgets, zero runtime cost**.
- All wiring happens in the module's `ServiceProvider::boot()`.

There is no `config/app.php` editing and no `composer.json` provider entry — a
module is dropped into `backend/modules/` and toggled in the UI.

## Module structure

```
backend/modules/YourModule/
├── module.json                         # manifest (name, provider, declared hooks)
├── Hooks.php                           # your event-handler methods (optional convention)
├── Providers/
│   └── YourModuleServiceProvider.php   # boot(): wires events + menu + widgets
├── routes.php                          # your own page routes (optional)
├── views/                              # your own Blade pages/partials (optional)
└── README.md
```

### `module.json`

```json
{
    "name": "YourModule",
    "display_name": "Your Module",
    "version": "1.0.0",
    "description": "What it does.",
    "author": "You",
    "provider": "Modules\\YourModule\\Providers\\YourModuleServiceProvider",
    "hooks": [
        "WorkOrder\\WorkOrderCreated",
        "Resource\\ResourceChanged",
        "Menu\\addItem"
    ],
    "requires": []
}
```

`provider` is a **single** provider class string (must start with `Modules\`).
`hooks` is documentation only — the actual wiring is in the provider.

### The provider

```php
namespace Modules\YourModule\Providers;

use App\Events\WorkOrder\WorkOrderCreated;
use App\Services\MenuRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\YourModule\Hooks;

class YourModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Own routes/views first (menu links below may point at them).
        $this->loadViewsFrom(__DIR__.'/../views', 'your-module');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        // 1) Domain events
        Event::listen(WorkOrderCreated::class, [Hooks::class, 'onWorkOrderCreated']);

        // 2) Menu hooks
        $menu = app(MenuRegistry::class);
        $menu->addItem('production', 'My Page', url('/modules/your-module'));

        // 3) Widget hooks
        app(WidgetRegistry::class)->register('kpi', [
            'title' => 'My metric', 'metric' => '42',
        ]);
    }
}
```

---

## 1. Domain event hooks

Hooks are plain Laravel events. Listen to them in your provider's `boot()`.
Handlers may be a class with a `handle()` method, an invokable, a closure, or a
`[Class::class, 'method']` pair (the PrestaShop-style "one method per hook" that
`ExampleShowcase/Hooks.php` uses).

```php
use App\Events\WorkOrder\WorkOrderCompleted;

Event::listen(WorkOrderCompleted::class, function (WorkOrderCompleted $e) {
    ExternalErp::notifyCompletion($e->workOrder);   // read + act, never mutate the order
});
```

All lifecycle events fire from the **model layer**, so they trigger on every save
path — admin UI, CSV import, ERP API, internal services — without each caller
opting in.

### Event catalog

| Event | Fires when | Payload |
|---|---|---|
| `WorkOrder\WorkOrderCreated` | a work order is created | `workOrder` |
| `WorkOrder\WorkOrderUpdated` | a work order is updated | `workOrder`, `changes` (array of changed attributes) |
| `WorkOrder\WorkOrderCompleted` | a work order first enters `DONE` | `workOrder` |
| `Batch\BatchCreated` | a batch is created | `batch` |
| `BatchStep\StepStarted` | a step enters `IN_PROGRESS` | `batchStep` |
| `BatchStep\StepCompleted` | a step enters `DONE` | `batchStep` |
| `Machine\WorkstationStateChanged` | the signal pipeline changes a workstation state | `workstation`, `from`, `to`, `state` |
| `MachineMessageReceived` | an inbound MQTT message is parsed | `message` |
| `User\UserAssignedToLine` | a user is assigned to a line | `user`, `line` |
| `Resource\ResourceChanged` | **any** curated resource is created/updated/deleted | `model`, `action` |
| `Schedule\WorkOrderScheduled` | a work order is (re)placed on the planner | `workOrder`, `changes` |

See [Machine Connectivity](docs/machine-connectivity.md) for the signal-pipeline
events (`WorkstationStateChanged`, `MachineMessageReceived`).

### Generic CRUD hook — `ResourceChanged`

Instead of a separate event per entity, **one** event covers create/update/delete
for every user-CRUD-able resource — work orders, customers, materials, lines,
product types, and every other entry in `App\Support\SoftDeleteRegistry::MODELS`.
A single wildcard Eloquent listener re-dispatches it, so you hook any resource
save without wiring each model.

```php
use App\Events\Resource\ResourceChanged;
use App\Models\Customer;

Event::listen(ResourceChanged::class, function (ResourceChanged $e) {
    // $e->action is 'created' | 'updated' | 'deleted'
    // $e->model  is the affected model; $e->type() is its short class name
    if ($e->model instanceof Customer && $e->action === 'created') {
        Crm::push($e->model);
    }
});
```

The **typed** events (`WorkOrderCreated`, `BatchCreated`, …) still fire too — use
those when you care about one specific entity, and `ResourceChanged` when you want
"any resource".

### Scheduling hook — `WorkOrderScheduled`

Fired from the planner whenever a work order's placement changes — assigned to a
line, moved to another day/shift, resized, or unassigned.

```php
use App\Events\Schedule\WorkOrderScheduled;

Event::listen(WorkOrderScheduled::class, function (WorkOrderScheduled $e) {
    // $e->changes = the placement fields that were written
    Calendar::sync($e->workOrder, $e->changes);
});
```

---

## 2. Menu hooks

`App\Services\MenuRegistry` lets a module add entries to the sidebar. They are
bridged to the React frontend as the `moduleNav` Inertia prop and rendered by
`AppLayout`. Because module pages are server-rendered, menu links do a **full page
load** (not an Inertia visit).

```php
$menu = app(MenuRegistry::class);

// (a) inject a link into a built-in dropdown
//     keys: orders | production | structure | hr | maintenance | admin
$menu->addItem('production', 'My Page', url('/modules/your-module'), order: 90);

// (b) declare your own top-level dropdown …
$menu->addGroup('yourmod', 'Your Module', order: 55);
// (c) … and add links to it
$menu->addGroupItem('yourmod', 'Overview', url('/modules/your-module'), order: 10);
```

Resolve URLs with `url()` (not `route()`) so registration never depends on route
load order or a cached route table.

## 3. Dashboard widget hooks

`App\Services\WidgetRegistry` lets a module add cards to the admin dashboard.
A widget is **structured data** (not a Blade view): the React dashboard renders a
standard card and escapes every field, so a module never ships raw HTML. It is
exposed as the `moduleWidgets` prop by `DashboardController`.

```php
$widgets = app(WidgetRegistry::class);

$widgets->register('kpi', [
    'title'    => 'Open jobs',
    'metric'   => (string) $count,   // optional big number
    'body'     => 'Awaiting start',  // optional caption
    'href'     => url('/modules/your-module'), // optional link
    'external' => true,              // optional: full page load
], order: 50);
```

Zones: `kpi` and `sidebar` render as compact cards in the grid under the core
KPIs; `main` renders as a full-width card at the bottom of the dashboard column.

---

## Enabling a module

1. **Admin → Modules → your module → Enable** (or add its name to the
   `system_settings.modules_enabled` set).
2. On a production install with cached routes, run `php artisan route:cache` after
   enabling — module routes are registered at boot, not baked into the core route
   file. (Under Octane, reload workers so the newly enabled provider boots:
   `php artisan octane:reload`.)

Disable it again and every hook detaches — back to zero runtime cost.

---

## Best practices

1. **Never mutate core state from an event handler.** Handlers observe; they must
   not save/update the core models they are notified about, or you can cause
   double counts, re-entrant events and broken transactions. Do your side effects
   (notify, export, write your own tables) instead.
2. **Keep listeners focused** — one listener per concern. Register several rather
   than one giant closure.
3. **Queue heavy work** — implement `ShouldQueue` on listeners that call slow
   external systems, so they run in the background.
4. **Handle your own errors** — wrap external calls in try/catch and log; don't let
   your failure break the user's save or other listeners.
5. **Don't rely on listener order** across modules.
6. **Version and document** your module (`README.md`, semantic `version`).

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncToErp implements ShouldQueue
{
    public function handle(\App\Events\WorkOrder\WorkOrderCompleted $e): void
    {
        try {
            Erp::sync($e->workOrder);
        } catch (\Throwable $ex) {
            \Log::error('ERP sync failed', ['wo' => $e->workOrder->id, 'err' => $ex->getMessage()]);
        }
    }
}
```

---

## Complete hook reference

**Domain events** (`App\Events\…`): `WorkOrder\WorkOrderCreated`,
`WorkOrder\WorkOrderUpdated`, `WorkOrder\WorkOrderCompleted`, `Batch\BatchCreated`,
`BatchStep\StepStarted`, `BatchStep\StepCompleted`,
`Machine\WorkstationStateChanged`, `MachineMessageReceived`,
`User\UserAssignedToLine`, `Resource\ResourceChanged` (generic CRUD),
`Schedule\WorkOrderScheduled`.

**Menu** (`App\Services\MenuRegistry`): `addItem`, `addGroup`, `addGroupItem`.

**Widgets** (`App\Services\WidgetRegistry`): `register` — zones `kpi`, `main`,
`sidebar`.

---

## Support

- Issues: <https://github.com/Mes-Open/OpenMes/issues>
- Want a new hook? Open an issue with the name, when it should fire, its payload,
  and your use case.
