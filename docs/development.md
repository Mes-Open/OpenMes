# Technical Documentation

This document is intended for developers who want to contribute to OpenMES, build modules, or understand the internal architecture.

---

## Table of Contents

- [Tech Stack](#tech-stack)
- [Repository Structure](#repository-structure)
- [Local Development Setup](#local-development-setup)
- [Architecture Overview](#architecture-overview)
  - [Laravel Application Structure](#laravel-application-structure)
  - [Database Schema Overview](#database-schema-overview)
  - [Role-Based Access Control](#role-based-access-control)
- [Module System](#module-system)
  - [Module Structure](#module-structure)
  - [Service Provider Registration](#service-provider-registration)
  - [Adding Sidebar Navigation](#adding-sidebar-navigation)
  - [Module Database Migrations](#module-database-migrations)
  - [Extending Core Models](#extending-core-models)
- [Hook System](#hook-system)
- [Frontend (Blade + Alpine.js + Tailwind)](#frontend)
- [API Development](#api-development)
- [Testing](#testing)
- [Code Style](#code-style)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | Blade templates, Alpine.js, Tailwind CSS 4 |
| Reactive components | Livewire 4 |
| Database | PostgreSQL 14+ |
| Auth | Laravel Sanctum (session + token) |
| Roles | Spatie Laravel Permission |
| Asset pipeline | Vite |
| File imports | PhpSpreadsheet (via maatwebsite/excel) |
| Deployment | Docker Compose |

---

## Repository Structure

```
OpenMes/
├── backend/                    # Laravel application
│   ├── app/
│   │   ├── Console/Commands/   # Artisan commands
│   │   ├── Events/             # Laravel events
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/V1/     # REST API controllers
│   │   │   │   └── Web/        # Web (Blade) controllers
│   │   │   └── Requests/       # Form Request validation
│   │   ├── Models/             # Eloquent models
│   │   ├── Services/           # Business logic services
│   │   └── Livewire/           # Livewire components
│   ├── database/
│   │   ├── migrations/         # Core schema migrations
│   │   └── factories/          # Test data factories
│   ├── resources/
│   │   └── views/              # Blade templates
│   │       ├── admin/          # Admin panel views
│   │       ├── supervisor/     # Supervisor views
│   │       ├── operator/       # Operator views
│   │       └── layouts/        # Shared layouts & components
│   ├── routes/
│   │   ├── web.php             # Web routes
│   │   └── api.php             # API routes
│   └── tests/                  # Feature & unit tests
├── modules/                    # Optional modules directory
│   └── Packaging/              # Example: Packaging module
├── docs/                       # Documentation (this directory)
├── docker-compose.yml
└── README.md
```

---

## Local Development Setup

### Requirements

- Docker & Docker Compose
- PHP 8.3 (for running artisan commands locally without Docker)
- Composer
- Node.js 20+ and npm

### Step 1: Start the Environment

```bash
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes
docker-compose up -d
```

Navigate to `http://localhost` and complete the web installer.

### Step 2: Install Dependencies (for development)

```bash
cd backend
composer install
npm install
npm run dev     # starts Vite dev server with HMR
```

### Step 3: Running Artisan Commands

```bash
# Inside Docker
docker exec openmmes-backend php artisan <command>

# Or if PHP is installed locally
cd backend
php artisan <command>
```

### Useful Commands

```bash
# Run tests
php artisan test

# Run tests with coverage
php artisan test --coverage

# Reset and re-seed the database
php artisan migrate:fresh --seed

# Load sample data
php artisan db:seed --class=SampleDataSeeder

# Clear all caches
php artisan optimize:clear

# Run code formatter
./vendor/bin/pint
```

---

## Architecture Overview

### Laravel Application Structure

OpenMES follows standard Laravel conventions with a few additions:

**Web Controllers** (`app/Http/Controllers/Web/`) are split by role:
- `Web/Admin/` — admin panel controllers
- `Web/Supervisor/` — supervisor controllers
- `Web/Operator/` — operator controllers

**API Controllers** (`app/Http/Controllers/Api/V1/`) handle the REST API.

**Services** (`app/Services/`) contain reusable business logic:
- `ModuleManager` — discovers, enables, disables, installs, and uninstalls modules
- Other services for complex operations (import processing, batch creation, etc.)

**Livewire Components** (`app/Livewire/`) are reactive components used for real-time UI parts (e.g., dashboard metrics, live search).

### Database Schema Overview

Core entities and their relationships:

```
Factory → Division → Line → Workstation
                       ↓
                   WorkOrder → ProcessTemplate → TemplateStep
                       ↓
                     Batch → BatchStep (one per TemplateStep)
                       ↓
                     Issue → IssueType
```

Additional tables:
- `users` — authentication, Spatie roles
- `workers` — shop floor workers (separate from user accounts)
- `crews`, `skills`, `wage_groups` — HR
- `shifts` — working hours definition
- `audit_logs` — immutable change history
- `event_logs` — system events
- `csv_imports`, `csv_import_mappings` — bulk import history and profiles
- `system_settings` — key/value configuration store
- `line_statuses` — configurable line status codes

### Role-Based Access Control

Roles are managed with Spatie Laravel Permission:

```php
// In controllers
$this->middleware('role:Admin');
$this->middleware('role:Supervisor|Admin');

// In Blade templates
@hasrole('Admin')
    <admin-only-content/>
@endhasrole

@hasanyrole('Supervisor|Admin')
    <supervisor-and-admin-content/>
@endhasanyrole
```

The three main roles:
- `Admin` — full system access
- `Supervisor` — production management, no system configuration
- `Operator` — own line only, no management views

---

## Module System

Modules are self-contained Laravel packages located in the `modules/` directory at the root of the project. They are auto-discovered and loaded by the core application.

### Module Structure

```
modules/
└── MyModule/
    ├── module.json                         # Required: module metadata
    ├── Providers/
    │   └── MyModuleServiceProvider.php     # Required: registers everything
    ├── Controllers/
    │   └── MyModuleController.php
    ├── Models/
    │   └── MyEntity.php
    ├── views/
    │   └── index.blade.php
    ├── migrations/
    │   └── 2025_01_01_000001_create_my_table.php
    └── Console/
        └── MyCommand.php
```

### module.json

```json
{
    "name": "MyModule",
    "display_name": "My Module",
    "description": "A short description of what this module does.",
    "version": "1.0.0",
    "author": "Your Name"
}
```

### Service Provider Registration

The `ModuleManager` discovers modules by scanning `modules/*/Providers/*ServiceProvider.php`. For a module to be loaded, its service provider must be registered.

Create `modules/MyModule/Providers/MyModuleServiceProvider.php`:

```php
<?php

namespace Modules\MyModule\Providers;

use Illuminate\Support\ServiceProvider;

class MyModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Register Blade views (access via my-module::view-name)
        $this->loadViewsFrom(__DIR__ . '/../views', 'my-module');

        // Register migrations
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\MyModule\Console\MyCommand::class,
            ]);
        }
    }
}
```

### Adding Sidebar Navigation

Modules can add items to the sidebar by hooking into `MenuRegistry`:

```php
use App\Services\MenuRegistry;

// Inside boot() method:
MenuRegistry::add('my-module', [
    'label'  => 'My Module',
    'icon'   => '<svg .../>',
    'route'  => 'my-module.index',
    'role'   => 'Admin',           // or 'Supervisor|Admin', 'Operator|Supervisor|Admin'
]);
```

### Module Database Migrations

Place migration files in `modules/MyModule/migrations/`. They follow the same format as Laravel migrations:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('my_module_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_module_entities');
    }
};
```

Run migrations: `php artisan migrate`

### Extending Core Models

To add a relationship to a core model (e.g. WorkOrder) from a module, use the service provider's `boot()` method:

```php
use App\Models\WorkOrder;
use Modules\MyModule\Models\MyEntity;

WorkOrder::resolveRelationUsing('myEntities', function (WorkOrder $model) {
    return $model->hasMany(MyEntity::class);
});
```

Now `$workOrder->myEntities` is available throughout the application.

---

## Hook System

OpenMES fires Laravel Events throughout the production lifecycle. Modules listen to these events to react to changes without modifying core code.

See [HOOKS.md](../HOOKS.md) for the full list of available events and examples.

### Quick Example

Listen to work order completion in your module:

```php
// In MyModuleServiceProvider::boot()
use App\Events\WorkOrder\WorkOrderCompleted;

Event::listen(WorkOrderCompleted::class, function ($event) {
    $workOrder = $event->workOrder;
    // Send ERP notification, update inventory, etc.
});
```

---

## Frontend

OpenMES uses **server-rendered Blade templates** with:

- **Tailwind CSS 4** — utility-first CSS (compiled via Vite)
- **Alpine.js** — lightweight reactivity for interactive components
- **Livewire 4** — full-stack reactive components (forms, live tables)
- **Chart.js** — dashboard charts

### Tailwind Dark Mode

The app supports dark mode via the `dark` class on `<html>`:

```html
<!-- Dark mode styles -->
<div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
```

Dark mode preference is stored in `localStorage` under the key `theme`.

### Alpine.js State

Sidebar state (`collapsed`, group open/close states) is managed in the root `x-data` on the layout. The persistent sidebar collapse state is stored in `localStorage` under the key `sb`.

### Adding Views in a Module

Blade views in modules use the namespace registered in the service provider:

```php
// In ServiceProvider
$this->loadViewsFrom(__DIR__ . '/../views', 'my-module');
```

```php
// In controller
return view('my-module::index', compact('data'));
```

Extend the main layout:

```blade
@extends('layouts.app')

@section('title', 'My Module Page')

@section('content')
    <div class="max-w-4xl mx-auto">
        {{-- your content --}}
    </div>
@endsection
```

---

## API Development

### Adding a New API Endpoint

1. Create a Form Request in `app/Http/Requests/`
2. Create a controller in `app/Http/Controllers/Api/V1/`
3. Add the route in `routes/api.php` inside the `v1` group
4. Use `auth:sanctum` middleware (already applied to the group)
5. Write a Feature test

### Form Request Validation

Always use Form Requests, never validate inline in controllers:

```php
class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Admin');
    }

    public function rules(): array
    {
        return [
            'order_no'   => ['required', 'string', 'unique:work_orders,order_no'],
            'quantity'   => ['required', 'integer', 'min:1'],
            'due_date'   => ['nullable', 'date', 'after:today'],
        ];
    }
}
```

---

## Testing

OpenMES uses Laravel's built-in testing framework. Tests live in `backend/tests/`.

```bash
# Run all tests
php artisan test

# Run a specific test file
php artisan test tests/Feature/WorkOrderApiTest.php

# Run with coverage report
php artisan test --coverage
```

### Test Conventions

Every feature must have tests covering:
1. Happy path
2. Validation errors (missing/invalid fields)
3. Authorization (unauthenticated + wrong role)
4. Edge cases

Example test:

```php
class WorkOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_work_order(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');

        $response = $this->actingAs($admin)->postJson('/api/v1/work-orders', [
            'order_no' => 'WO-TEST-001',
            'quantity' => 100,
        ]);

        $response->assertCreated()
                 ->assertJsonPath('data.order_no', 'WO-TEST-001');

        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-TEST-001']);
    }

    public function test_operator_cannot_create_work_order(): void
    {
        $operator = User::factory()->create()->assignRole('Operator');

        $this->actingAs($operator)
             ->postJson('/api/v1/work-orders', ['order_no' => 'WO-X', 'quantity' => 1])
             ->assertForbidden();
    }
}
```

---

## Code Style

Run before committing:

```bash
./vendor/bin/pint
```

Pint uses the Laravel preset (PSR-12 based). CI enforces formatting — PRs will fail if `pint --test` reports changes.

### Key Conventions

- No raw SQL with user input — always use Eloquent or Query Builder
- No inline validation in controllers — always use Form Requests
- No business logic in controllers — move to Services or model methods
- `app/Services/` for reusable logic
- Use transactions for multi-step DB operations
- Log security events (auth failures, permission denials)
