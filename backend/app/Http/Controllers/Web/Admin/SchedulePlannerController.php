<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Shift;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SchedulePlannerController extends Controller
{
    public function index(Request $request)
    {
        // Load schedule settings
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

        // Allow query string override for view mode
        if ($request->filled('view_mode') && in_array($request->view_mode, ['weekly', 'daily', 'hourly', 'monthly'])) {
            $viewMode = $request->view_mode;
        }

        // Calculate start date — anchor depends on view mode.
        // Weekly/monthly snap to start of week; daily/hourly anchor to the
        // specific day so navigating forward/back moves day-by-day.
        $rawStart = $request->filled('start_date')
            ? Carbon::parse($request->start_date)
            : now();

        $startDate = match ($viewMode) {
            'hourly', 'daily' => $rawStart->copy()->startOfDay(),
            'monthly' => $rawStart->copy()->startOfMonth(),
            default => $rawStart->copy()->startOfWeek(),
        };

        // Calculate date range based on view mode
        [$rangeStart, $rangeEnd] = $this->calculateDateRange($viewMode, $startDate, $horizonWeeks);

        // Load active lines
        $linesQuery = Line::where('is_active', true)->orderBy('name');
        if ($request->filled('line_id')) {
            $linesQuery->where('id', $request->line_id);
        }
        $lines = $linesQuery->get();
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

        // Load work orders in range
        $workOrders = WorkOrder::with(['productType', 'line'])
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->whereIn('line_id', $lineIds)
            ->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereBetween('due_date', [$rangeStart, $rangeEnd])
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

        // The grid layout is computed client-side (see the React Planner) from a
        // flat work-order list — the controller only ships the raw data the views
        // need (orders, lines, shifts, backlog, maintenance) plus the visible range.

        // Navigation dates
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

        // Maintenance events in range (pending/in_progress, with scheduled_at)
        $maintenanceEvents = \App\Models\MaintenanceEvent::with(['line', 'workstation'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $rangeStart)
            ->where('scheduled_at', '<=', $rangeEnd)
            ->orderBy('scheduled_at')
            ->get();

        // Generate virtual maintenance events from recurring schedules
        // for the entire visible range (not just next_due_at)
        $activeSchedules = \App\Models\MaintenanceSchedule::with(['line', 'workstation'])
            ->where('is_active', true)
            ->whereNotNull('next_due_at')
            ->get();

        foreach ($activeSchedules as $schedule) {
            // Calculate interval in days
            $intervalDays = match ($schedule->frequency) {
                'daily' => 1,
                'weekly' => 7,
                'monthly' => 30,
                'quarterly' => 91,
                'annually' => 365,
                default => 7,
            };

            // Generate occurrences within visible range
            $cursor = $schedule->next_due_at->copy();

            // If next_due is after range, walk backwards to find first occurrence in range
            while ($cursor->gt($rangeEnd)) {
                $cursor->subDays($intervalDays);
            }
            // Walk backwards to find the earliest occurrence in range
            while ($cursor->copy()->subDays($intervalDays)->gte($rangeStart)) {
                $cursor->subDays($intervalDays);
            }

            // Now walk forward generating events
            while ($cursor->lte($rangeEnd)) {
                if ($cursor->gte($rangeStart)) {
                    // Check if a real event already exists on this date
                    $dateStr = $cursor->format('Y-m-d');
                    $hasEvent = $maintenanceEvents->contains(function ($e) use ($schedule, $dateStr) {
                        return $e->schedule_id === $schedule->id
                            && $e->scheduled_at->format('Y-m-d') === $dateStr;
                    });

                    if (! $hasEvent) {
                        $time = $schedule->preferred_time ?? '06:00';
                        $scheduledAt = $cursor->copy()->setTimeFromTimeString($time);
                        $virtual = new \App\Models\MaintenanceEvent([
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

        // All lines for filter dropdown (unfiltered)
        $allLines = Line::where('is_active', true)->orderBy('name')->get();

        // Backlog: unassigned work orders (no line or no due_date/week)
        $backlogOrders = WorkOrder::with(['productType', 'line'])
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->where(function ($q) {
                $q->whereNull('line_id')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('due_date')->whereNull('week_number');
                    });
            })
            ->orderBy('priority', 'desc')
            ->orderBy('due_date')
            ->get();

        $realtimeMode = trim($settings['realtime_mode'] ?? 'polling', '"\'');

        // Flatten maintenance events for the React component
        $maintFlat = $maintenanceEvents->map(fn ($m) => [
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
        ])->values()->all();

        // Flatten work orders for the React component. The client computes all
        // grid/lane placement from this flat list (camelCase-free, ISO dates).
        $workOrdersFlat = $workOrders->map(function ($wo) {
            $planned = (float) $wo->planned_qty;
            $produced = (float) $wo->produced_qty;

            return [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'product_name' => $wo->productType?->name,
                'line_id' => $wo->line_id,
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
                'week_number' => $wo->week_number,
                'month_number' => $wo->month_number,
                'shift_number' => $wo->shift_number,
                'end_shift_number' => $wo->end_shift_number,
                'planned_start_at' => $wo->planned_start_at?->toIso8601String(),
                'planned_end_at' => $wo->planned_end_at?->toIso8601String(),
            ];
        })->values()->all();

        // Flatten backlog orders
        $backlogFlat = $backlogOrders->map(fn ($wo) => [
            'id' => $wo->id,
            'order_no' => $wo->order_no,
            'product_name' => $wo->productType?->name,
            'line_id' => $wo->line_id,
            'due_date' => $wo->due_date?->format('Y-m-d'),
            'planned_qty' => $wo->planned_qty,
            'status' => $wo->status,
            'priority' => $wo->priority,
        ])->values()->all();

        // Flatten lines for props
        $linesFlat = $lines->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'code' => $l->code])->values()->all();
        $allLinesFlat = $allLines->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'code' => $l->code])->values()->all();
        $shiftsFlat = $shifts->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'sort_order' => $s->sort_order,
            'start_time' => $s->start_time,
            'end_time' => $s->end_time,
        ])->values()->all();

        return Inertia::render('admin/schedule/Planner', [
            'workOrders' => $workOrdersFlat,
            'lines' => $linesFlat,
            'allLines' => $allLinesFlat,
            'shifts' => $shiftsFlat,
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
            'backlogOrders' => $backlogFlat,
            'maintenanceEvents' => $maintFlat,
            'realtimeMode' => $realtimeMode,
        ]);
    }

    public function updateOrder(Request $request, WorkOrder $workOrder)
    {
        $request->validate([
            'line_id' => 'nullable|exists:lines,id',
            'due_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:due_date',
            'week_number' => 'nullable|integer|min:1|max:53',
            'shift_number' => 'nullable|integer|min:1|max:10',
            'end_shift_number' => 'nullable|integer|min:1|max:10',
            'planned_start_at' => 'nullable|date',
            'planned_end_at' => 'nullable|date|after:planned_start_at',
        ]);

        $data = [
            'line_id' => $request->input('line_id') ?: null,
            'due_date' => $request->input('due_date') ?: null,
            'week_number' => $request->input('week_number') ?: null,
            'shift_number' => $request->input('shift_number') ?: null,
        ];

        // Handle span fields — only set if explicitly provided
        if ($request->has('end_date')) {
            $data['end_date'] = $request->input('end_date') ?: null;
        }
        if ($request->has('end_shift_number')) {
            $data['end_shift_number'] = $request->input('end_shift_number') ?: null;
        }

        // Minute-level planning timestamps. Only touch the columns if the
        // request explicitly carried them so we don't accidentally wipe them
        // out from shift-level edits.
        if ($request->has('planned_start_at')) {
            $data['planned_start_at'] = $request->input('planned_start_at') ?: null;
        }
        if ($request->has('planned_end_at')) {
            $data['planned_end_at'] = $request->input('planned_end_at') ?: null;
        }

        // Conflict detection: if both timestamps are being set and a line is
        // assigned, refuse the update when another active WO on the same line
        // overlaps the proposed window — unless the caller explicitly forces.
        $targetLineId = array_key_exists('line_id', $data) ? $data['line_id'] : $workOrder->line_id;
        if (! empty($data['planned_start_at']) && ! empty($data['planned_end_at']) && $targetLineId) {
            $conflict = WorkOrder::query()
                ->where('line_id', $targetLineId)
                ->where('id', '!=', $workOrder->id)
                ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
                ->whereNotNull('planned_start_at')
                ->whereNotNull('planned_end_at')
                ->where('planned_start_at', '<', $data['planned_end_at'])
                ->where('planned_end_at', '>', $data['planned_start_at'])
                ->exists();

            if ($conflict && ! $request->boolean('force_conflict')) {
                $message = __('This time slot overlaps another work order on the same line.');
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'conflict' => true,
                        'message' => $message,
                    ], 409);
                }

                return back()->with('error', $message);
            }
        }

        $workOrder->update($data);

        // The schedule placement is already persisted above. The snapshot /
        // auto-batch side-effects below can throw on incomplete product data
        // (missing BOM material, lot allocation, …); that must NOT 500 the
        // schedule drag and discard the user's placement — collect it as a
        // warning instead so the planner edit still succeeds.
        $warnings = [];
        try {
            // If line assigned and no process_snapshot yet — generate it from product type
            if ($workOrder->line_id && $workOrder->product_type_id && empty($workOrder->process_snapshot)) {
                $processTemplate = \App\Models\ProcessTemplate::where('product_type_id', $workOrder->product_type_id)
                    ->where('is_active', true)
                    ->orderBy('version', 'desc')
                    ->first();
                if ($processTemplate) {
                    $workOrder->update(['process_snapshot' => $processTemplate->toSnapshot()]);
                }
            }

            // Auto-create first batch if none exist and WO has line + snapshot
            if ($workOrder->line_id && ! empty($workOrder->process_snapshot) && $workOrder->batches()->count() === 0) {
                app(\App\Services\WorkOrder\WorkOrderService::class)
                    ->createBatch($workOrder, $workOrder->planned_qty);
            }
        } catch (\Throwable $e) {
            report($e);
            $warnings[] = __('Scheduled, but the batch could not be prepared automatically: :msg', ['msg' => $e->getMessage()]);
        }

        // Warn about cross-line workstations
        if ($workOrder->line_id && ! empty($workOrder->process_snapshot)) {
            $lineWorkstationIds = \App\Models\Workstation::where('line_id', $workOrder->line_id)->pluck('id')->toArray();
            foreach ($workOrder->process_snapshot['steps'] ?? [] as $step) {
                if (! empty($step['workstation_id']) && ! in_array($step['workstation_id'], $lineWorkstationIds)) {
                    $warnings[] = __('Step ":step" uses workstation ":ws" from another line.', [
                        'step' => $step['name'],
                        'ws' => $step['workstation_name'] ?? $step['workstation_id'],
                    ]);
                }
            }
        }

        $message = __('Work order updated successfully.');
        if (! empty($warnings)) {
            $message .= ' '.__('Warnings:').' '.implode('; ', $warnings);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'warnings' => $warnings,
                'order' => [
                    'id' => $workOrder->id,
                    'order_no' => $workOrder->order_no,
                    'line_id' => $workOrder->line_id,
                    'due_date' => $workOrder->due_date?->format('Y-m-d'),
                    'week_number' => $workOrder->week_number,
                ],
            ]);
        }

        return back()->with('success', __('Work order updated successfully.'));
    }

    public function resizeOrder(Request $request, WorkOrder $workOrder)
    {
        // Minute-level resize: when both `planned_start_at` and
        // `planned_end_at` are present we treat the request as a minute-level
        // move/resize and bypass the legacy shift-level branch.
        if ($request->filled('planned_start_at') && $request->filled('planned_end_at')) {
            $validated = $request->validate([
                'planned_start_at' => 'required|date',
                'planned_end_at' => 'required|date|after:planned_start_at',
            ]);

            if ($workOrder->line_id) {
                $conflict = WorkOrder::query()
                    ->where('line_id', $workOrder->line_id)
                    ->where('id', '!=', $workOrder->id)
                    ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
                    ->whereNotNull('planned_start_at')
                    ->whereNotNull('planned_end_at')
                    ->where('planned_start_at', '<', $validated['planned_end_at'])
                    ->where('planned_end_at', '>', $validated['planned_start_at'])
                    ->exists();

                if ($conflict && ! $request->boolean('force_conflict')) {
                    return response()->json([
                        'success' => false,
                        'conflict' => true,
                        'message' => __('This time slot overlaps another work order on the same line.'),
                    ], 409);
                }
            }

            $workOrder->update([
                'planned_start_at' => $validated['planned_start_at'],
                'planned_end_at' => $validated['planned_end_at'],
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Work order span updated.'),
                'order' => [
                    'id' => $workOrder->id,
                    'order_no' => $workOrder->order_no,
                    'planned_start_at' => $workOrder->planned_start_at?->toIso8601String(),
                    'planned_end_at' => $workOrder->planned_end_at?->toIso8601String(),
                ],
            ]);
        }

        // Allow null to clear span (legacy shift-level behaviour)
        if ($request->input('end_date') === null && $request->input('end_shift_number') === null) {
            $workOrder->update(['end_date' => null, 'end_shift_number' => null]);
        } else {
            $request->validate([
                'end_date' => 'required|date|after_or_equal:'.($workOrder->due_date?->format('Y-m-d') ?? 'today'),
                'end_shift_number' => 'required|integer|min:1|max:10',
            ]);
            $workOrder->update([
                'end_date' => $request->input('end_date'),
                'end_shift_number' => $request->input('end_shift_number'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Work order span updated.'),
            'order' => [
                'id' => $workOrder->id,
                'order_no' => $workOrder->order_no,
                'due_date' => $workOrder->due_date?->format('Y-m-d'),
                'shift_number' => $workOrder->shift_number,
                'end_date' => $workOrder->end_date?->format('Y-m-d'),
                'end_shift_number' => $workOrder->end_shift_number,
            ],
        ]);
    }

    public function checkUpdates(Request $request)
    {
        $lastUpdated = WorkOrder::max('updated_at');

        $response = [
            'last_updated' => $lastUpdated ? Carbon::parse($lastUpdated)->toIso8601String() : null,
        ];

        // Live tracking: return real-time data for a specific work order
        if ($request->filled('track')) {
            $wo = WorkOrder::with(['productType', 'line', 'batches.steps.workstation'])
                ->find($request->track);

            if ($wo) {
                $planned = (float) $wo->planned_qty;
                $produced = (float) $wo->produced_qty;
                $percent = $planned > 0 ? min(100, round(($produced / $planned) * 100, 1)) : 0;

                // Current batch step info
                $currentStep = null;
                foreach ($wo->batches as $batch) {
                    $step = $batch->steps->firstWhere('status', 'in_progress')
                        ?? $batch->steps->firstWhere('status', 'pending');
                    if ($step) {
                        $currentStep = [
                            'name' => $step->name ?? $step->workstation?->name ?? '-',
                            'status' => $step->status,
                            'batch_number' => $batch->batch_number,
                        ];
                        break;
                    }
                }

                $isOverdue = $wo->due_date
                    && $wo->due_date->lt(today())
                    && ! in_array($wo->status, WorkOrder::TERMINAL_STATUSES);

                $response['tracked_order'] = [
                    'id' => $wo->id,
                    'order_no' => $wo->order_no,
                    'status' => $wo->status,
                    'line' => $wo->line?->name ?? '-',
                    'product' => $wo->productType?->name ?? '-',
                    'planned_qty' => $planned,
                    'produced_qty' => $produced,
                    'progress_percent' => $percent,
                    'is_overdue' => $isOverdue,
                    'current_step' => $currentStep,
                    'updated_at' => $wo->updated_at->toIso8601String(),
                ];
            }
        }

        return response()->json($response);
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
