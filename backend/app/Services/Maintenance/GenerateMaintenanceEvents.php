<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class GenerateMaintenanceEvents
{
    /**
     * Generate maintenance events for all active schedules that are due
     * (taking lead_time_days into account). Idempotent per cycle: a second
     * call for the same (schedule_id, scheduled_at) pair is a no-op.
     *
     * Idempotency is enforced at two layers:
     *  1. Application: the `exists()` guard below is the first line of defence
     *     and short-circuits the create() in the common case (single worker,
     *     re-running the scheduler).
     *  2. Database: a composite UNIQUE(schedule_id, scheduled_at) index added
     *     in migration 2026_05_21_150000 closes the narrow race between two
     *     concurrent workers that both pass the exists() check before either
     *     has committed its insert. The QueryException catch below swallows
     *     that race and continues — the peer worker already created the row.
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

                // First line of defence: application-level duplicate guard.
                $exists = MaintenanceEvent::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('scheduled_at', $schedule->next_due_at)
                    ->exists();

                if ($exists) {
                    return;
                }

                try {
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
                } catch (QueryException $e) {
                    // Race with a peer worker: it won the insert between our exists()
                    // check and our create(). The DB-level unique constraint rejected
                    // our duplicate. Log and continue — the event already exists.
                    Log::warning('Maintenance event generation lost insert race', [
                        'schedule_id'  => $schedule->id,
                        'scheduled_at' => optional($schedule->next_due_at)->toIso8601String(),
                        'sql_state'    => $e->getCode(),
                    ]);

                    return;
                }

                $schedule->advance();
                $created++;
            });

        return $created;
    }
}
