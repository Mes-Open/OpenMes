<?php

namespace App\Services\Quality;

use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\InspectionResult;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InboundInspectionService
{
    /**
     * Start a new inspection. If a plan is provided, its criteria are
     * snapshot-copied into inspection_results so later edits to the plan
     * cannot retroactively alter past inspections.
     */
    public function start(
        Material $material,
        string $lotNumber,
        ?float $quantity,
        ?InspectionPlan $plan,
        User $inspector,
        ?string $supplierLotRef = null,
    ): Inspection {
        return DB::transaction(function () use ($material, $lotNumber, $quantity, $plan, $inspector, $supplierLotRef) {
            $inspection = Inspection::create([
                'inspection_plan_id' => $plan?->id,
                'material_id' => $material->id,
                'lot_number' => $lotNumber,
                'supplier_lot_ref' => $supplierLotRef,
                'quantity_received' => $quantity,
                'inspector_id' => $inspector->id,
                'started_at' => now(),
                'status' => Inspection::STATUS_PENDING,
            ]);

            if ($plan) {
                foreach ($plan->criteria as $criterion) {
                    InspectionResult::create([
                        'inspection_id' => $inspection->id,
                        'criterion_name' => $criterion['name'] ?? 'Unnamed',
                        'criterion_type' => $criterion['type'] ?? InspectionResult::TYPE_PASS_FAIL,
                        'required' => $criterion['required'] ?? true,
                        'unit' => $criterion['unit'] ?? null,
                        'spec_min' => $criterion['spec_min'] ?? null,
                        'spec_max' => $criterion['spec_max'] ?? null,
                    ]);
                }
            }

            return $inspection->fresh('results');
        });
    }

    /**
     * Record / update a single criterion's result. Pass/fail is auto-evaluated
     * by InspectionResult::evaluate() based on the criterion type and spec.
     */
    public function recordResult(InspectionResult $result, array $data): InspectionResult
    {
        $result->fill([
            'value_numeric' => $data['value_numeric'] ?? $result->value_numeric,
            'value_boolean' => $data['value_boolean'] ?? $result->value_boolean,
            'value_text' => $data['value_text'] ?? $result->value_text,
            'notes' => $data['notes'] ?? $result->notes,
        ]);

        $result->is_passed = $result->evaluate();
        $result->save();

        return $result;
    }

    /**
     * Mark the inspection complete, compute its overall result, and create a
     * non-conformance Issue if it failed. Idempotent — calling again on a
     * completed inspection throws to avoid silently reopening it.
     */
    public function complete(Inspection $inspection, ?string $notes = null): Inspection
    {
        if (! $inspection->isPending()) {
            throw new RuntimeException('Inspection #' . $inspection->id . ' is already completed (status: ' . $inspection->status . ').');
        }

        $inspection->loadMissing('results', 'material', 'inspector');

        if ($inspection->results->isEmpty()) {
            throw new InvalidArgumentException('Cannot complete inspection without any recorded criteria.');
        }

        $overall = $this->evaluateOverall($inspection);

        return DB::transaction(function () use ($inspection, $overall, $notes) {
            $inspection->update([
                'status' => $overall,
                'completed_at' => now(),
                'notes' => $notes ?? $inspection->notes,
            ]);

            if ($overall === Inspection::STATUS_FAIL) {
                $issue = $this->createNonConformance($inspection);
                $inspection->update(['issue_id' => $issue->id]);
            }

            // Create a MaterialLot for the inspected qty so production can
            // pick from it via FEFO/FIFO. Quarantined on fail, available on
            // pass / conditional. Opt-in via lot_tracking_enabled setting.
            $this->ensureLotFromInspection($inspection, $overall);

            return $inspection->fresh('results', 'issue');
        });
    }

    private function ensureLotFromInspection(Inspection $inspection, string $overall): void
    {
        try {
            $enabled = (bool) json_decode(
                \DB::table('system_settings')->where('key', 'lot_tracking_enabled')->value('value') ?? 'false',
                true,
            );
        } catch (\Throwable) {
            $enabled = false;
        }

        if (! $enabled) {
            return;
        }

        // Avoid duplicating a lot if the inspection completes twice for any reason.
        if (\App\Models\MaterialLot::where('inspection_id', $inspection->id)->exists()) {
            return;
        }

        \App\Models\MaterialLot::create([
            'tenant_id' => $inspection->tenant_id,
            'material_id' => $inspection->material_id,
            'lot_number' => $inspection->lot_number,
            'supplier_lot_ref' => $inspection->supplier_lot_ref,
            'received_qty' => $inspection->quantity_received ?? 0,
            'available_qty' => $overall === Inspection::STATUS_FAIL ? 0 : ($inspection->quantity_received ?? 0),
            'received_at' => $inspection->started_at ?? now(),
            'inspection_id' => $inspection->id,
            'status' => $overall === Inspection::STATUS_FAIL
                ? \App\Models\MaterialLot::STATUS_QUARANTINED
                : \App\Models\MaterialLot::STATUS_AVAILABLE,
        ]);
    }

    /**
     * Overall result:
     *   - fail: any required criterion failed, OR any non-null criterion failed
     *           and there are no required criteria defined.
     *   - conditional_pass: all required passed but at least one optional failed.
     *   - pass: everything passed (and no nulls among required).
     *   - fail (degraded): at least one required has null result.
     */
    private function evaluateOverall(Inspection $inspection): string
    {
        $required = $inspection->results->where('required', true);
        $optional = $inspection->results->where('required', false);

        // Any required result still null or explicitly failed → fail.
        if ($required->isNotEmpty()) {
            if ($required->contains(fn ($r) => $r->is_passed === null)) {
                return Inspection::STATUS_FAIL;
            }
            if ($required->contains(fn ($r) => $r->is_passed === false)) {
                return Inspection::STATUS_FAIL;
            }
        } else {
            // No required criteria defined — if any criterion explicitly failed, fail.
            if ($inspection->results->contains(fn ($r) => $r->is_passed === false)) {
                return Inspection::STATUS_FAIL;
            }
        }

        // All required passed; check if any optional failed.
        if ($optional->contains(fn ($r) => $r->is_passed === false)) {
            return Inspection::STATUS_CONDITIONAL;
        }

        return Inspection::STATUS_PASS;
    }

    private function createNonConformance(Inspection $inspection): Issue
    {
        $issueType = IssueType::firstWhere('code', 'INBOUND_QC_FAIL')
            ?? IssueType::firstWhere('code', 'MATERIAL_DEFECT')
            ?? IssueType::firstWhere('code', 'OTHER');

        if (! $issueType) {
            throw new RuntimeException('No suitable IssueType found — run IssueTypesSeeder.');
        }

        $failed = $inspection->results->filter(fn ($r) => $r->is_passed === false);
        $description = "Inbound inspection failed for material '{$inspection->material->name}' lot '{$inspection->lot_number}'.\n\nFailed criteria:\n"
            . $failed->map(function ($r) {
                $value = $r->value_numeric ?? ($r->value_boolean !== null ? ($r->value_boolean ? 'pass' : 'fail') : ($r->value_text ?? '—'));
                $spec = $r->spec_min !== null && $r->spec_max !== null
                    ? " (spec: {$r->spec_min}–{$r->spec_max} {$r->unit})"
                    : '';

                return "  • {$r->criterion_name}: {$value}{$spec}";
            })->implode("\n");

        return Issue::create([
            'issue_type_id' => $issueType->id,
            'material_id' => $inspection->material_id,
            'source' => Issue::SOURCE_INBOUND_INSPECTION,
            'work_order_id' => null,
            'title' => sprintf('Inbound QC fail: %s lot %s', $inspection->material->name, $inspection->lot_number),
            'description' => $description,
            'status' => Issue::STATUS_OPEN,
            'reported_by_id' => $inspection->inspector_id,
            'reported_at' => now(),
        ]);
    }
}
