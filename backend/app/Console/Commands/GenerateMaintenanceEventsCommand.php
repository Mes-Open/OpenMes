<?php

namespace App\Console\Commands;

use App\Services\Maintenance\GenerateMaintenanceEvents;
use Illuminate\Console\Command;

class GenerateMaintenanceEventsCommand extends Command
{
    protected $signature = 'maintenance:generate-events';

    protected $description = 'Generate maintenance events for due schedules';

    public function handle(GenerateMaintenanceEvents $service): int
    {
        $count = $service->run();

        $this->info(sprintf('Generated %d maintenance event%s.', $count, $count === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
