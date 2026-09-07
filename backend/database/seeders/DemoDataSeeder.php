<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Umbrella seeder for the demo / screenshot dataset. Runs the focused
 * seeders in dependency order so a single command populates everything
 * the mobile UI expects.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Idempotent — each child seeder upserts on stable keys, so re-running
 * is safe. NOT wired into the main DatabaseSeeder (production deploys
 * should never see this data); run it manually after install.
 *
 * The shift monitor runs last: it fills whichever shift is in progress
 * right now, so it needs the lines, stations and shifts the others
 * create. Re-run it alone whenever the demo needs a fresh live shift.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AirFilterDemoSeeder::class,
            HrDemoSeeder::class,
            OeeAndDowntimeDemoSeeder::class,
            ShiftMonitorDemoSeeder::class,
        ]);
    }
}
