<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tenants:prune')->everyMinute();
// Demo-only: roll OEE/production forward to today so the report never shows N/A
// on a long-running demo. No-op unless DEMO_MODE=true (the command self-guards).
// Runs before oee:calculate so today's production exists first.
Schedule::command('demo:refresh-oee')->dailyAt('00:30');
Schedule::command('oee:calculate')->dailyAt('01:00');
Schedule::command('maintenance:generate-events')->hourly();
Schedule::command('quality:fire-due-triggers')->everyMinute()->withoutOverlapping();
Schedule::command('quality:notify-overdue-actions')->dailyAt('07:00');
// Keep time-based priority rules (hours-until-due) fresh as due dates approach.
Schedule::command('priority:recalculate')->hourly()->withoutOverlapping();
