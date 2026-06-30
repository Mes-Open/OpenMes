<?php

namespace App\Console\Commands;

use Database\Seeders\OeeAndDowntimeDemoSeeder;
use Illuminate\Console\Command;

/**
 * Keeps a long-running demo's OEE report alive. The OEE page recomputes
 * today/yesterday from real DONE production, so once the demo's seeded
 * "today" ages, OEE decays into N/A. This re-runs the (fully idempotent)
 * demo seeder, which rolls production + OEE forward to the current day.
 *
 * Guarded by config('openmmes.demo_mode') so it never touches a real install.
 */
class RefreshDemoOeeCommand extends Command
{
    protected $signature = 'demo:refresh-oee
                            {--force : Run even when demo mode is disabled}';

    protected $description = 'Roll demo OEE/production data forward to today so the report never shows N/A';

    public function handle(): int
    {
        if (! config('openmmes.demo_mode') && ! $this->option('force')) {
            $this->warn('Demo mode is off (openmmes.demo_mode) — skipping. Pass --force to run anyway.');

            return self::SUCCESS;
        }

        $this->info('Refreshing demo OEE/production data for today…');

        // The seeder is idempotent — updateOrCreate for OEE/downtime rows and a
        // skip for lines that already have DONE batches today — so a daily run
        // only rolls today/yesterday forward and never duplicates history.
        $this->callSilent('db:seed', [
            '--class' => OeeAndDowntimeDemoSeeder::class,
            '--force' => true,
        ]);

        $this->info('Demo OEE refreshed.');

        return self::SUCCESS;
    }
}
