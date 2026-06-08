<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Umbrella seeder for the demo / screenshot dataset. Runs the three
 * focused seeders in dependency order so a single command populates
 * everything the mobile UI expects.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Idempotent — each child seeder upserts on stable keys, so re-running
 * is safe. NOT wired into the main DatabaseSeeder (production deploys
 * should never see this data); run it manually after install.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AirFilterDemoSeeder::class,
            HrDemoSeeder::class,
            OeeAndDowntimeDemoSeeder::class,
        ]);
    }
}
