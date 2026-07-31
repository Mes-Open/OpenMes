# Building an OpenMES module — hands-on tutorial

This is a **step-by-step walkthrough**: you'll build a working module from an empty
folder, hook into events, add a sidebar link and a dashboard card, ship your own
page, enable it, and watch it run. At the end there's a **code map** describing
every core piece that makes modules work.

> **Reference, not tutorial?** For the full catalog of every hook and its payload,
> see [`../../HOOKS.md`](../../HOOKS.md). This file teaches you *how*; HOOKS.md
> lists *what*.
>
> **In a hurry?** Copy [`ExampleShowcase/`](ExampleShowcase) (every hook) or
> [`ExampleHooks/`](ExampleHooks) (minimal) and edit. This tutorial explains what
> those modules do and why.

---

## What a module is

A module is a small Laravel package under `backend/modules/<Name>/` that plugs into
OpenMES **without editing core code**. It can:

- **listen to domain events** (an order is saved, a step finishes, any resource
  changes, a work order is scheduled),
- **add menu entries** to the React sidebar,
- **add cards** to the admin dashboard,
- **serve its own pages** (routes + Blade),
- ship its own models, migrations, services — anything a Laravel package can.

It is **disabled by default** and toggled in **Admin → Modules**. Disabled = its
provider never boots = zero listeners, zero cost.

We'll build a module called **`OrderPinger`** that logs every order save, adds a
sidebar link and a dashboard KPI, and serves a status page.

---

## Prerequisites

- A running OpenMES dev environment (`docker compose … up`, see the root README).
- You edit files under `backend/modules/` — the dev overlay bind-mounts that
  directory, so changes are live.

---

## Step 1 — Create the folder and manifest

```
backend/modules/OrderPinger/
└── module.json
```

`module.json` is the manifest OpenMES reads to discover your module:

```json
{
    "name": "OrderPinger",
    "display_name": "Order Pinger",
    "version": "1.0.0",
    "description": "Logs order saves and shows a dashboard KPI.",
    "author": "You",
    "provider": "Modules\\OrderPinger\\Providers\\OrderPingerServiceProvider",
    "hooks": [
        "WorkOrder\\WorkOrderCreated",
        "Resource\\ResourceChanged",
        "Menu\\addItem",
        "Widget\\kpi"
    ],
    "requires": []
}
```

Field by field:

| Field | Meaning |
|---|---|
| `name` | Unique id. Alphanumeric/underscore/hyphen only. Must match the folder name. |
| `display_name` | Shown in Admin → Modules. |
| `version` | Semantic version. |
| `provider` | **Single** service-provider class. Must start with `Modules\`. This is the only entry point OpenMES loads. |
| `hooks` | Documentation only — a human-readable list of what you plug into. The real wiring lives in the provider. |
| `requires` | Reserved for future dependency checks; use `[]`. |

> **Autoloading is automatic.** `composer.json` maps `Modules\` (PSR-4) to
> `backend/modules/`, so `Modules\OrderPinger\…` classes load with no extra config,
> no `composer dump-autoload` needed for new files.

---

## Step 2 — The service provider (the entry point)

Everything your module does is wired in one place: `ServiceProvider::boot()`.
OpenMES registers this provider **only when the module is enabled**.

```php
// backend/modules/OrderPinger/Providers/OrderPingerServiceProvider.php
namespace Modules\OrderPinger\Providers;

use App\Events\Resource\ResourceChanged;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Services\MenuRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\OrderPinger\Hooks;

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
            'title'  => 'Order Pinger',
            'metric' => 'ON',
            'body'   => 'Logging order saves',
            'href'   => url('/modules/order-pinger'),
            'external' => true,
        ], order: 90);
    }
}
```

Why `boot()` and not `register()`? Because the registries and the event dispatcher
already exist by boot time. Why `url()` and not `route()` for menu links? So the
menu never depends on route-loading order or a cached route table.

---

## Step 3 — Event hooks (one method per hook)

Put your handlers in a single `Hooks` class — the PrestaShop-style "one method per
hook". OpenMES dispatches the domain events from the **model layer**, so they fire
on *every* save path (admin UI, CSV import, ERP API, services).

```php
// backend/modules/OrderPinger/Hooks.php
namespace Modules\OrderPinger;

