<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workstation;
use App\Models\Tool;
use App\Traits\StandardApiResponse;
use App\Services\Analytics\ProductionIntelligenceService;
use App\Services\Analytics\LossAnalysisService;
use App\Services\Analytics\TrendAnalysisService;
use App\Services\Analytics\IndustrialPerformanceService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ProductionIntelligenceController extends Controller
{
    use StandardApiResponse;

    public function __construct(
        protected ProductionIntelligenceService $piService,
        protected LossAnalysisService $lossService,
        protected TrendAnalysisService $trendService,
        protected IndustrialPerformanceService $performanceService
    ) {}

    /**
     * Get OEE for a workstation.
     */
    public function getOee(Request $request, Workstation $workstation)
    {
        $startDate = Carbon::parse($request->query('start_date', now()->subDay()->toDateString()));
        $endDate = Carbon::parse($request->query('end_date', now()->toDateTimeString()));

        $kpis = $this->piService->calculateKpis($workstation, $startDate, $endDate);

        return $this->success($kpis);
    }

    /**
     * Get OOE for a workstation.
     */
    public function getOoe(Request $request, Workstation $workstation)
    {
        $startDate = Carbon::parse($request->query('start_date', now()->subDay()->toDateString()));
        $endDate = Carbon::parse($request->query('end_date', now()->toDateTimeString()));

        $kpis = $this->piService->calculateKpis($workstation, $startDate, $endDate);

        return $this->success([
            'ooe' => $kpis['ooe'],
            'workstation_id' => $workstation->id,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
        ]);
    }

    /**
     * Get TEEP for a workstation.
     */
    public function getTeep(Request $request, Workstation $workstation)
    {
        $startDate = Carbon::parse($request->query('start_date', now()->subDay()->toDateString()));
        $endDate = Carbon::parse($request->query('end_date', now()->toDateTimeString()));

        $kpis = $this->piService->calculateKpis($workstation, $startDate, $endDate);

        return $this->success([
            'teep' => $kpis['teep'],
            'workstation_id' => $workstation->id,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
        ]);
    }

    /**
     * Get detailed loss summary.
     */
    public function getLossSummary(Request $request, Workstation $workstation)
    {
        $startDate = Carbon::parse($request->query('start_date', now()->subDay()->toDateString()));
        $endDate = Carbon::parse($request->query('end_date', now()->toDateTimeString()));

        $losses = $this->lossService->categorizeLosses($workstation, $startDate, $endDate);

        return $this->success($losses);
    }

    /**
     * Get Pareto analysis of downtime causes.
     */
    public function getParetoAnalysis(Request $request, Workstation $workstation)
    {
        $startDate = Carbon::parse($request->query('start_date', now()->subDay()->toDateString()));
        $endDate = Carbon::parse($request->query('end_date', now()->toDateTimeString()));

        $pareto = $this->lossService->getParetoAnalysis($workstation, $startDate, $endDate);

        return $this->success($pareto);
    }

    /**
     * Get KPI trends.
     */
    public function getTrends(Request $request, Workstation $workstation)
    {
        $startDate = Carbon::parse($request->query('start_date', now()->subDays(7)->toDateString()));
        $endDate = Carbon::parse($request->query('end_date', now()->toDateTimeString()));
        $interval = $request->query('interval', 'daily');

        $trends = $this->trendService->getTrends($workstation, $startDate, $endDate, $interval);

        return $this->success($trends);
    }

    /**
     * Get tool performance analysis.
     */
    public function getToolPerformance(Request $request, Tool $tool)
    {
        $startDate = Carbon::parse($request->query('start_date', now()->subDays(30)->toDateString()));
        $endDate = Carbon::parse($request->query('end_date', now()->toDateTimeString()));

        $performance = $this->performanceService->analyzeToolPerformance($tool, $startDate, $endDate);

        return $this->success($performance);
    }
}
