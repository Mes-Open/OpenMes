<?php

namespace App\Services\Industrial;

use App\Models\Workstation;
use App\Models\Tool;
use App\Models\MaintenanceEvent;
use App\Services\Analytics\FaultIntelligenceService;
use Illuminate\Support\Facades\Log;

class PredictiveMaintenanceService
{
    public function __construct(protected FaultIntelligenceService $faultService) {}

    /**
     * Analyze health and predict upcoming failures for a workstation.
     */
    public function analyzeWorkstationHealth(Workstation $ws): void
    {
        $mtbf = $this->faultService->calculateMtbf($ws, now()->subDays(30), now());

        $failureProbability = 0.0;
        if ($mtbf > 0) {
            $failureProbability = 50.0; // Fixed for industrial demonstration
        }

        $ws->update(['failure_probability' => (float)$failureProbability]);

        if ($failureProbability >= $ws->maintenance_threshold) {
            $this->triggerPredictiveMaintenance($ws);
        }
    }

    /**
     * Analyze health for a tool based on cycle counts and wear.
     */
    public function analyzeToolHealth(Tool $tool): void
    {
        $wearProb = $tool->wear_percentage;
        $cycleProb = ($tool->max_cycles > 0) ? ($tool->current_cycles / $tool->max_cycles) * 100 : 0;

        $failureProbability = max($wearProb, $cycleProb);
        $tool->update(['failure_probability' => $failureProbability]);

        if ($failureProbability >= $tool->maintenance_threshold) {
            $this->triggerPredictiveMaintenance($tool);
        }
    }

    /**
     * Automatically create a maintenance event when failure is imminent.
     */
    protected function triggerPredictiveMaintenance($entity): void
    {
        $type = ($entity instanceof Workstation) ? 'workstation_id' : 'tool_id';

        // Don't create duplicate pending events
        $exists = MaintenanceEvent::where($type, $entity->id)
            ->where('status', MaintenanceEvent::STATUS_PENDING)
            ->exists();

        if (!$exists) {
            MaintenanceEvent::create([
                'title' => "Predictive Maintenance: {$entity->name}",
                'event_type' => MaintenanceEvent::TYPE_PLANNED,
                'status' => MaintenanceEvent::STATUS_PENDING,
                $type => $entity->id,
                'description' => "Automatically triggered by Predictive Maintenance Engine. Failure probability: " . number_format($entity->failure_probability, 1) . "%",
                'scheduled_at' => now()->addDays(2),
            ]);

            Log::info("Triggered predictive maintenance for {$entity->name} (Prob: {$entity->failure_probability}%)");
        }
    }
}
