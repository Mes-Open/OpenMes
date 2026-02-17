# OpenMES Modules

This directory is for custom modules that extend OpenMES functionality.

## Creating a Module

1. Create a new directory: `modules/YourModuleName/`
2. Create a service provider: `Providers/YourModuleServiceProvider.php`
3. Register your event listeners in the service provider
4. Add your provider to `config/app.php` or use auto-discovery

## Example Module Structure

```
modules/
└── NotificationModule/
    ├── Providers/
    │   └── NotificationModuleServiceProvider.php
    ├── Listeners/
    │   ├── SendWorkOrderEmail.php
    │   └── SendBatchCompletedSMS.php
    ├── config/
    │   └── notifications.php
    ├── module.json
    └── README.md
```

## Available Hooks

See [HOOKS.md](../../HOOKS.md) for a complete list of available hooks and events.

## Best Practices

1. **Keep modules independent** - Don't depend on other modules
2. **Use semantic versioning** - Version your module (1.0.0, 1.1.0, etc.)
3. **Document everything** - Include a README.md
4. **Test your code** - Write tests for your listeners
5. **Handle errors gracefully** - Use try-catch blocks
6. **Use queues for heavy operations** - Implement ShouldQueue

## Example: Simple Notification Module

```php
// modules/NotificationModule/Providers/NotificationModuleServiceProvider.php
<?php

namespace Modules\NotificationModule\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\WorkOrder\WorkOrderCompleted;
use Modules\NotificationModule\Listeners\SendCompletionEmail;

class NotificationModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register event listener
        Event::listen(
            WorkOrderCompleted::class,
            SendCompletionEmail::class
        );

        // Load config
        $this->publishes([
            __DIR__.'/../config/notifications.php' => config_path('notifications.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/notifications.php',
            'notifications'
        );
    }
}
```

```php
// modules/NotificationModule/Listeners/SendCompletionEmail.php
<?php

namespace Modules\NotificationModule\Listeners;

use App\Events\WorkOrder\WorkOrderCompleted;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCompletionEmail implements ShouldQueue
{
    public function handle(WorkOrderCompleted $event): void
    {
        $workOrder = $event->workOrder;

        Mail::to(config('notifications.email'))
            ->send(new \App\Mail\WorkOrderCompletedMail($workOrder));
    }
}
```

## Installing a Module

1. Copy module to `modules/` directory
2. Add service provider to `config/app.php`:

```php
'providers' => [
    // ...
    Modules\YourModule\Providers\YourModuleServiceProvider::class,
],
```

3. Run migrations if needed:

```bash
php artisan migrate
```

4. Clear cache:

```bash
php artisan config:clear
php artisan cache:clear
```

## Sharing Your Module

Want to share your module with the community?

1. Create a GitHub repository
2. Add installation instructions
3. Submit to the OpenMES modules directory (coming soon!)

## Support

For help creating modules:
- See [HOOKS.md](../../HOOKS.md) for available hooks
- Check GitHub issues
- Join our community discussions
