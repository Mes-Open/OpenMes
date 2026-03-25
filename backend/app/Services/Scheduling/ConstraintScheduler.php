<?php

namespace App\Services\Scheduling;

use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\Tool;
use App\Models\User;

class ConstraintScheduler
{
    /**
     * Check if a Work Order can be scheduled based on industrial constraints.
     */
    public function canSchedule(WorkOrder $wo): array
    {
        $reasons = [];

        // 1. Machine Constraint
        $ws = $wo->line->workstations()->first();
        if ($ws->state === 'FAULT' || $ws->state === 'MAINTENANCE') {
            $reasons[] = "Workstation {$ws->name} is in {$ws->state} state.";
        }

        // 2. Tooling Constraint
        $requiredTools = Tool::where('workstation_type_id', $ws->workstation_type_id)->get();
        foreach ($requiredTools as $tool) {
            if ($tool->status !== 'available' || $tool->wear_percentage > 90) {
                $reasons[] = "Tool {$tool->name} is {$tool->status} or worn (90%+).";
            }
        }

        // 3. Operator Skill Constraint
        $operators = $wo->line->users;
        $hasSkilledOperator = false;
        foreach ($operators as $op) {
            if ($op->worker && $op->worker->skills->isNotEmpty()) {
                $hasSkilledOperator = true;
                break;
            }
        }

        if (!$hasSkilledOperator) {
            $reasons[] = "No skilled operator assigned to Line {$wo->line->name}.";
        }

        return [
            'schedulable' => empty($reasons),
            'reasons' => $reasons,
        ];
    }
}
