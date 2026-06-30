<?php

namespace App\Console\Commands;

use App\Services\Quality\QualityTriggerService;
use Illuminate\Console\Command;

/**
 * Fires time-based quality-control triggers (every_n_minutes). Scheduled every
 * minute in routes/console.php; the service decides which in-progress batches
 * are due. Other trigger types fire synchronously from the production
 * lifecycle, so they don't need the scheduler.
 */
class FireDueQualityTriggersCommand extends Command
{
    protected $signature = 'quality:fire-due-triggers';

    protected $description = 'Create quality-control tasks for due time-based triggers';

    public function handle(QualityTriggerService $service): int
    {
        $created = $service->fireDueTimeTriggers();

        $this->info("Quality-control triggers fired: {$created} task(s) created.");

        return self::SUCCESS;
    }
}
