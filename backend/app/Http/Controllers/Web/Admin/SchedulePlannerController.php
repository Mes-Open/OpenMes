<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Shift;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchedulePlannerController extends Controller
{
    public function index(Request $request)
    {
        // Load schedule settings
        $settings = $this->loadSettings();

        $viewMode = trim($settings['schedule_view_mode'] ?? 'weekly', '"\'');
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

        // Load active shifts
        $shifts = Shift::where('is_active', true)->orderBy('sort_order')->get();

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

        // Build data structure based on view mode
        $data = match ($viewMode) {
            'daily' => $this->buildDailyData($startDate, $rangeEnd, $lines, $workOrders, $shiftsPerDay, $showWeekends),
            'hourly' => $this->buildHourlyData($startDate, $lines, $workOrders, $slotMinutes, $showWeekends),
            'monthly' => $this->buildMonthlyData($startDate, $rangeEnd, $lines, $workOrders, $shiftsPerDay, $showWeekends),
            default => $this->buildWeeklyData($startDate, $rangeEnd, $lines, $workOrders, $shiftsPerDay, $showWeekends),
        };

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

        $viewData = compact(
            'data',
            'lines',
            'allLines',
            'shifts',
            'viewMode',
            'shiftsPerDay',
            'slotMinutes',
            'horizonWeeks',
            'showWeekends',
            'startDate',
            'rangeStart',
            'rangeEnd',
            'navPrev',
            'navNext',
            'backlogOrders',
            'realtimeMode',
        );

        // Partial response for infinite scroll — return only week cards HTML
        if ($request->filled('_partial')) {
            return view('admin.schedule.planner-weeks', $viewData);
        }

        return view('admin.schedule.planner', $viewData);
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
        if ($workOrder->line_id && !empty($workOrder->process_snapshot) && $workOrder->batches()->count() === 0) {
            app(\App\Services\WorkOrder\WorkOrderService::class)
                ->createBatch($workOrder, $workOrder->planned_qty);
        }

        // Warn about cross-line workstations
        $warnings = [];
        if ($workOrder->line_id && !empty($workOrder->process_snapshot)) {
            $lineWorkstationIds = \App\Models\Workstation::where('line_id', $workOrder->line_id)->pluck('id')->toArray();
            foreach ($workOrder->process_snapshot['steps'] ?? [] as $step) {
                if (!empty($step['workstation_id']) && !in_array($step['workstation_id'], $lineWorkstationIds)) {
                    $warnings[] = __('Step ":step" uses workstation ":ws" from another line.', [
                        'step' => $step['name'],
                        'ws' => $step['workstation_name'] ?? $step['workstation_id'],
                    ]);
                }
            }
        }

        event(new \App\Events\ScheduleUpdated());

        $message = __('Work order updated successfully.');
        if (!empty($warnings)) {
            $message .= ' ' . __('Warnings:') . ' ' . implode('; ', $warnings);
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

            event(new \App\Events\ScheduleUpdated());

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
                'end_date' => 'required|date|after_or_equal:' . ($workOrder->due_date?->format('Y-m-d') ?? 'today'),
                'end_shift_number' => 'required|integer|min:1|max:10',
            ]);
            $workOrder->update([
                'end_date' => $request->input('end_date'),
                'end_shift_number' => $request->input('end_shift_number'),
            ]);
        }

        event(new \App\Events\ScheduleUpdated());

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
            default => [
                $startDate->copy(),
                $startDate->copy()->addWeeks($horizonWeeks)->subDay()->endOfDay(),
            ],
        };
    }

    private function buildWeeklyData(Carbon $start, Carbon $end, $lines, $workOrders, int $shiftsPerDay, bool $showWeekends): array
    {
        $weeks = [];
        $cursor = $start->copy();
        $daysInWeek = $showWeekends ? 7 : 5;

        while ($cursor->lte($end)) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $cursor->copy()->endOfWeek();
            $weekNumber = $cursor->isoWeek();

            $weekLines = [];
            foreach ($lines as $line) {
                $lineOrders = $workOrders->filter(function ($wo) use ($line, $weekStart, $weekEnd, $weekNumber) {
                    if ($wo->line_id !== $line->id) {
                        return false;
                    }
                    if ($wo->due_date) {
                        return $wo->due_date->between($weekStart, $weekEnd);
                    }

                    return $wo->week_number === $weekNumber;
                })->values();

                $capacity = $shiftsPerDay * $daysInWeek;
                $usedSlots = $lineOrders->count();
                $loadPercent = $capacity > 0 ? min(100, round(($usedSlots / $capacity) * 100)) : 0;

                // Build grid: map orders to specific day+shift slots
                $grid = [];
                $dayCursor = $weekStart->copy();
                for ($d = 0; $d < $daysInWeek; $d++) {
                    $dateKey = $dayCursor->format('Y-m-d');
                    for ($s = 1; $s <= $shiftsPerDay; $s++) {
                        $grid[$dateKey . '-' . $s] = null;
                    }
                    $dayCursor->addDay();
                }

                // Place orders with due_date into their specific day+shift
                $dated = $lineOrders->filter(fn ($wo) => $wo->due_date !== null)->sortBy('priority', SORT_REGULAR, true);
                // First pass: orders with explicit shift_number go to exact slot
                foreach ($dated as $wo) {
                    if ($wo->shift_number) {
                        $key = $wo->due_date->format('Y-m-d') . '-' . $wo->shift_number;
                        if (array_key_exists($key, $grid) && $grid[$key] === null) {
                            $grid[$key] = $wo;
                        }
                    }
                }
                // Second pass: orders without shift_number go to first free shift of their day
                foreach ($dated as $wo) {
                    if ($wo->shift_number) {
                        continue;
                    }
                    $dk = $wo->due_date->format('Y-m-d');
                    for ($s = 1; $s <= $shiftsPerDay; $s++) {
                        $key = $dk . '-' . $s;
                        if (array_key_exists($key, $grid) && $grid[$key] === null) {
                            $grid[$key] = $wo;
                            break;
                        }
                    }
                }

                // Place orders with only week_number into first available slots
                $undated = $lineOrders->filter(fn ($wo) => $wo->due_date === null);
                $emptySlots = array_keys(array_filter($grid, fn ($v) => $v === null));
                $si = 0;
                foreach ($undated as $wo) {
                    if (isset($emptySlots[$si])) {
                        $grid[$emptySlots[$si]] = $wo;
                        $si++;
                    }
                }

                // Build span map: vertical spanning through shifts, then next days.
                // For each spanned cell, record type and rowspan (per-day vertical span).
                $spans = [];     // gridKey => ['order' => $wo, 'type' => 'start'|'day-start'|'cont'|'single', 'rowspan' => int]
                foreach ($grid as $key => $wo) {
                    if ($wo === null) {
                        continue;
                    }
                    if (! $wo->end_date || ! $wo->end_shift_number) {
                        $spans[$key] = ['order' => $wo, 'type' => 'single', 'rowspan' => 1];
                        continue;
                    }

                    // Parse start date+shift from grid key
                    [$startDate, $startShift] = [substr($key, 0, 10), (int) substr($key, 11)];
                    $endDate = $wo->end_date->format('Y-m-d');
                    $endShift = (int) $wo->end_shift_number;

                    // Enumerate all cells this order occupies (shift-by-shift, day-by-day)
                    $spannedKeys = [];
                    $curDate = $startDate;
                    $curShift = $startShift;
                    $maxIter = $shiftsPerDay * $daysInWeek * 2; // safety limit
                    $iter = 0;
                    while ($iter++ < $maxIter) {
                        $curKey = $curDate . '-' . $curShift;
                        if (! array_key_exists($curKey, $grid)) break;
                        $spannedKeys[] = $curKey;
                        if ($curDate === $endDate && $curShift === $endShift) break;
                        // Advance: next shift, or wrap to next day
                        $curShift++;
                        if ($curShift > $shiftsPerDay) {
                            $curShift = 1;
                            $curDate = \Carbon\Carbon::parse($curDate)->addDay()->format('Y-m-d');
                        }
                    }

                    if (count($spannedKeys) <= 1) {
                        $spans[$key] = ['order' => $wo, 'type' => 'single', 'rowspan' => 1];
                        continue;
                    }

                    // Group spanned keys by date for per-day rowspan
                    $byDate = [];
                    foreach ($spannedKeys as $sk) {
                        $d = substr($sk, 0, 10);
                        $byDate[$d][] = $sk;
                    }

                    $isFirst = true;
                    foreach ($byDate as $date => $dateKeys) {
                        // First key of each day = start of vertical span in that column
                        $firstKey = $dateKeys[0];
                        $spans[$firstKey] = [
                            'order' => $wo,
                            'type' => $isFirst ? 'start' : 'day-start',
                            'rowspan' => count($dateKeys),
                        ];
                        // For day-start cells, set grid to the order so Blade renders it
                        if (! $isFirst) {
                            $grid[$firstKey] = $wo;
                        }
                        $isFirst = false;

                        // Remaining keys in this day = continuation (skip rendering <td>)
                        for ($ci = 1; $ci < count($dateKeys); $ci++) {
                            $spans[$dateKeys[$ci]] = ['order' => $wo, 'type' => 'cont', 'rowspan' => 0];
                            $grid[$dateKeys[$ci]] = '__span__';
                        }
                    }
                }

                $weekLines[] = [
                    'line' => $line,
                    'orders' => $lineOrders,
                    'grid' => $grid,
                    'spans' => $spans,
                    'total_planned_qty' => $lineOrders->sum('planned_qty'),
                    'total_shifts' => $capacity,
                    'capacity' => $capacity,
                    'used_slots' => $usedSlots,
                    'load_percent' => $loadPercent,
                    'free_slots_percent' => 100 - $loadPercent,
                ];
            }

            $totalOrders = collect($weekLines)->sum('used_slots');
            $totalCapacity = collect($weekLines)->sum('capacity');
            $totalLoad = $totalCapacity > 0 ? min(100, round(($totalOrders / $totalCapacity) * 100)) : 0;

            $weeks[] = [
                'number' => $weekNumber,
                'start' => $weekStart,
                'end' => $weekEnd,
                'label' => __('Week') . ' ' . $weekNumber,
                'date_range' => $weekStart->format('d.m') . ' - ' . $weekEnd->format('d.m'),
                'lines' => $weekLines,
                'total_orders' => $totalOrders,
                'total_load_percent' => $totalLoad,
                'free_slots_percent' => 100 - $totalLoad,
            ];

            $cursor->addWeek();
        }

        return $weeks;
    }

    private function buildDailyData(Carbon $start, Carbon $end, $lines, $workOrders, int $shiftsPerDay, bool $showWeekends): array
    {
        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if (! $showWeekends && $cursor->isWeekend()) {
                $cursor->addDay();

                continue;
            }

            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $dayLines = [];
            foreach ($lines as $line) {
                $lineOrders = $workOrders->filter(function ($wo) use ($line, $dayStart, $dayEnd) {
                    if ($wo->line_id !== $line->id) {
                        return false;
                    }
                    if ($wo->due_date) {
                        return $wo->due_date->between($dayStart, $dayEnd);
                    }

                    return false;
                })->values();

                $capacity = $shiftsPerDay;
                $usedSlots = $lineOrders->count();
                $loadPercent = $capacity > 0 ? min(100, round(($usedSlots / $capacity) * 100)) : 0;

                $dayLines[] = [
                    'line' => $line,
                    'orders' => $lineOrders,
                    'total_planned_qty' => $lineOrders->sum('planned_qty'),
                    'capacity' => $capacity,
                    'used_slots' => $usedSlots,
                    'load_percent' => $loadPercent,
                    'free_slots_percent' => 100 - $loadPercent,
                ];
            }

            $totalOrders = collect($dayLines)->sum('used_slots');
            $totalCapacity = collect($dayLines)->sum('capacity');
            $totalLoad = $totalCapacity > 0 ? min(100, round(($totalOrders / $totalCapacity) * 100)) : 0;

            $days[] = [
                'date' => $dayStart,
                'label' => $dayStart->translatedFormat('D d.m'),
                'lines' => $dayLines,
                'total_orders' => $totalOrders,
                'total_load_percent' => $totalLoad,
                'free_slots_percent' => 100 - $totalLoad,
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Build the per-line layout for the hourly (minute-level) view of a single
     * day. Work orders that have minute planning (`planned_start_at` +
     * `planned_end_at`) are positioned absolutely on a 24h * 60min timeline;
     * orders that only have a legacy `due_date` are rendered as a fixed
     * 1-hour block at the top of the day so they remain visible until they
     * get minute timestamps assigned.
     *
     * Cross-midnight orders are clamped to the visible day (0..1440 min); the
     * actual end timestamp is left untouched in the model. Conflict detection
     * is per-line pairwise overlap on the clamped minute range.
     */
    private function buildHourlyData(Carbon $start, $lines, $workOrders, int $slotMinutes, bool $showWeekends): array
    {
        $dayStart = $start->copy()->startOfDay();
        $dayEnd = $start->copy()->endOfDay();
        $minutesPerDay = 24 * 60;
        $slotsPerDay = (int) ($minutesPerDay / $slotMinutes);

        // Filter WOs that are relevant to this day: either minute-planned and
        // overlapping the day, or legacy with due_date inside the day.
        $relevant = $workOrders->filter(function ($wo) use ($dayStart, $dayEnd) {
            if ($wo->planned_start_at && $wo->planned_end_at) {
                return $wo->planned_start_at->lt($dayEnd) && $wo->planned_end_at->gt($dayStart);
            }

            return $wo->due_date && $wo->due_date->between($dayStart, $dayEnd);
        });

        $lineRows = [];
        foreach ($lines as $line) {
            $lineOrders = $relevant->where('line_id', $line->id)->values();

            // Compute minute layout for each order on this line. Cross-midnight
            // spans are clamped to [0, minutesPerDay] for rendering only.
            $layouts = $lineOrders->map(function ($wo) use ($dayStart, $minutesPerDay) {
                $hasMinute = $wo->planned_start_at && $wo->planned_end_at;
                if ($hasMinute) {
                    $startMin = max(0, (int) $dayStart->diffInMinutes($wo->planned_start_at, false));
                    $endMin = min($minutesPerDay, (int) $dayStart->diffInMinutes($wo->planned_end_at, false));
                } else {
                    // Legacy WO with only due_date: render as a 1h block at the
                    // top of the day so it stays visible until rescheduled.
                    $startMin = 0;
                    $endMin = 60;
                }

                return [
                    'wo' => $wo,
                    'start_minute' => $startMin,
                    'end_minute' => $endMin,
                    'duration_minutes' => max(0, $endMin - $startMin),
                    'is_legacy' => ! $hasMinute,
                ];
            })->values();

            // Pairwise overlap detection on the clamped minute range. A pair
            // (i, j) with i < j is in conflict when their minute intervals
            // intersect with strictly positive duration.
            $conflicts = [];
            $count = $layouts->count();
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $layouts[$i];
                    $b = $layouts[$j];
                    if ($a['start_minute'] < $b['end_minute'] && $b['start_minute'] < $a['end_minute']) {
                        $conflicts[$a['wo']->id] = true;
                        $conflicts[$b['wo']->id] = true;
                    }
                }
            }

            $layouts = $layouts->map(function ($l) use ($conflicts) {
                $l['has_conflict'] = isset($conflicts[$l['wo']->id]);

                return $l;
            });

            $usedMinutes = (int) $layouts->sum('duration_minutes');
            $loadPercent = $minutesPerDay > 0
                ? min(100, (int) round(($usedMinutes / $minutesPerDay) * 100))
                : 0;

            $lineRows[] = [
                'line' => $line,
                'orders' => $layouts,
                'used_minutes' => $usedMinutes,
                'capacity_minutes' => $minutesPerDay,
                'load_percent' => $loadPercent,
                'free_minutes_percent' => 100 - $loadPercent,
            ];
        }

        return [
            'date' => $dayStart,
            'label' => $dayStart->translatedFormat('l, d F Y'),
            'is_weekend' => $dayStart->isWeekend(),
            'show_weekends' => $showWeekends,
            'slot_minutes' => $slotMinutes,
            'slots_per_day' => $slotsPerDay,
            'minutes_per_day' => $minutesPerDay,
            'lines' => $lineRows,
        ];
    }

    private function buildMonthlyData(Carbon $start, Carbon $end, $lines, $workOrders, int $shiftsPerDay, bool $showWeekends): array
    {
        $months = [];
        $cursor = $start->copy()->startOfMonth();
        $daysInWeek = $showWeekends ? 7 : 5;

        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            // Count working days in month
            $workingDays = 0;
            $dayCursor = $monthStart->copy();
            while ($dayCursor->lte($monthEnd)) {
                if ($showWeekends || ! $dayCursor->isWeekend()) {
                    $workingDays++;
                }
                $dayCursor->addDay();
            }

            $monthLines = [];
            foreach ($lines as $line) {
                $lineOrders = $workOrders->filter(function ($wo) use ($line, $monthStart, $monthEnd) {
                    if ($wo->line_id !== $line->id) {
                        return false;
                    }
                    if ($wo->due_date) {
                        return $wo->due_date->between($monthStart, $monthEnd);
                    }

                    return $wo->month_number === $monthStart->month;
                })->values();

                $capacity = $shiftsPerDay * $workingDays;
                $usedSlots = $lineOrders->count();
                $loadPercent = $capacity > 0 ? min(100, round(($usedSlots / $capacity) * 100)) : 0;

                $monthLines[] = [
                    'line' => $line,
                    'orders' => $lineOrders,
                    'total_planned_qty' => $lineOrders->sum('planned_qty'),
                    'capacity' => $capacity,
                    'used_slots' => $usedSlots,
                    'load_percent' => $loadPercent,
                    'free_slots_percent' => 100 - $loadPercent,
                ];
            }

            $totalOrders = collect($monthLines)->sum('used_slots');
            $totalCapacity = collect($monthLines)->sum('capacity');
            $totalLoad = $totalCapacity > 0 ? min(100, round(($totalOrders / $totalCapacity) * 100)) : 0;

            $months[] = [
                'month' => $monthStart->month,
                'year' => $monthStart->year,
                'start' => $monthStart,
                'end' => $monthEnd,
                'label' => $monthStart->translatedFormat('F Y'),
                'lines' => $monthLines,
                'total_orders' => $totalOrders,
                'total_load_percent' => $totalLoad,
                'free_slots_percent' => 100 - $totalLoad,
            ];

            $cursor->addMonth();
        }

        return $months;
    }
}
