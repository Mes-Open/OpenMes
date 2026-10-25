<?php

namespace App\Services\Dashboard;

use App\Models\Issue;
use App\Models\Material;
use App\Models\WorkOrder;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Carbon\Carbon;

/**
 * The summary strip at the bottom of the plant overview: work orders today,
 * materials below minimum, open non-conformances and the next order to start.
 *
 * "Today" is the plant's calendar day — TimezoneRegistry::apply() makes PHP's
 * default timezone the plant's, so midnight in PHP's default timezone is plant
 * midnight. A field whose module is switched off is null, and the page drops it.
 */
class PlantSummaryService
{
    /**
     * @return array{
     *     work_orders: array{running: int, pending: int},
     *     materials: ?array{below_min: int},
     *     non_conformances: ?array{open: int},
     *     next_order: ?array{id: int, order_no: string, planned_start_at: string, line_name: ?string, product_code: ?string}
     * }
     */
    public function summary(Carbon $now): array
    {
        return [
            'work_orders' => $this->workOrdersToday($now),
            'materials' => ModuleRegistry::isModuleEnabled('materials') ? ['below_min' => $this->materialsBelowMinimum()] : null,
            'non_conformances' => ModuleRegistry::isModuleEnabled('quality') ? ['open' => $this->openNonConformances()] : null,
            'next_order' => $this->nextOrder($now),
        ];
    }

    /**
     * Running = every order IN_PROGRESS now; pending = PENDING / ACCEPTED
     * orders planned to start during the plant's today.
     *
     * @return array{running: int, pending: int}
     */
    private function workOrdersToday(Carbon $now): array
    {
        $dayStart = $now->copy()->setTimezone(date_default_timezone_get())->startOfDay();

        return [
            'running' => WorkOrder::where('status', WorkOrder::STATUS_IN_PROGRESS)->count(),
            'pending' => WorkOrder::whereIn('status', [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED])
                ->where('planned_start_at', '>=', $dayStart)
                ->where('planned_start_at', '<', $dayStart->copy()->addDay())
                ->count(),
        ];
    }

    /** Same predicate as the dashboard's materialsStats.low_stock_count. */
    private function materialsBelowMinimum(): int
    {
        return Material::where('is_active', true)
            ->whereNotNull('min_stock_level')
            ->whereColumn('stock_quantity', '<=', 'min_stock_level')
            ->count();
    }

    /**
     * Open issues flagged as non-conformances (Issue::scopeNonConformance) —
     * not every open issue, unlike nonConformanceStats.open_total.
     *
     * Issue has no tenant scope; for a tenant user it is limited to issues
     * reached through that tenant's work orders or materials.
     */
    private function openNonConformances(): int
    {
        $query = Issue::open()->nonConformance();

        if ($this->tenantId() !== null) {
            $query->where(fn ($q) => $q->whereHas('workOrder')->orWhereHas('material'));
        }

        return $query->count();
    }

    /**
     * The not-yet-started order with the nearest planned start in the future.
     *
     * @return ?array{id: int, order_no: string, planned_start_at: string, line_name: ?string, product_code: ?string}
     */
    private function nextOrder(Carbon $now): ?array
    {
        $order = WorkOrder::with(['line:id,name', 'productType:id,code'])
            ->whereIn('status', [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED])
            ->where('planned_start_at', '>', $now)
            ->orderBy('planned_start_at')
            ->orderBy('id')
            ->first(['id', 'order_no', 'line_id', 'product_type_id', 'planned_start_at']);

        if ($order === null) {
            return null;
        }

        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'planned_start_at' => $order->planned_start_at->toIso8601String(),
            'line_name' => $order->line?->name,
            'product_code' => $order->productType?->code,
        ];
    }

    /** The tenant TenantScope filters by, if any. */
    private function tenantId(): ?int
    {
        return auth()->hasUser()
            ? auth()->user()?->tenant_id
            : app(TenantContext::class)->id();
    }
}
