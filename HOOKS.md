# OpenMES Hooks & Events System

OpenMES provides a comprehensive hook system that allows you to extend functionality without modifying core code. This is perfect for creating custom modules and integrations.

## 📋 Table of Contents

- [How Hooks Work](#how-hooks-work)
- [Creating a Module](#creating-a-module)
- [Available Hooks](#available-hooks)
  - [Work Order Hooks](#work-order-hooks)
  - [Batch Hooks](#batch-hooks)
  - [Batch Step Hooks](#batch-step-hooks)
  - [User Hooks](#user-hooks)
  - [Line Hooks](#line-hooks)
  - [Process Template Hooks](#process-template-hooks)
  - [CSV Import Hooks](#csv-import-hooks)
- [Best Practices](#best-practices)

---

## How Hooks Work

OpenMES uses Laravel's event system. Each hook is an Event that you can listen to using Event Listeners.

### Basic Usage

1. Create an event listener in your module
2. Register it in your module's service provider
3. The listener will be called automatically when the event occurs

### Example

```php
// modules/MyModule/Listeners/NotifyOnWorkOrderComplete.php
namespace Modules\MyModule\Listeners;

use App\Events\WorkOrder\WorkOrderCompleted;

class NotifyOnWorkOrderComplete
{
    public function handle(WorkOrderCompleted $event): void
    {
        $workOrder = $event->workOrder;

        // Your custom logic here
        // e.g., send notification, update external system, etc.
    }
}
```

```php
// modules/MyModule/Providers/MyModuleServiceProvider.php
namespace Modules\MyModule\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\WorkOrder\WorkOrderCompleted;
use Modules\MyModule\Listeners\NotifyOnWorkOrderComplete;

class MyModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            WorkOrderCompleted::class,
            NotifyOnWorkOrderComplete::class
        );
    }
}
```

---

## Creating a Module

### Module Structure

```
modules/
└── YourModule/
    ├── Providers/
    │   └── YourModuleServiceProvider.php
    ├── Listeners/
    │   └── YourListener.php
    ├── Models/
    │   └── YourModel.php
    ├── Controllers/
    │   └── YourController.php
    ├── views/
    │   └── your-view.blade.php
    ├── routes/
    │   └── web.php
    ├── database/
    │   └── migrations/
    └── module.json
```

### module.json Example

```json
{
    "name": "YourModule",
    "description": "Description of your module",
    "version": "1.0.0",
    "author": "Your Name",
    "providers": [
        "Modules\\YourModule\\Providers\\YourModuleServiceProvider"
    ]
}
```

### Registering Your Module

Add to `config/app.php`:

```php
'providers' => [
    // ...
    Modules\YourModule\Providers\YourModuleServiceProvider::class,
],
```

Or use auto-discovery by adding to `composer.json`:

```json
"extra": {
    "laravel": {
        "providers": [
            "Modules\\YourModule\\Providers\\YourModuleServiceProvider"
        ]
    }
}
```

---

## Available Hooks

### Work Order Hooks

#### `WorkOrderCreated`
**Fired when:** A new work order is created
**Location:** `App\Events\WorkOrder\WorkOrderCreated`
**Data available:**
- `$event->workOrder` - The created WorkOrder model

**Use cases:**
- Send notifications to production team
- Update external ERP system
- Generate QR codes for work orders
- Trigger automated workflows

**Example:**
```php
use App\Events\WorkOrder\WorkOrderCreated;

Event::listen(WorkOrderCreated::class, function ($event) {
    $workOrder = $event->workOrder;

    // Send email notification
    Mail::to('production@company.com')
        ->send(new WorkOrderCreatedNotification($workOrder));

    // Sync with external system
    ExternalAPI::createWorkOrder([
        'number' => $workOrder->work_order_number,
        'quantity' => $workOrder->quantity,
    ]);
});
```

---

#### `WorkOrderUpdated`
**Fired when:** A work order is updated
**Location:** `App\Events\WorkOrder\WorkOrderUpdated`
**Data available:**
- `$event->workOrder` - The updated WorkOrder model
- `$event->changes` - Array of changed attributes

**Use cases:**
- Track changes for audit purposes
- Sync updates with external systems
- Send change notifications

**Example:**
```php
use App\Events\WorkOrder\WorkOrderUpdated;

Event::listen(WorkOrderUpdated::class, function ($event) {
    if (isset($event->changes['status'])) {
        // Status changed - notify stakeholders
        Notification::send(
            $event->workOrder->line->users,
            new WorkOrderStatusChanged($event->workOrder)
        );
    }
});
```

---

#### `WorkOrderCompleted`
**Fired when:** A work order is marked as completed
**Location:** `App\Events\WorkOrder\WorkOrderCompleted`
**Data available:**
- `$event->workOrder` - The completed WorkOrder model

**Use cases:**
- Update inventory systems
- Generate completion reports
- Trigger quality control workflows
- Calculate production metrics

**Example:**
```php
use App\Events\WorkOrder\WorkOrderCompleted;

Event::listen(WorkOrderCompleted::class, function ($event) {
    $workOrder = $event->workOrder;

    // Update inventory
    Inventory::increment($workOrder->product_type_id, $workOrder->quantity);

    // Generate report
    Report::generateProductionReport($workOrder);

    // Notify warehouse
    event(new NotifyWarehouse($workOrder));
});
```

---

### Batch Hooks

#### `BatchCreated`
**Fired when:** A new batch is created
**Location:** `App\Events\Batch\BatchCreated`
**Data available:**
- `$event->batch` - The created Batch model

**Use cases:**
- Initialize tracking systems
- Create batch-specific resources
- Send start notifications

**Example:**
```php
use App\Events\Batch\BatchCreated;

Event::listen(BatchCreated::class, function ($event) {
    $batch = $event->batch;

    // Create tracking record
    TrackingSystem::createBatch([
        'batch_number' => $batch->batch_number,
        'work_order' => $batch->workOrder->work_order_number,
        'quantity' => $batch->quantity,
    ]);
});
```

---

#### `BatchCompleted`
**Fired when:** A batch is completed
**Location:** `App\Events\Batch\BatchCompleted`
**Data available:**
- `$event->batch` - The completed Batch model

**Use cases:**
- Calculate batch metrics
- Update production statistics
- Trigger next batch creation

---

### Batch Step Hooks

#### `StepStarted`
**Fired when:** A batch step is started
**Location:** `App\Events\BatchStep\StepStarted`
**Data available:**
- `$event->batchStep` - The started BatchStep model

**Use cases:**
- Start timers
- Notify operators
- Check equipment availability

**Example:**
```php
use App\Events\BatchStep\StepStarted;

Event::listen(StepStarted::class, function ($event) {
    $step = $event->batchStep;

    // Check if workstation is available
    if ($step->templateStep->workstation) {
        WorkstationMonitor::markBusy($step->templateStep->workstation_id);
    }

    // Start time tracking
    TimeTracker::startStep($step->id);
});
```

---

#### `StepCompleted`
**Fired when:** A batch step is completed
**Location:** `App\Events\BatchStep\StepCompleted`
**Data available:**
- `$event->batchStep` - The completed BatchStep model

**Use cases:**
- Calculate step duration
- Update workstation availability
- Trigger quality checks

**Example:**
```php
use App\Events\BatchStep\StepCompleted;

Event::listen(StepCompleted::class, function ($event) {
    $step = $event->batchStep;

    // Calculate actual vs estimated time
    $actual = $step->completed_at->diffInMinutes($step->started_at);
    $estimated = $step->templateStep->estimated_duration_minutes;

    if ($actual > $estimated * 1.2) {
        // Step took 20% longer than expected
        Alert::create([
            'type' => 'slow_step',
            'step_id' => $step->id,
            'message' => "Step took {$actual}min (expected {$estimated}min)",
        ]);
    }

    // Free up workstation
    if ($step->templateStep->workstation) {
        WorkstationMonitor::markAvailable($step->templateStep->workstation_id);
    }
});
```

---

#### `StepProblemReported`
**Fired when:** An issue is reported for a step
**Location:** `App\Events\BatchStep\StepProblemReported`
**Data available:**
- `$event->batchStep` - The BatchStep model
- `$event->issue` - The reported Issue model

**Use cases:**
- Escalate critical issues
- Notify maintenance team
- Pause related production

---

### User Hooks

#### `UserAssignedToLine`
**Fired when:** A user is assigned to a production line
**Location:** `App\Events\User\UserAssignedToLine`
**Data available:**
- `$event->user` - The User model
- `$event->line` - The Line model

**Use cases:**
- Send welcome notifications
- Grant access to line-specific resources
- Update scheduling systems

**Example:**
```php
use App\Events\User\UserAssignedToLine;

Event::listen(UserAssignedToLine::class, function ($event) {
    // Send notification to user
    $event->user->notify(new AssignedToLineNotification($event->line));

    // Grant access to line documentation
    Documentation::grantAccess($event->user, $event->line);
});
```

---

#### `UserUnassignedFromLine`
**Fired when:** A user is removed from a production line
**Location:** `App\Events\User\UserUnassignedFromLine`
**Data available:**
- `$event->user` - The User model
- `$event->line` - The Line model

**Use cases:**
- Revoke access to line resources
- Update scheduling

---

### Line Hooks

#### `LineActivated`
**Fired when:** A production line is activated
**Location:** `App\Events\Line\LineActivated`
**Data available:**
- `$event->line` - The activated Line model

**Use cases:**
- Initialize line resources
- Notify operators
- Start monitoring systems

---

#### `LineDeactivated`
**Fired when:** A production line is deactivated
**Location:** `App\Events\Line\LineDeactivated`
**Data available:**
- `$event->line` - The deactivated Line model

**Use cases:**
- Stop monitoring
- Reassign operators
- Archive line data

---

### Process Template Hooks

#### `TemplateCreated`
**Fired when:** A new process template is created
**Location:** `App\Events\ProcessTemplate\TemplateCreated`
**Data available:**
- `$event->template` - The created ProcessTemplate model

**Use cases:**
- Validate against standards
- Generate documentation
- Notify quality team

---

#### `TemplateStepAdded`
**Fired when:** A step is added to a process template
**Location:** `App\Events\ProcessTemplate\TemplateStepAdded`
**Data available:**
- `$event->template` - The ProcessTemplate model
- `$event->step` - The added TemplateStep model

**Use cases:**
- Update time estimates
- Recalculate costs
- Validate step sequence

---

### CSV Import Hooks

#### `CsvImportStarted`
**Fired when:** A CSV import begins
**Location:** `App\Events\CsvImport\CsvImportStarted`
**Data available:**
- `$event->import` - The CsvImport model

**Use cases:**
- Notify administrators
- Lock related resources
- Start monitoring

---

#### `CsvImportCompleted`
**Fired when:** A CSV import finishes successfully
**Location:** `App\Events\CsvImport\CsvImportCompleted`
**Data available:**
- `$event->import` - The CsvImport model
- `$event->recordsImported` - Number of records imported

**Use cases:**
- Generate import report
- Trigger data validation
- Notify stakeholders

---

#### `CsvImportFailed`
**Fired when:** A CSV import fails
**Location:** `App\Events\CsvImport\CsvImportFailed`
**Data available:**
- `$event->import` - The CsvImport model
- `$event->error` - Error message

**Use cases:**
- Alert administrators
- Log errors
- Rollback changes

---

## Best Practices

### 1. Keep Listeners Focused
Each listener should do one thing well. If you need to perform multiple actions, create multiple listeners.

✅ **Good:**
```php
Event::listen(WorkOrderCompleted::class, SendCompletionEmail::class);
Event::listen(WorkOrderCompleted::class, UpdateInventory::class);
Event::listen(WorkOrderCompleted::class, NotifyWarehouse::class);
```

❌ **Bad:**
```php
Event::listen(WorkOrderCompleted::class, function ($event) {
    // Send email
    // Update inventory
    // Notify warehouse
    // Generate report
    // ... 100 lines of code
});
```

### 2. Use Queued Listeners for Heavy Operations
If your listener performs time-consuming operations, implement `ShouldQueue`:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateProductionReport implements ShouldQueue
{
    public function handle(WorkOrderCompleted $event): void
    {
        // Heavy operation that will run in background
        Report::generate($event->workOrder);
    }
}
```

### 3. Handle Errors Gracefully
Always wrap your listener logic in try-catch blocks:

```php
public function handle(WorkOrderCompleted $event): void
{
    try {
        ExternalAPI::sync($event->workOrder);
    } catch (\Exception $e) {
        \Log::error('Failed to sync work order', [
            'work_order_id' => $event->workOrder->id,
            'error' => $e->getMessage(),
        ]);
        // Don't throw - other listeners should still run
    }
}
```

### 4. Don't Modify Core Events
Never modify events in the `App\Events\` namespace. Instead, dispatch your own custom events from your listeners:

```php
Event::listen(WorkOrderCompleted::class, function ($event) {
    // Dispatch your own event
    event(new Modules\MyModule\Events\CustomWorkOrderProcessed($event->workOrder));
});
```

### 5. Document Your Modules
Always include a README.md in your module explaining:
- What it does
- Which hooks it uses
- Configuration options
- Installation instructions

### 6. Version Your Modules
Use semantic versioning (1.0.0, 1.1.0, 2.0.0) and document breaking changes.

### 7. Test Your Listeners
Write tests for your event listeners:

```php
public function test_work_order_completion_sends_email()
{
    Event::fake();

    $workOrder = WorkOrder::factory()->create();
    event(new WorkOrderCompleted($workOrder));

    Event::assertListened(WorkOrderCompleted::class);
}
```

---

## Complete Hook List

### Work Order
- `WorkOrderCreated` - New work order created
- `WorkOrderUpdated` - Work order updated
- `WorkOrderCompleted` - Work order completed
- `WorkOrderBlocked` - Work order blocked
- `WorkOrderUnblocked` - Work order unblocked

### Batch
- `BatchCreated` - New batch created
- `BatchCompleted` - Batch completed
- `BatchCancelled` - Batch cancelled

### Batch Step
- `StepStarted` - Step started
- `StepCompleted` - Step completed
- `StepProblemReported` - Problem reported

### User
- `UserAssignedToLine` - User assigned to line
- `UserUnassignedFromLine` - User removed from line
- `UserCreated` - New user created
- `UserUpdated` - User updated

### Line
- `LineCreated` - New line created
- `LineActivated` - Line activated
- `LineDeactivated` - Line deactivated
- `LineDeleted` - Line deleted

### Workstation
- `WorkstationCreated` - New workstation created
- `WorkstationActivated` - Workstation activated
- `WorkstationDeactivated` - Workstation deactivated

### Product Type
- `ProductTypeCreated` - New product type created
- `ProductTypeUpdated` - Product type updated

### Process Template
- `TemplateCreated` - New template created
- `TemplateActivated` - Template activated
- `TemplateStepAdded` - Step added to template
- `TemplateStepUpdated` - Step updated
- `TemplateStepDeleted` - Step removed

### CSV Import
- `CsvImportStarted` - Import started
- `CsvImportCompleted` - Import finished
- `CsvImportFailed` - Import failed

---

## Support

For questions or issues with the hook system:
- GitHub Issues: https://github.com/Mes-Open/OpenMes/issues
- Documentation: https://github.com/Mes-Open/OpenMes

## Contributing

Want to suggest a new hook? Open an issue with:
1. Hook name
2. When it should fire
3. What data it should provide
4. Use cases
