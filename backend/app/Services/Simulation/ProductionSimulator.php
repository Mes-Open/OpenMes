<?php

namespace App\Services\Simulation;

use App\Models\Workstation;
use App\Models\WorkOrder;
use App\Models\CycleTimeLog;

class ProductionSimulator
{
    /**
     * Run a What-If scenario on a specific production flow.
     */
    public function runScenario(array $params): array
    {
        // 1. Get baseline performance data
        $workstations = Workstation::whereIn('id', $params['workstation_ids'])->get();

        $results = [];
        foreach ($workstations as $ws) {
            $avgCycleTime = CycleTimeLog::where('workstation_id', $ws->id)
                ->where('completed_at', '>=', now()->subDays(30))
                ->avg('cycle_time_secs') ?? $ws->ideal_cycle_time_secs;

            // 2. Simulate new parameters
            $simulatedCycleTime = $avgCycleTime * ($params['efficiency_multiplier'] ?? 1.0);
            $simulatedDowntimeProb = ($params['downtime_probability'] ?? 0.05);

            // 3. Simple Discrete Event Simulation logic
            $totalOutput = 0;
            $simulationTimeHours = $params['simulation_hours'] ?? 8;
            $currentTimeSecs = 0;

            while ($currentTimeSecs < ($simulationTimeHours * 3600)) {
                if (rand(0, 100) / 100 > $simulatedDowntimeProb) {
                    $currentTimeSecs += $simulatedCycleTime;
                    $totalOutput++;
                } else {
                    $currentTimeSecs += 600; // 10 min average stop
                }
            }

            $results[$ws->name] = [
                'estimated_throughput' => $totalOutput,
                'avg_bottleneck_pct' => ($totalOutput * $simulatedCycleTime) / ($simulationTimeHours * 3600) * 100,
            ];
        }

        return $results;
    }

    /**
     * Virtual production flow (Digital Twin visualization state).
     */
    public function getDigitalTwinState(Workstation $workstation): array
    {
        return [
            'id' => $workstation->id,
            'name' => $workstation->name,
            'current_state' => $workstation->state,
            'last_heartbeat' => $workstation->last_heartbeat_at,
            'live_telemetry' => $workstation->currentState()?->metadata['telemetry'] ?? [],
            'bottleneck_score' => rand(0, 100) / 100, // Probability of being the bottleneck
        ];
    }
}
