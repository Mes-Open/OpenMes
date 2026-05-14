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

        // Allow query string override for view mode
        if ($request->filled('view_mode') && in_array($request->view_mode, ['weekly', 'daily', 'monthly'])) {
            $viewMode = $request->view_mode;
        }

        // Calculate start date (default: current Monday)
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfWeek()
            : now()->startOfWeek();

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
            'monthly' => $this->buildMonthlyData($startDate, $rangeEnd, $lines, $workOrders, $shiftsPerDay, $showWeekends),
            default => $this->buildWeeklyData($startDate, $rangeEnd, $lines, $workOrders, $shiftsPerDay, $showWeekends),
        };

        // Navigation dates
        $navPrev = match ($viewMode) {
            'daily' => $startDate->copy()->subWeeks(2),
            'monthly' => $startDate->copy()->subMonths(3),
            default => $startDate->copy()->subWeeks($horizonWeeks),
        };
        $navNext = match ($viewMode) {
            'daily' => $startDate->copy()->addWeeks(2),
            'monthly' => $startDate->copy()->addMonths(3),
            default => $startDate->copy()->addWeeks($horizonWeeks),
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

        return view('admin.schedule.planner', compact(
            'data',
            'lines',
            'allLines',
            'shifts',
            'viewMode',
            'shiftsPerDay',
            'horizonWeeks',
            'showWeekends',
            'startDate',
            'rangeStart',
            'rangeEnd',
            'navPrev',
            'navNext',
            'backlogOrders',
        ));
    }

    public function updateOrder(Request $request, WorkOrder $workOrder)
    {
        $request->validate([
            'line_id' => 'nullable|exists:lines,id',
            'due_date' => 'nullable|date',
            'week_number' => 'nullable|integer|min:1|max:53',
            'shift_number' => 'nullable|integer|min:1|max:10',
        ]);

        $workOrder->update([
            'line_id' => $request->input('line_id') ?: null,
            'due_date' => $request->input('due_date') ?: null,
            'week_number' => $request->input('week_number') ?: null,
            'shift_number' => $request->input('shift_number') ?: null,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('Work order updated successfully.'),
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

    private function loadSettings(): array
    {
        $keys = [
            'schedule_view_mode',
            'schedule_shifts_per_day',
            'schedule_horizon_weeks',
            'schedule_show_weekends',
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

                $weekLines[] = [
                    'line' => $line,
                    'orders' => $lineOrders,
                    'grid' => $grid,
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
