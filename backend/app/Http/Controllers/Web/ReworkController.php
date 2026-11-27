<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rework\CancelReworkOperationRequest;
use App\Http\Requests\Rework\CompleteReworkOperationRequest;
use App\Http\Requests\Rework\ReworkActionRequest;
use App\Http\Requests\Rework\StoreReworkOperationRequest;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\ReworkOperation;
use App\Models\Workstation;
use App\Services\Production\StepTimeService;
use App\Services\Quality\ReworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Rework routing (#400) on the web: the Issues page sends and cancels rework
 * (JSON, the page stays put), the operator panel starts, times and completes
 * it (redirect back with a flash, like the panel's other actions).
 */
class ReworkController extends Controller
{
    public function __construct(
        private ReworkService $rework,
        private StepTimeService $time,
    ) {}

    /** The issue's rework so far, and the steps its pieces could go to. */
    public function forIssue(Request $request, Issue $issue): JsonResponse
    {
        if ($issue->workOrder) {
            $this->authorize('view', $issue->workOrder);
        }

        return response()->json($this->issuePayload($issue));
    }

    public function storeForIssue(StoreReworkOperationRequest $request, Issue $issue): JsonResponse
    {
        try {
            $this->rework->create($issue, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['rework' => [$e->getMessage()]]], 422);
        }

        return response()->json($this->issuePayload($issue->fresh()), 201);
    }

    public function cancel(CancelReworkOperationRequest $request, ReworkOperation $rework): JsonResponse
    {
        try {
            $this->rework->cancel($rework, $request->validated('reason'), $request->user());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['rework' => [$e->getMessage()]]], 422);
        }

        return response()->json($rework->issue ? $this->issuePayload($rework->issue) : ['reworks' => []]);
    }

    // ── Operator panel ──────────────────────────────────────────────────

    public function start(ReworkActionRequest $request, ReworkOperation $rework): RedirectResponse
    {
        return $this->operator($request, $rework, function () use ($rework, $request) {
            $this->rework->start($rework, $request->user());

            return __('Rework started.');
        });
    }

    public function complete(CompleteReworkOperationRequest $request, ReworkOperation $rework): RedirectResponse
    {
        $v = $request->validated();

        return $this->operator($request, $rework, function () use ($rework, $request, $v) {
            $this->rework->complete(
                $rework,
                (float) $v['accepted_qty'],
                (float) $v['scrapped_qty'],
                $request->user(),
                isset($v['scrap_reason_id']) ? (int) $v['scrap_reason_id'] : null,
                $v['notes'] ?? null,
            );

            return __('Rework completed.');
        });
    }

    public function time(ReworkActionRequest $request, ReworkOperation $rework, string $kind, string $action): RedirectResponse
    {
        return $this->operator($request, $rework, function () use ($rework, $request, $kind, $action) {
            $step = $rework->targetStep;
            $user = $request->user();

            if ($kind === 'labor') {
                $action === 'start'
                    ? $this->time->startLabor($step, $user, rework: $rework)
                    : $this->time->stopLabor($step, $user, rework: $rework);

                return $action === 'start' ? __('Clocked on.') : __('Clocked off.');
            }

            $workstation = $step->workstation ?? Workstation::find($request->session()->get('selected_workstation_id'));
            $action === 'start'
                ? $this->time->startMachine($step, $workstation, $user, rework: $rework)
                : $this->time->stopMachine($step, null, $user, rework: $rework);

            return $action === 'start' ? __('Machine time started.') : __('Machine time stopped.');
        });
    }

    /** @param  callable(): string  $fn  returns the success message */
    private function operator(Request $request, ReworkOperation $rework, callable $fn): RedirectResponse
    {
        $lineId = $request->session()->get('selected_line_id');
        if (! $lineId || (int) $rework->workOrder?->line_id !== (int) $lineId) {
            return back()->with('error', __('This step does not belong to the selected line.'));
        }

        try {
            return back()->with('success', $fn());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** @return array{reworks: list<array>, steps: list<array>} */
    private function issuePayload(Issue $issue): array
    {
        $reworks = $issue->reworkOperations()->with(['sourceStep', 'targetStep'])->orderBy('id')->get();

        $batches = $issue->work_order_id
            ? Batch::where('work_order_id', $issue->work_order_id)
                ->whereNotIn('status', [Batch::STATUS_DONE, Batch::STATUS_CANCELLED])
                ->with('steps')
                ->orderBy('batch_number')
                ->get()
            : collect();

        $steps = $batches->flatMap(fn (Batch $b) => $b->steps->sortBy('step_number')->map(fn (BatchStep $s) => [
            'id' => $s->id,
            'batch_id' => $b->id,
            'batch_number' => $b->batch_number,
            'step_number' => $s->step_number,
            'name' => $s->name,
            'status' => $s->status,
            'available_qty' => $s->availableQty(),
            'rework_held_qty' => (float) $s->rework_held_qty,
        ]))->values()->all();

        return [
            'reworks' => $reworks->map(fn (ReworkOperation $r) => ReworkService::present($r))->values()->all(),
            'steps' => $steps,
        ];
    }
}
