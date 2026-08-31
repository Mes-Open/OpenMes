<?php

namespace App\Services\Schedule;

use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\ProcessTemplate;
use App\Models\ScheduleChangeLog;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\WorkOrder\WorkOrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The planner's shared brain: board assembly, placement writes, audit log and
 * undo. Both the Inertia web planner (Web\Admin\SchedulePlannerController) and
 * the mobile API (Api\V1\ScheduleController) call this, so the two surfaces
 * can't drift apart again — before this existed the API mirror silently lacked
 * extra-placement sync and audit logging, which made mobile edits invisible to
 * the Changes tab and impossible to undo.
 */
class SchedulePlannerService
{
    public const VIEW_MODES = ['weekly', 'daily', 'hourly', 'monthly'];

    /**
     * Everything the planner board renders for one view/range/line filter.
     *
     * @param  array{view_mode?:string|null, start_date?:string|null, line_id?:int|string|null}  $params
     */
    public function board(array $params = []): array
    {
        $settings = $this->loadSettings();

        $viewMode = trim($settings['schedule_view_mode'] ?? 'weekly', '"\'');
        // Fallback only — the board's shift columns follow the actual active
        // shifts (see below); this setting applies when none are defined.
        $shiftsPerDay = (int) trim($settings['schedule_shifts_per_day'] ?? '1', '"\'');
        $horizonWeeks = (int) trim($settings['schedule_horizon_weeks'] ?? '4', '"\'');
        $showWeekends = filter_var(trim($settings['schedule_show_weekends'] ?? 'true', '"\''), FILTER_VALIDATE_BOOLEAN);

        // Snap granularity for the hourly view (in minutes). Defaults to 15
        // and is constrained to a fixed list of UI-supported values.
        $slotMinutes = (int) trim($settings['schedule_slot_minutes'] ?? '15', '"\'');
        if (! in_array($slotMinutes, [5, 10, 15, 30, 60], true)) {
            $slotMinutes = 15;
        }

        if (! empty($params['view_mode']) && in_array($params['view_mode'], self::VIEW_MODES, true)) {
            $viewMode = $params['view_mode'];
        }

        // Calculate start date — anchor depends on view mode.
        // Weekly/monthly snap to start of week; daily/hourly anchor to the
        // specific day so navigating forward/back moves day-by-day.
        $rawStart = ! empty($params['start_date'])
            ? Carbon::parse($params['start_date'])
            : now();

        $startDate = match ($viewMode) {
            'hourly', 'daily' => $rawStart->copy()->startOfDay(),
            'monthly' => $rawStart->copy()->startOfMonth(),
            default => $rawStart->copy()->startOfWeek(),
        };

        [$rangeStart, $rangeEnd] = $this->calculateDateRange($viewMode, $startDate, $horizonWeeks);

        $lineId = $params['line_id'] ?? null;

        // Every active line, loaded once: the board's line filter is a subset of
        // it, so filtering in PHP saves a second identical round trip (the
        // unfiltered board — the default — asked for the same rows twice).
        $allLines = Line::where('is_active', true)->orderBy('name')->get();
        $lines = empty($lineId)
            ? $allLines
            : $allLines->where('id', (int) $lineId)->values();
        $lineIds = $lines->pluck('id');

        // Load the distinct active shifts (e.g. Morning / Afternoon / Night).
        // Shifts are stored per line but share name/time, so collapse the
        // per-line duplicates by their actual time window — deduping by
        // sort_order would silently drop a column when two genuinely distinct
        // slots happen to share a sort_order.
        $shifts = Shift::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get()
            ->unique(fn ($s) => $s->start_time.'|'.$s->end_time)
            ->values();

        // Draw a column per actual shift (clamped to the 4 the grid supports);
        // fall back to the schedule_shifts_per_day setting only when none exist.
        if ($shifts->isNotEmpty()) {
            $shiftsPerDay = min(4, max(1, $shifts->count()));
        }

        $workOrders = WorkOrder::with(['productType', 'line', 'customer', 'extraPlacements'])
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->where(function ($q) use ($lineIds) {
                // An order shows on every line it runs on — its primary
                // placement or any extra segment.
                $q->whereIn('line_id', $lineIds)
                    ->orWhereHas('extraPlacements', fn ($q2) => $q2->whereIn('line_id', $lineIds));
            })
            ->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereBetween('due_date', [$rangeStart, $rangeEnd])
                    // Extra segments are scheduled independently — an order
                    // with any segment in the range must ship too.
                    ->orWhereHas('extraPlacements', fn ($q2) => $q2->whereBetween('due_date', [$rangeStart, $rangeEnd]))
                    ->orWhere(function ($q2) use ($rangeStart, $rangeEnd) {
                        // Minute-planned orders that overlap the visible range
                        $q2->whereNotNull('planned_start_at')
                            ->whereNotNull('planned_end_at')
                            ->where('planned_start_at', '<', $rangeEnd)
                            ->where('planned_end_at', '>', $rangeStart);
                    })
                    ->orWhere(function ($q2) use ($rangeStart, $rangeEnd) {
                        $q2->whereNull('due_date')
                            ->where(function ($q3) use ($rangeStart, $rangeEnd) {
                                // Match by week_number if due_date is null
                                $weekNumbers = [];
                                $cursor = $rangeStart->copy();
                                while ($cursor->lte($rangeEnd)) {
                                    $weekNumbers[] = $cursor->isoWeek();
                                    $cursor->addWeek();
                                }
                                $q3->whereIn('week_number', array_unique($weekNumbers));
                            });
                    });
            })
            ->orderBy('priority', 'desc')
            ->orderBy('due_date')
            ->get();

        $navPrev = match ($viewMode) {
            'daily', 'hourly' => $startDate->copy()->subDay(),
            'monthly' => $startDate->copy()->subMonth(),
            default => $startDate->copy()->subWeek(),
        };
        $navNext = match ($viewMode) {
            'daily', 'hourly' => $startDate->copy()->addDay(),
            'monthly' => $startDate->copy()->addMonth(),
            default => $startDate->copy()->addWeek(),
        };

        $maintenanceEvents = $this->maintenanceEventsInRange($rangeStart, $rangeEnd);

        // Backlog: unassigned work orders (no line or no due_date/week)
        $backlogOrders = WorkOrder::with(['productType', 'line', 'customer'])
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->where(function ($q) {
                $q->whereNull('line_id')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('due_date')->whereNull('week_number');
                    });
            })
            ->orderBy('priority_score', 'desc')
            ->orderBy('priority', 'desc')
            ->orderBy('due_date')
            ->get();

        // High-value orders past due — powers the planner's overdue banner.
        $importantOverdue = WorkOrder::query()
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereHas('customer', fn ($q) => $q->whereIn('tier', ['gold', 'vip']));
        $importantOverdueCount = (clone $importantOverdue)->count();
        $importantOverdueOrders = $importantOverdue
            ->with('customer:id,name,tier')
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(fn ($wo) => [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'customer_name' => $wo->customer?->name,
                'tier' => $wo->customer?->tier?->value,
                'due_date' => $wo->due_date?->format('Y-m-d'),
            ])->all();

        $realtimeMode = trim($settings['realtime_mode'] ?? 'polling', '"\'');

        return [
            'workOrders' => $workOrders->map(fn ($wo) => $this->flattenOrder($wo))->values()->all(),
            'lines' => $this->flattenLines($lines),
            'allLines' => $this->flattenLines($allLines),
            'shifts' => $shifts->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sort_order' => $s->sort_order,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ])->values()->all(),
            'viewMode' => $viewMode,
            'shiftsPerDay' => $shiftsPerDay,
            'slotMinutes' => $slotMinutes,
            'horizonWeeks' => $horizonWeeks,
            'showWeekends' => $showWeekends,
            'startDate' => $startDate->format('Y-m-d'),
            'rangeStart' => $rangeStart->format('Y-m-d'),
            'rangeEnd' => $rangeEnd->format('Y-m-d'),
            'navPrev' => $navPrev->format('Y-m-d'),
            'navNext' => $navNext->format('Y-m-d'),
            'backlogOrders' => $backlogOrders->map(fn ($wo) => [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'product_name' => $wo->productType?->name,
                'customer_name' => $wo->customer?->name,
                'customer_tier' => $wo->customer?->tier?->value,
                'line_id' => $wo->line_id,
                'due_date' => $wo->due_date?->format('Y-m-d'),
                'planned_qty' => $wo->planned_qty,
                'status' => $wo->status,
                'priority' => $wo->priority,
                'priority_score' => $wo->priority_score,
            ])->values()->all(),
            'maintenanceEvents' => $maintenanceEvents->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'event_type' => $m->event_type,
                'status' => $m->status,
                'line_id' => $m->line_id,
                'workstation_id' => $m->workstation_id,
                'schedule_id' => $m->schedule_id,
                'scheduled_at_date' => $m->scheduled_at?->format('Y-m-d'),
                'scheduled_at_time' => $m->scheduled_at?->format('H:i'),
                'scheduled_at_minute' => $m->scheduled_at
                    ? ($m->scheduled_at->hour * 60 + $m->scheduled_at->minute)
                    : null,
                'duration_minutes' => $m->scheduled_end_at
                    ? (int) $m->scheduled_at->diffInMinutes($m->scheduled_end_at)
                    : 60,
                'description' => $m->description,
            ])->values()->all(),
            // Defined maintenance (schedules) offered in the planner's "Add
            // maintenance" modal — drop one onto a line/day as a yellow tile.
            'maintenanceSchedules' => MaintenanceSchedule::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'event_type', 'line_id', 'workstation_id', 'description'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'event_type' => $s->event_type,
                    'line_id' => $s->line_id,
                    'workstation_id' => $s->workstation_id,
                    'description' => $s->description,
                ])->values()->all(),
            'realtimeMode' => $realtimeMode,
            'overdueImportant' => [
                'count' => $importantOverdueCount,
                'orders' => $importantOverdueOrders,
            ],
        ];
    }

    /**
     * Move / re-place an order. Only fields present in `$input` are touched, so
     * a partial edit (a single-segment drag, the capacity drill-down, a stale
     * client) can't silently null or revert placements it didn't mean to change.
     *
     * @return array{conflict:bool, message?:string, warnings?:array<string>}
     */
    /**
     * Place a maintenance event on the planner (the "Add maintenance" modal). A
     * defined schedule (schedule_id) pre-fills the title / type / line; a bare
     * title + event_type works too. Lands as a pending tile at the chosen slot.
     *
     * @param  array<string, mixed>  $input
     */
    public function createMaintenanceEvent(array $input): MaintenanceEvent
    {
        $schedule = ! empty($input['schedule_id'])
            ? MaintenanceSchedule::find($input['schedule_id'])
            : null;

        $scheduledAt = Carbon::parse($input['scheduled_at']);
        $duration = (int) ($input['duration_minutes'] ?? 60);

        return MaintenanceEvent::create([
            'title' => $input['title'] ?? $schedule?->name ?? 'Maintenance',
            'event_type' => $input['event_type'] ?? $schedule?->event_type ?? MaintenanceEvent::TYPE_PLANNED,
            'status' => MaintenanceEvent::STATUS_PENDING,
            'line_id' => $input['line_id'] ?? $schedule?->line_id,
            'workstation_id' => $input['workstation_id'] ?? $schedule?->workstation_id,
            'schedule_id' => $schedule?->id,
            'scheduled_at' => $scheduledAt,
            'scheduled_end_at' => $scheduledAt->copy()->addMinutes(max(1, $duration)),
            'description' => $input['description'] ?? $schedule?->description,
        ]);
    }

    public function updateOrder(WorkOrder $workOrder, array $input, bool $force = false): array
    {
        $data = [];
        foreach (['line_id', 'due_date', 'week_number', 'shift_number', 'end_date', 'end_shift_number'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field] ?: null;
            }
        }

        $newPrimary = array_key_exists('line_id', $data) ? $data['line_id'] : $workOrder->line_id;

        $snapshotBefore = $this->placementSnapshot($workOrder);

        // Minute-level planning timestamps. Only touch the columns if the
        // caller explicitly carried them so we don't accidentally wipe them
        // out from shift-level edits.
        foreach (['planned_start_at', 'planned_end_at'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field] ?: null;
            }
        }

        // Conflict detection: if both timestamps are being set and a line is
        // assigned, refuse the update when another active WO on the same line
        // overlaps the proposed window — unless the caller explicitly forces.
        // The minute plan lives on the primary placement only; extra segments
        // are coarse (day + shift) and never carry a minute window.
        if (! empty($data['planned_start_at']) && ! empty($data['planned_end_at']) && $newPrimary !== null) {
            $conflict = $this->minuteConflictExists($workOrder, [(int) $newPrimary], $data['planned_start_at'], $data['planned_end_at']);

            if ($conflict && ! $force) {
                return [
                    'conflict' => true,
                    'message' => __('This time slot overlaps another work order on the same line.'),
                ];
            }
        }

        $workOrder->update($data);

        // Sync the extra segments when the caller carries them: update rows by
        // id, create rows without one, delete rows the client dropped. Losing
        // the primary line (unassign) always clears every segment.
        if ($newPrimary === null) {
            $workOrder->extraPlacements()->delete();
        } elseif (array_key_exists('extra_placements', $input)) {
            $incoming = collect($input['extra_placements'] ?? []);
            $keepIds = $incoming->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $workOrder->extraPlacements()->whereNotIn('id', $keepIds)->delete();
            foreach ($incoming as $row) {
                $attrs = [
                    'line_id' => $row['line_id'],
                    'due_date' => $row['due_date'],
                    'shift_number' => $row['shift_number'] ?? null,
                    'end_date' => $row['end_date'] ?? null,
                    'end_shift_number' => $row['end_shift_number'] ?? null,
                ];
                $existing = ! empty($row['id']) ? $workOrder->extraPlacements()->find($row['id']) : null;
                $existing ? $existing->update($attrs) : $workOrder->extraPlacements()->create($attrs);
            }
        }

        $this->logChange($workOrder, $snapshotBefore);

        return [
            'conflict' => false,
            'warnings' => $this->prepareBatchAndWarn($workOrder),
        ];
    }

    /**
     * Minute-level move/resize (both timestamps), or the legacy shift-level
     * span edit when they're absent.
     *
     * @return array{conflict:bool, message?:string}
     */
    public function resizeOrder(WorkOrder $workOrder, array $input, bool $force = false): array
    {
        $snapshotBefore = $this->placementSnapshot($workOrder);

        if (! empty($input['planned_start_at']) && ! empty($input['planned_end_at'])) {
            // Minute windows live on the primary placement only.
            if ($workOrder->line_id) {
                $conflict = $this->minuteConflictExists(
                    $workOrder,
                    [$workOrder->line_id],
                    $input['planned_start_at'],
                    $input['planned_end_at'],
                );

                if ($conflict && ! $force) {
                    return [
                        'conflict' => true,
                        'message' => __('This time slot overlaps another work order on the same line.'),
                    ];
                }
            }

            $workOrder->update([
                'planned_start_at' => $input['planned_start_at'],
                'planned_end_at' => $input['planned_end_at'],
            ]);
            $this->logChange($workOrder, $snapshotBefore);

            return ['conflict' => false];
        }

        // Allow null to clear span (legacy shift-level behaviour)
        if (($input['end_date'] ?? null) === null && ($input['end_shift_number'] ?? null) === null) {
            $workOrder->update(['end_date' => null, 'end_shift_number' => null]);
        } else {
            $workOrder->update([
                'end_date' => $input['end_date'],
                'end_shift_number' => $input['end_shift_number'],
            ]);
        }
        $this->logChange($workOrder, $snapshotBefore);

        return ['conflict' => false];
    }

    /** The last planner edits, newest first — the backlog rail's Changes tab. */
    public function recentChanges(int $limit = 50): array
    {
        return ScheduleChangeLog::with(['workOrder:id,order_no', 'user:id,name'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'work_order_id' => $c->work_order_id,
                'order_no' => $c->workOrder?->order_no,
                'action' => $c->action,
                'before' => $c->before,
                'after' => $c->after,
                'user' => $c->user?->name,
                'undone_at' => $c->undone_at?->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
            ])->values()->all();
    }

    /**
     * Revert one edit: restore the order's placement snapshot from before it.
     * The revert is itself logged (action 'undo'), so it can be undone too.
     */
    public function undoChange(ScheduleChangeLog $change): bool
    {
        $workOrder = $change->workOrder;
        if (! $workOrder) {
            return false;
        }

        $current = $this->placementSnapshot($workOrder);
        $s = $change->before;

        $workOrder->update([
            'line_id' => $s['line_id'] ?? null,
            'due_date' => $s['due_date'] ?? null,
            'week_number' => $s['week_number'] ?? null,
            'shift_number' => $s['shift_number'] ?? null,
            'end_date' => $s['end_date'] ?? null,
            'end_shift_number' => $s['end_shift_number'] ?? null,
            'planned_start_at' => $s['planned_start_at'] ?? null,
            'planned_end_at' => $s['planned_end_at'] ?? null,
        ]);
        $workOrder->extraPlacements()->delete();
        foreach ($s['placements'] ?? [] as $p) {
            $workOrder->extraPlacements()->create($p);
        }

        $change->update(['undone_at' => now()]);
        ScheduleChangeLog::create([
            'work_order_id' => $workOrder->id,
            'user_id' => auth()->id(),
            'action' => 'undo',
            'before' => $current,
            'after' => $this->placementSnapshot($workOrder->fresh('extraPlacements')),
        ]);

        return true;
    }

    /**
     * Everything the planner can change about an order's schedule, as one
     * comparable/restorable array.
     */
    public function placementSnapshot(WorkOrder $workOrder): array
    {
        return [
            'line_id' => $workOrder->line_id,
            'due_date' => $workOrder->due_date?->format('Y-m-d'),
            'week_number' => $workOrder->week_number,
            'shift_number' => $workOrder->shift_number,
            'end_date' => $workOrder->end_date?->format('Y-m-d'),
            'end_shift_number' => $workOrder->end_shift_number,
            'planned_start_at' => $workOrder->planned_start_at?->toIso8601String(),
            'planned_end_at' => $workOrder->planned_end_at?->toIso8601String(),
            'placements' => $workOrder->loadMissing('extraPlacements')->extraPlacements->map(fn ($p) => [
                'line_id' => $p->line_id,
                'due_date' => $p->due_date->format('Y-m-d'),
                'shift_number' => $p->shift_number,
                'end_date' => $p->end_date?->format('Y-m-d'),
                'end_shift_number' => $p->end_shift_number,
            ])->values()->all(),
        ];
    }

    /** Log the edit when the snapshot actually changed. */
    public function logChange(WorkOrder $workOrder, array $before): void
    {
        $after = $this->placementSnapshot($workOrder->fresh('extraPlacements'));
        if ($before == $after) {
            return;
        }
        ScheduleChangeLog::create([
            'work_order_id' => $workOrder->id,
            'user_id' => auth()->id(),
            'action' => 'reschedule',
            'before' => $before,
            'after' => $after,
        ]);
    }

    /**
     * Does any other active, minute-planned order overlap the proposed window
     * on one of the given lines? Minute windows always live on an order's
     * primary line — extra segments are coarse and never conflict here.
     */
    public function minuteConflictExists(WorkOrder $workOrder, array $lineIds, string $startAt, string $endAt): bool
    {
        return WorkOrder::query()
            ->whereIn('line_id', $lineIds)
            ->where('id', '!=', $workOrder->id)
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->whereNotNull('planned_start_at')
            ->whereNotNull('planned_end_at')
            ->where('planned_start_at', '<', $endAt)
            ->where('planned_end_at', '>', $startAt)
            ->exists();
    }

    public function flattenOrder(WorkOrder $wo): array
    {
        $planned = (float) $wo->planned_qty;
        $produced = (float) $wo->produced_qty;

        return [
            'id' => $wo->id,
            'order_no' => $wo->order_no,
            'customer_name' => $wo->customer?->name,
            'customer_tier' => $wo->customer?->tier?->value,
            'priority_score' => $wo->priority_score,
            'product_name' => $wo->productType?->name,
            'line_id' => $wo->line_id,
            'secondary_line_id' => $wo->secondary_line_id,
            'product_type_id' => $wo->product_type_id,
            'status' => $wo->status,
            'priority' => $wo->priority,
            'planned_qty' => $wo->planned_qty,
            'produced_qty' => $wo->produced_qty,
            'progress_percent' => $planned > 0 ? (int) round($produced / $planned * 100) : 0,
            'is_overdue' => $wo->due_date
                && $wo->due_date->lt(today())
                && ! in_array($wo->status, WorkOrder::TERMINAL_STATUSES),
            'due_date' => $wo->due_date?->format('Y-m-d'),
            'end_date' => $wo->end_date?->format('Y-m-d'),
            'placements' => $wo->extraPlacements->map(fn ($p) => [
                'id' => $p->id,
                'line_id' => $p->line_id,
                'due_date' => $p->due_date->format('Y-m-d'),
                'shift_number' => $p->shift_number,
                'end_date' => $p->end_date?->format('Y-m-d'),
                'end_shift_number' => $p->end_shift_number,
            ])->values()->all(),
            'week_number' => $wo->week_number,
            'month_number' => $wo->month_number,
            'shift_number' => $wo->shift_number,
            'end_shift_number' => $wo->end_shift_number,
            'planned_start_at' => $wo->planned_start_at?->toIso8601String(),
            'planned_end_at' => $wo->planned_end_at?->toIso8601String(),
        ];
    }

    /**
     * The schedule placement is persisted before this runs. These side-effects
     * can throw on incomplete product data (missing BOM material, lot
     * allocation, …); that must NOT 500 the schedule drag and discard the
     * user's placement — collect it as a warning instead so the edit succeeds.
     *
     * @return array<string>
     */
    private function prepareBatchAndWarn(WorkOrder $workOrder): array
    {
        $warnings = [];

        try {
            // If line assigned and no process_snapshot yet — generate it from product type
            if ($workOrder->line_id && $workOrder->product_type_id && empty($workOrder->process_snapshot)) {
                $processTemplate = ProcessTemplate::where('product_type_id', $workOrder->product_type_id)
                    ->where('is_active', true)
                    ->orderBy('version', 'desc')
                    ->first();
                if ($processTemplate) {
                    $workOrder->update(['process_snapshot' => $processTemplate->toSnapshot()]);
                }
            }

            // Auto-create first batch if none exist and WO has line + snapshot
            if ($workOrder->line_id && ! empty($workOrder->process_snapshot) && $workOrder->batches()->count() === 0) {
                app(WorkOrderService::class)->createBatch($workOrder, $workOrder->planned_qty);
            }
        } catch (\Throwable $e) {
            report($e);
            $warnings[] = __('Scheduled, but the batch could not be prepared automatically: :msg', ['msg' => $e->getMessage()]);
        }

        // Warn about cross-line workstations
        if ($workOrder->line_id && ! empty($workOrder->process_snapshot)) {
            $lineWorkstationIds = Workstation::where('line_id', $workOrder->line_id)->pluck('id')->toArray();
            foreach ($workOrder->process_snapshot['steps'] ?? [] as $step) {
                if (! empty($step['workstation_id']) && ! in_array($step['workstation_id'], $lineWorkstationIds)) {
                    $warnings[] = __('Step ":step" uses workstation ":ws" from another line.', [
                        'step' => $step['name'],
                        'ws' => $step['workstation_name'] ?? $step['workstation_id'],
                    ]);
                }
            }
        }

        return $warnings;
    }

    /**
     * Real maintenance events in range, plus virtual occurrences projected from
     * active recurring schedules (the board draws the whole visible range, not
     * just each schedule's next_due_at).
     */
    private function maintenanceEventsInRange(Carbon $rangeStart, Carbon $rangeEnd)
    {
        $maintenanceEvents = MaintenanceEvent::with(['line', 'workstation'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $rangeStart)
            ->where('scheduled_at', '<=', $rangeEnd)
            ->orderBy('scheduled_at')
            ->get();

        $activeSchedules = MaintenanceSchedule::with(['line', 'workstation'])
            ->where('is_active', true)
            ->whereNotNull('next_due_at')
            ->get();

        foreach ($activeSchedules as $schedule) {
            $intervalDays = match ($schedule->frequency) {
                'daily' => 1,
                'weekly' => 7,
                'monthly' => 30,
                'quarterly' => 91,
                'annually' => 365,
                default => 7,
            };

            $cursor = $schedule->next_due_at->copy();

            // If next_due is after range, walk backwards to find first occurrence in range
            while ($cursor->gt($rangeEnd)) {
                $cursor->subDays($intervalDays);
            }
            // Walk backwards to find the earliest occurrence in range
            while ($cursor->copy()->subDays($intervalDays)->gte($rangeStart)) {
                $cursor->subDays($intervalDays);
            }

            while ($cursor->lte($rangeEnd)) {
                if ($cursor->gte($rangeStart)) {
                    $dateStr = $cursor->format('Y-m-d');
                    $hasEvent = $maintenanceEvents->contains(
                        fn ($e) => $e->schedule_id === $schedule->id
                            && $e->scheduled_at->format('Y-m-d') === $dateStr
                    );

                    if (! $hasEvent) {
                        $time = $schedule->preferred_time ?? '06:00';
                        $scheduledAt = $cursor->copy()->setTimeFromTimeString($time);
                        $virtual = new MaintenanceEvent([
                            'title' => $schedule->name,
                            'event_type' => $schedule->event_type,
                            'status' => 'pending',
                            'line_id' => $schedule->line_id,
                            'workstation_id' => $schedule->workstation_id,
                            'schedule_id' => $schedule->id,
                            'scheduled_at' => $scheduledAt,
                            'scheduled_end_at' => $scheduledAt->copy()->addHour(),
                            'description' => $schedule->description,
                        ]);
                        $virtual->setRelation('line', $schedule->line);
                        $virtual->setRelation('workstation', $schedule->workstation);
                        $maintenanceEvents->push($virtual);
                    }
                }
                $cursor->addDays($intervalDays);
            }
        }

        return $maintenanceEvents;
    }

    private function flattenLines($lines): array
    {
        return $lines->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'code' => $l->code])->values()->all();
    }

    private function loadSettings(): array
    {
        $keys = [
            'schedule_view_mode',
            'schedule_shifts_per_day',
            'schedule_horizon_weeks',
            'schedule_show_weekends',
            'schedule_slot_minutes',
            'realtime_mode',
        ];

        return DB::table('system_settings')
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->toArray();
    }

    private function calculateDateRange(string $viewMode, Carbon $startDate, int $horizonWeeks): array
    {
        return match ($viewMode) {
            'daily' => [
                $startDate->copy(),
                $startDate->copy()->addDays(13)->endOfDay(),
            ],
            'hourly' => [
                $startDate->copy()->startOfDay(),
                $startDate->copy()->endOfDay(),
            ],
            'monthly' => [
                $startDate->copy()->startOfMonth(),
                $startDate->copy()->addMonths(2)->endOfMonth(),
            ],
            // Weekly: the board renders exactly the one week starting at
            // $startDate, so ship only that week's orders (nav moves week-by-week).
            // Shipping the full horizon left later-week orders with no column.
            default => [
                $startDate->copy(),
                $startDate->copy()->endOfWeek()->endOfDay(),
            ],
        };
    }
}
