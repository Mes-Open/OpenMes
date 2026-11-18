<?php

namespace App\Http\Controllers\Web\Admin\Schedule;

use App\Enums\ScheduledOperationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\GenerateScheduleRequest;
use App\Http\Requests\Schedule\OverrideScheduledOperationRequest;
use App\Http\Requests\Schedule\ScheduleSimulationActionRequest;
use App\Models\ScheduledOperation;
use App\Models\ScheduleSimulation;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Schedule\Finite\SchedulePresenter;
use App\Services\Schedule\Finite\ScheduleSimulationService;
use App\Services\Schedule\Finite\SchedulingRequest;
use App\Support\TabRegistry;
use Inertia\Inertia;

/**
 * Planner page for finite-capacity scheduling: generate a what-if plan,
 * review its loads and its late or infeasible operations, then apply or
 * discard it. Every write navigates back here, so outcomes are flash messages.
 * Manual drag & drop on the planner board is untouched by any of this.
 */
class AutoScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleSimulationService $simulations,
        private readonly SchedulePresenter $presenter,
    ) {}

    public function index()
    {
        $draft = $this->simulations->currentDraft();

        $committed = ScheduledOperation::committed()
            ->with(SchedulePresenter::RELATIONS)
            ->whereHas('workOrder', fn ($q) => $q->whereIn('status', WorkOrder::ACTIVE_STATUSES))
            ->where(fn ($q) => $q->whereNull('planned_end_at')->orWhere('planned_end_at', '>=', now()->subDay()))
            ->orderByRaw('planned_start_at is null')
            ->orderBy('planned_start_at')
            ->orderBy('sequence')
            ->limit(300)
            ->get();

        return Inertia::render('admin/schedule/AutoSchedule', [
            'draft' => $draft ? $this->presenter->simulation($draft) : null,
            // Checked on load, so the planner is warned before clicking Apply.
            'draftStale' => $draft ? $this->simulations->staleReason($draft) : null,
            'committed' => $committed->map(fn ($op) => $this->presenter->operation($op))->values(),
            'stationsByType' => Workstation::with('line:id,name')
                ->where('is_active', true)
                ->whereNotNull('workstation_type_id')
                ->orderBy('name')
                ->get()
                ->groupBy('workstation_type_id')
                ->map(fn ($stations) => $stations->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'code' => $w->code, 'line_name' => $w->line?->name])->values()),
            'defaults' => [
                'start' => now()->format('Y-m-d\TH:i'),
                'horizon_days' => GenerateScheduleRequest::DEFAULT_HORIZON_DAYS,
                'max_horizon_days' => GenerateScheduleRequest::MAX_HORIZON_DAYS,
                'scope' => SchedulingRequest::SCOPE_UNPLANNED,
                'respect_material' => true,
                'respect_workforce' => true,
            ],
            'statusLabels' => collect(ScheduledOperationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
            'canDecide' => (bool) request()->user()?->hasAnyRole(['Admin', 'Supervisor']),
            // UX only: whether the "Assign operators" link on an untracked line
            // leads somewhere this user may open. The lines pages enforce it.
            'canAssignOperators' => (bool) request()->user()?->can(TabRegistry::permission(TabRegistry::tabForPath('admin/lines') ?? 'lines')),
        ]);
    }

    public function generate(GenerateScheduleRequest $request)
    {
        $simulation = $this->simulations->generate($request->toSchedulingRequest(), $request->user());
        $counts = $simulation->summary['counts'] ?? [];
        $placed = ($counts['scheduled'] ?? 0) + ($counts['scheduled_late'] ?? 0);
        $total = array_sum($counts);

        return redirect()->route('admin.schedule.auto')->with('success', __('Schedule simulated: :placed of :total operations placed. Nothing changes until you apply it.', [
            'placed' => $placed,
            'total' => $total,
        ]));
    }

    public function apply(ScheduleSimulationActionRequest $request, ScheduleSimulation $simulation)
    {
        try {
            $result = $this->simulations->apply($simulation, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('admin.schedule.auto')->with('error', $e->getMessage());
        } catch (\Illuminate\Validation\ValidationException $e) {
            // The planner refused one of the moves, so nothing was applied. The
            // page has no field to hang the error on — say it in the flash.
            return redirect()->route('admin.schedule.auto')->with('error', collect($e->errors())->flatten()->first());
        }

        $message = __('Schedule applied to :applied work orders.', ['applied' => $result['applied']]);
        if ($result['skipped'] > 0) {
            $message .= ' '.__(':count work orders could not be fully scheduled and kept their placement.', ['count' => $result['skipped']]);
        }
        if ($result['warnings'] !== []) {
            $message .= ' '.implode(' ', $result['warnings']);
        }

        return redirect()->route('admin.schedule.auto')->with('success', $message);
    }

    public function discard(ScheduleSimulationActionRequest $request, ScheduleSimulation $simulation)
    {
        try {
            $this->simulations->discard($simulation);
        } catch (\DomainException $e) {
            return redirect()->route('admin.schedule.auto')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.schedule.auto')->with('success', __('Simulated schedule discarded.'));
    }

    public function override(OverrideScheduledOperationRequest $request, ScheduledOperation $operation)
    {
        $this->simulations->overrideWorkstation($operation, $request->station(), $request->user());

        return redirect()->route('admin.schedule.auto')->with('success', __('Operation moved to :station.', ['station' => $request->station()->name]));
    }
}