use App\Events\Resource\ResourceChanged;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class Hooks
{
    // Typed hook: fires only for work orders.
    public function onWorkOrderCreated(WorkOrderCreated $e): void
    {
        Log::channel('daily')->info('[OrderPinger] order created', [
            'order_no' => $e->workOrder->order_no,
        ]);
    }

    // Generic CRUD hook: fires for ANY curated resource. Filter what you want.
    public function onResourceChanged(ResourceChanged $e): void
    {
        if ($e->model instanceof Customer && $e->action === 'created') {
            Log::channel('daily')->info('[OrderPinger] new customer', ['id' => $e->model->id]);
        }
    }
}
```

> ⚠️ **Golden rule: never mutate core state from a handler.** Read the payload and
> do your *own* side effects (log, notify, call an ERP, write your own tables).
> Saving the core model you were just notified about causes re-entrant events,
> double counts, and broken transactions.

Pick your events from [HOOKS.md](../../HOOKS.md). The most useful:

- `WorkOrder\WorkOrderCreated` / `WorkOrderUpdated` / `WorkOrderCompleted`
- `Batch\BatchCreated`, `BatchStep\StepStarted` / `StepCompleted`
- `Resource\ResourceChanged` — **any** create/update/delete of a curated resource
- `Schedule\WorkOrderScheduled` — planner placement changes

---

## Step 4 — Menu hook (sidebar)

`MenuRegistry` (used in Step 2) gives you three calls:

```php
$menu = app(MenuRegistry::class);

// (a) Add a link into a built-in dropdown.
//     Group keys: orders | production | structure | hr | maintenance | admin
$menu->addItem('production', 'Order Pinger', url('/modules/order-pinger'));

// (b) Or declare your OWN top-level dropdown …
$menu->addGroup('pinger', 'Pinger', order: 55);
// (c) … and fill it
$menu->addGroupItem('pinger', 'Status', url('/modules/order-pinger'));
$menu->addGroupItem('pinger', 'Docs', url('/modules/order-pinger#docs'), order: 20);
```

These render in the **React sidebar** (not Blade). Because module pages are
server-rendered, the links do a **full page load**, not an Inertia navigation.

---

## Step 5 — Widget hook (dashboard card)

`WidgetRegistry` adds a card to the admin dashboard. A widget is **structured
data**, never HTML — the React dashboard renders a standard card and escapes every
field.

```php
app(WidgetRegistry::class)->register('kpi', [
    'title'  => 'Open orders',          // required
    'metric' => (string) $count,        // optional: big number
    'body'   => 'Awaiting start',       // optional: caption
    'href'   => url('/modules/order-pinger'),  // optional: link
    'external' => true,                 // optional: full page load
], order: 50);
```

Zones: `kpi` and `sidebar` → compact cards in the grid under the core KPIs;
`main` → full-width card at the bottom.

---

## Step 6 — Your own page (routes + view)

Modules serve their own pages as plain Blade (full page loads). Keep the route in
the `web` + `auth` groups so it has a session and requires login.

```php
// backend/modules/OrderPinger/routes.php
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/modules/order-pinger', fn () => view('order-pinger::status'))
        ->name('order-pinger.status');
});
```

```blade
{{-- backend/modules/OrderPinger/views/status.blade.php --}}
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Order Pinger</title></head>
<body style="font:15px system-ui;padding:2rem">
    <h1>Order Pinger</h1>
    <p>This page is served entirely from the module — no core file was touched.</p>
    <p>Tail the log to watch hooks fire:</p>
    <pre>tail -f storage/logs/laravel-*.log | grep OrderPinger</pre>
