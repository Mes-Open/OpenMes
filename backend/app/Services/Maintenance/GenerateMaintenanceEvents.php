<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;

class GenerateMaintenanceEvents
{
    /**
     * Generate maintenance events for all active schedules that are due
     * (taking lead_time_days into account). Idempotent per cycle: a second
     * call for the same (schedule_id, scheduled_at) pair is a no-op.
     *
     * @return int Number of events created.
     */
    public function run(): int
    {
        $created = 0;

        MaintenanceSchedule::query()
            ->where('is_active', true)
            // Generate if due within the next 7 days (covers lead_time_days up to 7).
            ->where('next_due_at', '<=', now()->addDays(7))
            ->orderBy('next_due_at')
            ->get()
            ->each(function (MaintenanceSchedule $schedule) use (&$created) {
                if (! $schedule->isDue()) {
                    return; // not yet within lead_time window
                }

                // Race-safe duplicate guard for the current cycle.
                $exists = MaintenanceEvent::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('scheduled_at', $schedule->next_due_at)
                    ->exists();

                if ($exists) {
                    return;
                }

                MaintenanceEvent::create([
                    'title'          => $schedule->name,
                    'description'    => $schedule->description,
                    'event_type'     => $schedule->event_type,
                    'status'         => MaintenanceEvent::STATUS_PENDING,
                    'tool_id'        => $schedule->tool_id,
                    'line_id'        => $schedule->line_id,
                    'workstation_id' => $schedule->workstation_id,
                    'assigned_to_id' => $schedule->assigned_to_id,
                    'cost_source_id' => $schedule->cost_source_id,
                    'scheduled_at'   => $schedule->next_due_at,
                    'schedule_id'    => $schedule->id,
                ]);

                $schedule->advance();
                $created++;
            });

        return $created;
    }
}
