<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Line;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Models\WorkOrder;
use App\Services\Quality\QualityTriggerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The outstanding-quality-controls queue, shared by supervisor and admin (#105).
 * Lists due controls (fed live by the quality_control_tasks_due shape) and lets
 * the user perform a control (records a QualityCheck) or skip it. Roaming
 * controls can be raised ad-hoc from a roaming trigger.
 */
class QualityControlTaskController extends Controller
{
    public function __construct(private QualityTriggerService $triggerService) {}

    public function index()
    {
        // Trigger metadata (name, blocking, and the parameters the operator must
        // record) so the queue can build the "perform" form per task.
        $triggers = QualityControlTrigger::with('template')->get()->mapWithKeys(fn ($t) => [
            $t->id => [
                'name' => $t->name,
                'trigger_type' => $t->trigger_type,
                'is_blocking' => $t->is_blocking,
                'parameters' => $t->template?->parameters ?? [],
                'samples_per_check' => $t->template?->samples_per_check ?? 1,
            ],
        ]);

        return Inertia::render('shared/quality-tasks/Index', [
            'triggers' => $triggers,
            'workOrderNos' => WorkOrder::pluck('order_no', 'id'),
            'lineNames' => Line::pluck('name', 'id'),
            'batchNumbers' => Batch::pluck('batch_number', 'id'),
            'roamingTriggers' => QualityControlTrigger::active()
                ->ofType(QualityControlTrigger::TYPE_ROAMING)
                ->get(['id', 'name']),
        ]);
    }

    public function perform(Request $request, QualityControlTask $task)
    {
        $validated = $request->validate([
            'production_quantity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'samples' => 'required|array|min:1',
            'samples.*.sample_number' => 'required|integer',
            'samples.*.parameter_name' => 'required|string',
            'samples.*.parameter_type' => 'required|in:measurement,pass_fail',
            'samples.*.value_numeric' => 'nullable|numeric',
            'samples.*.value_boolean' => 'nullable',
            'samples.*.is_passed' => 'nullable',
        ]);

        $samples = collect($validated['samples'])->map(fn ($s) => [
            'sample_number' => $s['sample_number'],
            'parameter_name' => $s['parameter_name'],
            'parameter_type' => $s['parameter_type'],
            'value_numeric' => $s['value_numeric'] ?? null,
            'value_boolean' => isset($s['value_boolean']) ? (bool) $s['value_boolean'] : null,
            'is_passed' => isset($s['is_passed']) ? (bool) $s['is_passed'] : null,
        ])->toArray();

        try {
            $task = $this->triggerService->performTask(
                $task,
                $request->user(),
                $samples,
                $validated['production_quantity'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            $task->qualityCheck?->all_passed ? 'success' : 'warning',
            $task->qualityCheck?->all_passed
                ? __('Quality control passed.')
                : __('Quality control recorded — some samples failed; a non-conformance was raised.'),
        );
    }

    public function skip(QualityControlTask $task)
    {
        try {
            $this->triggerService->skipTask($task, request()->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Quality control skipped.'));
    }

    public function storeRoaming(Request $request)
    {
        $validated = $request->validate([
            'quality_control_trigger_id' => 'required|integer|exists:quality_control_triggers,id',
            'line_id' => 'nullable|integer|exists:lines,id',
            'workstation_id' => 'nullable|integer|exists:workstations,id',
            'work_order_id' => 'nullable|integer|exists:work_orders,id',
            'batch_id' => 'nullable|integer|exists:batches,id',
        ]);

        $trigger = QualityControlTrigger::findOrFail($validated['quality_control_trigger_id']);

        if ($trigger->trigger_type !== QualityControlTrigger::TYPE_ROAMING) {
            return back()->with('error', __('Only roaming triggers can be raised manually.'));
        }

        $this->triggerService->createRoamingTask($trigger, [
            'line_id' => $validated['line_id'] ?? null,
            'workstation_id' => $validated['workstation_id'] ?? null,
            'work_order_id' => $validated['work_order_id'] ?? null,
            'batch_id' => $validated['batch_id'] ?? null,
        ]);

        return back()->with('success', __('Roaming quality control raised.'));
    }
}
