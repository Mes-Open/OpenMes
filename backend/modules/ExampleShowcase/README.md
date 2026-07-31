# Example Showcase module

A **reference module** that touches **every** OpenMES extension point — all domain
events, all menu hooks, and all dashboard-widget hooks — in one place, so you can
copy it as the starting point for a real module.

- **Disabled by default.** It ships in the repo but is not in
  `system_settings.modules_enabled`, so its provider is never booted and it adds
  nothing until you turn it on.
- **Non-intrusive.** Everything lives under `backend/modules/ExampleShowcase/`.
  No core file is edited. Event handlers only *read and log* — they never mutate
  core objects (see the warning in `Hooks.php`).

## Enable it

1. **Admin → Modules → Example Showcase → Enable** (or add `"ExampleShowcase"` to
   the `modules_enabled` system setting).
2. The provider (`ExampleShowcaseServiceProvider`) boots and wires up the hooks.
3. On a production install with cached routes, run `php artisan route:cache` after
   enabling (module routes are registered at boot, not in the core route file).

Disable it again and every hook detaches — back to zero runtime cost.

## What it demonstrates

### 1. Domain events (`Hooks.php`)

PrestaShop-style: one method per hook. The provider maps each event to its method.

| Event | Method |
|---|---|
| `WorkOrder\WorkOrderCreated` | `onWorkOrderCreated` |
| `WorkOrder\WorkOrderUpdated` | `onWorkOrderUpdated` |
| `WorkOrder\WorkOrderCompleted` | `onWorkOrderCompleted` |
| `Batch\BatchCreated` | `onBatchCreated` |
| `BatchStep\StepStarted` | `onStepStarted` |
| `BatchStep\StepCompleted` | `onStepCompleted` |
| `Machine\WorkstationStateChanged` | `onWorkstationStateChanged` |
| `MachineMessageReceived` | `onMachineMessageReceived` |
| `User\UserAssignedToLine` | `onUserAssignedToLine` |

Watch them fire while enabled:

```bash
tail -f storage/logs/laravel-*.log | grep ExampleShowcase
```

### 2. Menu hooks (`MenuRegistry`)

All three APIs, in `ExampleShowcaseServiceProvider::registerMenuHooks()`:

- `addItem('production', …)` — inject a link into an existing dropdown
  (built-in keys: `orders | production | structure | hr | maintenance | admin`).
- `addGroup('showcase', …)` — declare a brand-new top-level dropdown.
- `addGroupItem('showcase', …)` — add links to that dropdown.

These render in the **React sidebar** via the `moduleNav` Inertia prop. Because
module pages are server-rendered (Blade), the links do a **full page load**, not
an Inertia visit. The injected "Showcase Page" link opens this module's own page
(`/modules/example-showcase`, `views/index.blade.php`).

### 3. Dashboard widget hooks (`WidgetRegistry`)

`registerWidgetHooks()` registers one card per zone (`kpi | main | sidebar`).
A widget is **structured data** — `title`, optional `metric`, `body`, `href`,
`external` — not a Blade view. The React dashboard renders a standard card from
those fields (`DashboardController` → the `moduleWidgets` Inertia prop), and every
value is escaped by React, so a module never ships raw HTML.

- `kpi` / `sidebar` → compact cards in the grid under the core KPIs.
- `main` → full-width card at the bottom of the dashboard column.

## Layout

```
modules/ExampleShowcase/
├── module.json                 # manifest: name, provider, declared hooks
├── Hooks.php                   # one method per domain-event hook (read + log)
├── Providers/
│   └── ExampleShowcaseServiceProvider.php   # wires events + menu + widgets
├── routes.php                  # the module's own page route (web + auth)
├── views/
│   └── index.blade.php         # self-contained module page (full page load)
└── README.md
```

Compare with `modules/ExampleHooks/` — a smaller starting point that logs a few
events and adds a single menu link.
