<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DashboardWidget;
use App\Models\Inspection;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\OeeRecord;
use App\Services\Production\OeeCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin dashboard, served as an Inertia/React page backed by Electric SQL.
 *
 * Row-level data (work orders, issues, lines, OEE records, issue types) is
 * synced live to the browser via shape subscriptions — see
 * `App\Sync\Shapes\*` and `app/Http/Controllers/Api/ShapeProxyController.php`.
 *
 * This controller stays responsible for:
 *   - Kicking the OEE calculation (15-min cache window) so synced records
 *     reflect today's production.
 *   - Computing heavy aggregates (inbound QC pass rates, materials KPIs)
 *     that don't map cleanly to Electric's per-row sync.
 *   - Minting a short-lived Sanctum token so the React client can hit the
 *     proxy without the full SPA cookie setup. TODO: switch to Sanctum's
 *     stateful (cookie) mode once we're ready to drop bearer tokens.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // Side-effect: keep OEE numbers fresh. Cached for 15 minutes — the
        // calculation itself is expensive and rerunning it on every dashboard
        // load would be wasteful. Synced OeeRecord rows below pick up the
        // updates automatically via Electric.
        Cache::remember('oee_calculated_'.today()->toDateString(), 900, function () {
            $svc = app(OeeCalculationService::class);
            $svc->calculateAll(today());
            $svc->calculateAll(\Carbon\Carbon::yesterday());

            return true;
        });

        $enabledWidgets = DashboardWidget::enabled()->pluck('widget_id')->toArray();
        $widgetOrder = DashboardWidget::where('enabled', true)
            ->orderBy('sort_order')
            ->pluck('widget_id')
            ->toArray();

        return Inertia::render('admin/Dashboard', [
            // No token: the gatekeeper authenticates via the session cookie
            // (Sanctum SPA stateful mode).
            'enabledWidgets' => $enabledWidgets,
            'widgetOrder' => $widgetOrder,
            // Aggregations that don't map cleanly to row-level sync. Inertia
            // partial reloads (`router.reload({only: [...]})`) can refresh
            // them on demand once we wire that up.
            'inboundQcStats' => fn () => $this->inboundQcStats($enabledWidgets),
            'materialsStats' => fn () => $this->materialsStats($enabledWidgets),
        ]);
    }

    private function inboundQcStats(array $enabledWidgets): ?array
    {
        if (! in_array('inbound_qc_overview', $enabledWidgets, true)) {
            return null;
        }

        $since = now()->subDays(29)->startOfDay();
        $base = Inspection::where('started_at', '>=', $since);
        $completed = (clone $base)->whereIn('status', ['pass', 'fail', 'conditional_pass'])->count();
        $passed = (clone $base)->where('status', 'pass')->count();

        return [
            'pending' => Inspection::where('status', 'pending')->count(),
            'completed_30d' => $completed,
            'failed_30d' => (clone $base)->where('status', 'fail')->count(),
            'conditional_30d' => (clone $base)->where('status', 'conditional_pass')->count(),
            'pass_rate_30d' => $completed > 0 ? round(($passed / $completed) * 100, 1) : null,
        ];
    }

    private function materialsStats(array $enabledWidgets): ?array
    {
        if (! in_array('materials_overview', $enabledWidgets, true)) {
            return null;
        }

        return [
            'low_stock_count' => Material::where('is_active', true)
                ->whereNotNull('min_stock_level')
                ->whereColumn('stock_quantity', '<=', 'min_stock_level')
                ->count(),
            'expiring_count' => MaterialLot::where('status', MaterialLot::STATUS_RELEASED)
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [today(), today()->addDays(30)])
                ->count(),
            'reserved_total' => (float) Material::sum('reserved_quantity'),
            'lots_total' => MaterialLot::where('status', MaterialLot::STATUS_RELEASED)->count(),
            'quarantined_count' => MaterialLot::where('status', MaterialLot::STATUS_QUARANTINE)->count(),
        ];
    }
}