</body></html>
```

The `order-pinger::status` view name works because Step 2 called
`loadViewsFrom(__DIR__.'/../views', 'order-pinger')`.

---

## Step 7 — Enable it

1. **Admin → Modules → Order Pinger → Enable** (or add `"OrderPinger"` to the
   `system_settings.modules_enabled` JSON).
2. If routes are cached (production): `php artisan route:cache`.
3. Under Octane (workers stay in memory): `php artisan octane:reload` so the newly
   enabled provider boots.

Final layout:

```
backend/modules/OrderPinger/
├── module.json
├── Hooks.php
├── Providers/OrderPingerServiceProvider.php
├── routes.php
└── views/status.blade.php
```

---

## Step 8 — Verify

- **Sidebar**: "Order Pinger" appears under **Production** (and your custom group
  if you added one). Clicking it opens your Blade page.
- **Dashboard**: your card shows under the core KPIs.
- **Events**: create/edit an order, then:
  ```bash
  docker exec <backend> sh -c "tail -f storage/logs/laravel-*.log | grep OrderPinger"
  ```
  You'll see `[OrderPinger] order created …`.

Disable it again in Admin → Modules and everything detaches — no leftover cost.

---

## How it works under the hood — code map

Where each piece lives in the core, so you know what you're plugging into:

### Discovery & loading

| File | Responsibility |
|---|---|
| `app/Services/ModuleManager.php` | `discover()` scans `backend/modules/*/module.json`; `enable()`/`disable()` write `system_settings.modules_enabled`; `loadEnabled()` registers each enabled module's `provider`. |
| `app/Providers/AppServiceProvider.php` | Calls `ModuleManager::loadEnabled()` on every boot (wrapped in try/catch so a broken module never stops the app). Also registers the event plumbing below. |
| `app/Http/Controllers/Web/Admin/ModulesController.php` | The **Admin → Modules** page (list, enable, disable). |
| `composer.json` (`autoload.psr-4`) | Maps `Modules\` → `modules/`, so your classes autoload. |

### Event hooks

| File | Responsibility |
|---|---|
| `app/Events/**` | The event classes you listen to (`WorkOrder\WorkOrderCreated`, `Resource\ResourceChanged`, `Schedule\WorkOrderScheduled`, …). |
| `app/Observers/WorkOrderEventObserver.php` | Dispatches `WorkOrderCreated` / `WorkOrderUpdated` / `WorkOrderCompleted` from the model lifecycle — this is why the events fire on **every** save path. |
| `app/Observers/BatchStepEventObserver.php` | Dispatches `StepStarted` / `StepCompleted` on status transitions. |
| `app/Models/Batch.php` (`$dispatchesEvents`) | Fires `BatchCreated` on create. |
| `app/Providers/AppServiceProvider.php` | Registers those observers **and** a wildcard Eloquent listener (`eloquent.{created,updated,deleted}: *`) that re-dispatches **`ResourceChanged`** for every model in `SoftDeleteRegistry::MODELS` — the generic CRUD hook. |
| `app/Support/SoftDeleteRegistry.php` | The curated list of resources `ResourceChanged` covers (work orders, customers, materials, lines, …). |
| `app/Http/Controllers/Web/Admin/SchedulePlannerController.php` | Dispatches `WorkOrderScheduled` from the planner (`updateOrder` + `resizeOrder`). |

### Menu hooks

| File | Responsibility |
|---|---|
| `app/Services/MenuRegistry.php` | Holds `addItem`/`addGroup`/`addGroupItem` entries a module registers. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Bridges the registry to the frontend as the **`moduleNav`** Inertia prop (`items` + `groups`). |
| `resources/js/layouts/AppLayout.jsx` | `mergeModuleNav()` merges those entries into the sidebar; injected items ride their built-in dropdown, custom groups render as new dropdowns. Module links are plain `<a>` (full load). |

### Widget hooks

| File | Responsibility |
|---|---|
| `app/Services/WidgetRegistry.php` | Holds the structured widget data a module registers (`register($zone, [...])`). |
| `app/Http/Controllers/Web/Admin/DashboardController.php` | Exposes the registry as the **`moduleWidgets`** Inertia prop. |
| `resources/js/Pages/admin/Dashboard.jsx` | Renders a `ModuleWidget` card per registered widget across the `kpi` / `main` / `sidebar` zones. |

### The flow, end to end

```
enable module ─► ModuleManager::loadEnabled ─► YourServiceProvider::boot()
   ├─ Event::listen(...)            → your Hooks methods run when core dispatches events
   │      core save ─► Observer/$dispatchesEvents/wildcard listener ─► event ─► your handler
   ├─ MenuRegistry::addItem(...)    → HandleInertiaRequests(moduleNav) ─► AppLayout sidebar
   └─ WidgetRegistry::register(...) → DashboardController(moduleWidgets) ─► Dashboard cards
```

---

## Best practices

1. **Never mutate core state in an event handler** (see the golden rule above).
2. **Keep handlers small** — one concern each; register several listeners rather
   than one giant closure.
3. **Queue heavy work** — `implements ShouldQueue` on listeners that call slow
   external systems.
4. **Catch your own errors** — wrap external calls in try/catch + log; don't break
   the user's save.
5. **Don't rely on listener order** across modules.
6. **Ship a README + semantic version**; document which hooks you use.

---

## Reference modules in this folder

| Module | What it shows |
|---|---|
| [`ExampleShowcase/`](ExampleShowcase) | **Every** hook: all domain events (one method per hook), all three menu APIs, all widget zones, its own page. Copy this. |
| [`ExampleHooks/`](ExampleHooks) | Minimal starting point: a few event listeners + one menu link. |

Full hook catalog with payloads: [`../../HOOKS.md`](../../HOOKS.md).
