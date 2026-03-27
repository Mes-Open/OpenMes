<?php

namespace Tests\Feature;

use App\Models\AnomalyReason;
use App\Models\DowntimeEvent;
use App\Models\ProductionCycle;
use App\Models\QualityEvent;
use App\Models\Shift;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Analytics\LossAnalysisService;
use App\Services\Analytics\MicroStopService;
use App\Services\Analytics\ProductionIntelligenceService;
use App\Services\Analytics\TimeModelService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndustrialValidationTest extends TestCase
{
    use RefreshDatabase;

    protected TimeModelService $timeModelService;
    protected ProductionIntelligenceService $piService;
    protected LossAnalysisService $lossAnalysisService;
    protected MicroStopService $microStopService;
    protected \App\Services\Analytics\IndustrialPerformanceService $industrialPerformanceService;
    protected \App\Services\Analytics\TrendAnalysisService $trendAnalysisService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timeModelService = $this->app->make(TimeModelService::class);
        $this->piService = $this->app->make(ProductionIntelligenceService::class);
        $this->lossAnalysisService = $this->app->make(LossAnalysisService::class);
        $this->microStopService = $this->app->make(MicroStopService::class);
        $this->industrialPerformanceService = $this->app->make(\App\Services\Analytics\IndustrialPerformanceService::class);
        $this->trendAnalysisService = $this->app->make(\App\Services\Analytics\TrendAnalysisService::class);
    }

    /**
     * 1. EVENT MODEL VALIDATION
     */
    public function test_machine_event_timeline_reconstruction()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();

        $eventStore = $this->app->make(\App\Services\EventStore\MachineEventStore::class);

        // Simulate a shift via MachineEventStore
        // Note: record() uses now() for timestamp, so we'll mock Carbon::now() for precise testing
        Carbon::setTestNow($start);
        $eventStore->record($workstation, 'STATE_CHANGE', ['state' => 'RUNNING']);

        Carbon::setTestNow($start->copy()->addSeconds(3600));
        $eventStore->record($workstation, 'STATE_CHANGE', ['state' => 'IDLE']);

        Carbon::setTestNow($start->copy()->addSeconds(4200));
        $eventStore->record($workstation, 'STATE_CHANGE', ['state' => 'RUNNING']);

        Carbon::setTestNow($start->copy()->addSeconds(7800));
        $eventStore->record($workstation, 'STATE_CHANGE', ['state' => 'FAULTED']);

        Carbon::setTestNow($start->copy()->addSeconds(9000));
        $eventStore->record($workstation, 'STATE_CHANGE', ['state' => 'RUNNING']);

        // Verify replay / reconstruction
        $this->assertEquals('RUNNING', $eventStore->replay($workstation, $start->copy()->addSeconds(1800)));
        $this->assertEquals('IDLE', $eventStore->replay($workstation, $start->copy()->addSeconds(3900)));
        $this->assertEquals('FAULTED', $eventStore->replay($workstation, $start->copy()->addSeconds(8000)));
        $this->assertEquals('RUNNING', $eventStore->replay($workstation, $start->copy()->addSeconds(10000)));

        Carbon::setTestNow(); // Reset
    }

    protected function getWorkstationStateAt(Workstation $workstation, Carbon $timestamp)
    {
        return WorkstationState::where('workstation_id', $workstation->id)
            ->where('started_at', '<=', $timestamp)
            ->where('ended_at', '>=', $timestamp)
            ->first()?->state;
    }

    /**
     * 2. TIME MODEL & 4. KPI ENGINE VALIDATION
     */
    public function test_time_model_consistency_and_kpis()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addHours(8); // 8 hour shift = 28,800 seconds

        // 1. Planned Downtime: 30 min break = 1,800 seconds
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(4),
            'ended_at' => $start->copy()->addHours(4)->addMinutes(30),
            'downtime_category' => 'Planned',
            'duration_minutes' => 30,
        ]);

        // Planned Production Time should be 28,800 - 1,800 = 27,000 seconds

        // 2. Unplanned Downtime: 1 hour breakdown = 3,600 seconds
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(1),
            'ended_at' => $start->copy()->addHours(2),
            'downtime_category' => 'Unplanned',
            'duration_minutes' => 60,
        ]);

        // Operating Time should be 27,000 - 3,600 = 23,400 seconds

        // 3. Performance: 1000 units, ideal cycle 20s.
        // Net Run Time = 1000 * 20 = 20,000 seconds
        // Performance = 20,000 / 23,400 = 0.8547 (85.47%)
        for ($i = 0; $i < 1000; $i++) {
            ProductionCycle::create([
                'workstation_id' => $workstation->id,
                'started_at' => $start->copy()->addSeconds($i * 20), // This is not realistic for timing but sufficient for calculation
                'ended_at' => $start->copy()->addSeconds(($i + 1) * 20),
                'cycle_time_seconds' => 20,
                'ideal_cycle_time_seconds' => 20,
            ]);
        }

        // 4. Quality: 50 scrap units
        // Good units = 950.
        // Fully Productive Time = 950 * 20 = 19,000 seconds.
        // Quality = 19,000 / 20,000 = 0.95 (95%)
        QualityEvent::create([
            'workstation_id' => $workstation->id,
            'event_type' => 'SCRAP',
            'quantity' => 50,
            'occurred_at' => $start->copy()->addHours(2),
        ]);

        $kpis = $this->piService->calculateKpis($workstation, $start, $end);
        $timeModel = $kpis['time_model'];

        // Assertions
        $this->assertEquals(28800, $timeModel['calendar_time_secs']);
        $this->assertEquals(27000, $timeModel['planned_production_time_secs']);
        $this->assertEquals(23400, $timeModel['operating_time_secs']);
        $this->assertEquals(20000, $timeModel['net_run_time_secs']);
        $this->assertEquals(19000, $timeModel['fully_productive_time_secs']);

        // KPI Assertions
        $this->assertEqualsWithDelta(23400/27000, $kpis['availability'], 0.0001);
        $this->assertEqualsWithDelta(20000/23400, $kpis['performance'], 0.0001);
        $this->assertEqualsWithDelta(19000/20000, $kpis['quality'], 0.0001);

        $expectedOee = (23400/27000) * (20000/23400) * (19000/20000); // Should be 19000/27000 = 0.7037
        $this->assertEqualsWithDelta($expectedOee, $kpis['oee'], 0.0001);

        // 5. TOTAL LOSS TIME & PERCENTAGE VALIDATION
        $totalLosses = array_sum($timeModel['losses']);
        // Note: some losses overlap or are subsets, so simple sum might not work if not careful.
        // Let's check: productive time + all losses = total time
        // Total Time = Fully Productive Time + Quality Loss + Performance Loss + Unplanned Downtime + Planned Downtime
        $calculatedTotal = $timeModel['fully_productive_time_secs'] +
                           $timeModel['losses']['quality_loss_secs'] +
                           $timeModel['losses']['performance_loss_secs'] +
                           $timeModel['losses']['unplanned_downtime_secs'] +
                           $timeModel['losses']['planned_downtime_secs'];

        $this->assertEquals(28800, $calculatedTotal, "Total time consistency failed");

        // Test OeeCalculationService specifically
        $oeeService = $this->app->make(\App\Services\Analytics\OeeCalculationService::class);
        $oeeResult = $oeeService->calculateOee($workstation, $start, $end);

        // OEE service uses actual produced vs quality.
        // totalProduced = 1000, scrap = 50. Quality = 0.95.
        $this->assertEqualsWithDelta(0.95, $oeeResult['quality'], 0.0001);
    }

    /**
     * 6. MICRO-STOP DETECTION VALIDATION
     */
    public function test_micro_stop_detection()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();

        // Normal cycle
        ProductionCycle::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start,
            'ended_at' => $start->copy()->addSeconds(20),
            'cycle_time_seconds' => 20,
            'ideal_cycle_time_seconds' => 20,
        ]);

        // Micro-stop cycle (extended duration)
        ProductionCycle::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addSeconds(30),
            'ended_at' => $start->copy()->addSeconds(150), // 120s actual vs 20s ideal. 100s loss > 60s threshold.
            'cycle_time_seconds' => 120,
            'ideal_cycle_time_seconds' => 20,
        ]);

        // Gap between cycles (NOT a micro-stop as it exceeds threshold of 60s)
        ProductionCycle::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addSeconds(220), // 70s gap from previous cycle end (150s)
            'ended_at' => $start->copy()->addSeconds(240),
            'cycle_time_seconds' => 20,
            'ideal_cycle_time_seconds' => 20,
        ]);

        $result = $this->microStopService->detectMicroStops($workstation, $start, $start->copy()->addMinutes(10), 60);

        // Debug
        // dump($result);

        $this->assertEquals(2, $result['count']); // 1 cycle extension (100s loss), 1 inter-cycle gap (10s)
        $this->assertEquals(110, $result['total_duration_secs']);
    }

    /**
     * 7. PARETO ANALYSIS VALIDATION
     */
    public function test_pareto_analysis()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addHours(8);

        $reasonA = AnomalyReason::create(['name' => 'Reason A', 'code' => 'A']);
        $reasonB = AnomalyReason::create(['name' => 'Reason B', 'code' => 'B']);
        $reasonC = AnomalyReason::create(['name' => 'Reason C', 'code' => 'C']);

        // Reason A: 100 mins
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'anomaly_reason_id' => $reasonA->id,
            'started_at' => $start->copy(),
            'ended_at' => $start->copy()->addMinutes(100),
            'duration_minutes' => 100,
            'downtime_category' => 'Unplanned',
        ]);

        // Reason B: 60 mins
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'anomaly_reason_id' => $reasonB->id,
            'started_at' => $start->copy()->addHours(2),
            'ended_at' => $start->copy()->addHours(2)->addMinutes(60),
            'duration_minutes' => 60,
            'downtime_category' => 'Unplanned',
        ]);

        // Reason C: 40 mins
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'anomaly_reason_id' => $reasonC->id,
            'started_at' => $start->copy()->addHours(4),
            'ended_at' => $start->copy()->addHours(4)->addMinutes(40),
            'duration_minutes' => 40,
            'downtime_category' => 'Unplanned',
        ]);

        $pareto = $this->lossAnalysisService->getParetoAnalysis($workstation, $start, $end);

        $this->assertEquals('Reason A', $pareto[0]['cause']);
        $this->assertEquals(50.0, $pareto[0]['percentage']); // 100/200

        $this->assertEquals('Reason B', $pareto[1]['cause']);
        $this->assertEquals(30.0, $pareto[1]['percentage']); // 60/200

        $this->assertEquals('Reason C', $pareto[2]['cause']);
        $this->assertEquals(20.0, $pareto[2]['percentage']); // 40/200

        $this->assertEquals(100.0, $pareto[2]['cumulative_percentage']);
    }

    /**
     * 3. LOSS CLASSIFICATION VALIDATION
     */
    public function test_loss_classification()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addHours(8);

        // Breakdown (Availability Loss)
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'downtime_category' => 'Breakdown',
            'started_at' => $start->copy()->addHour(),
            'ended_at' => $start->copy()->addHour()->addMinutes(30),
            'duration_minutes' => 30,
        ]);

        // Setup (Availability Loss)
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'downtime_category' => 'Setup',
            'started_at' => $start->copy()->addHours(2),
            'ended_at' => $start->copy()->addHours(2)->addMinutes(20),
            'duration_minutes' => 20,
        ]);

        $losses = $this->lossAnalysisService->categorizeLosses($workstation, $start, $end);

        $this->assertEquals(1800, $losses['availability_losses']['breakdowns']); // 30m
        $this->assertEquals(1200, $losses['availability_losses']['setup_adjustments']); // 20m
    }

    /**
     * 8. TOOL-LEVEL ANALYSIS & 10. OPERATOR PERFORMANCE VALIDATION
     */
    public function test_tool_and_operator_tracking()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();

        $toolA = \App\Models\Tool::create(['code' => 'TOOL-A', 'name' => 'Tool A']);
        $operatorA = \App\Models\Worker::create(['code' => 'OP-A', 'name' => 'Operator A']);
        $operatorB = \App\Models\Worker::create(['code' => 'OP-B', 'name' => 'Operator B']);

        // Operator A cycles
        for ($i = 0; $i < 10; $i++) {
            ProductionCycle::create([
                'workstation_id' => $workstation->id,
                'worker_id' => $operatorA->id,
                'tool_id' => $toolA->id,
                'started_at' => $start->copy()->addSeconds($i * 20),
                'ended_at' => $start->copy()->addSeconds(($i * 20) + 20),
                'cycle_time_seconds' => 20,
                'ideal_cycle_time_seconds' => 20,
            ]);
        }

        // Operator B cycles (Slower)
        for ($i = 10; $i < 20; $i++) {
            ProductionCycle::create([
                'workstation_id' => $workstation->id,
                'worker_id' => $operatorB->id,
                'tool_id' => $toolA->id,
                'started_at' => $start->copy()->addSeconds($i * 30),
                'ended_at' => $start->copy()->addSeconds(($i * 30) + 30),
                'cycle_time_seconds' => 30,
                'ideal_cycle_time_seconds' => 20,
            ]);
        }

        $cycles = ProductionCycle::where('workstation_id', $workstation->id)->get();

        $this->assertEquals(10, $cycles->where('worker_id', $operatorA->id)->count());
        $this->assertEquals(10, $cycles->where('worker_id', $operatorB->id)->count());
        $this->assertEquals(20, $cycles->where('tool_id', $toolA->id)->count());

        $avgA = $cycles->where('worker_id', $operatorA->id)->avg('cycle_time_seconds');
        $avgB = $cycles->where('worker_id', $operatorB->id)->avg('cycle_time_seconds');

        $this->assertEquals(20, $avgA);
        $this->assertEquals(30, $avgB);

        // Test IndustrialPerformanceService integration
        $toolA->update(['max_cycles' => 100, 'wear_percentage' => 5.0]);
        $performance = $this->industrialPerformanceService->analyzeToolPerformance($toolA, $start, $start->copy()->addHours(12));

        $this->assertEquals(20, $performance['total_cycles']);
        $this->assertEquals(20.0, $performance['wear_delta_pct']); // (20/100) * 100

        $opPerformance = $this->industrialPerformanceService->analyzeOperatorPerformance($start, $start->copy()->addHours(12));
        $this->assertEquals(10, $opPerformance[0]['total_produced']); // Op A
        $this->assertEquals(100.0, $opPerformance[0]['efficiency_pct']); // 200/200

        $this->assertEquals(10, $opPerformance[1]['total_produced']); // Op B
        $this->assertEqualsWithDelta(66.67, $opPerformance[1]['efficiency_pct'], 0.01); // 200/300
    }

    /**
     * 11. DATA INTEGRITY & EDGE CASES (Overlapping events)
     */
    public function test_overlapping_downtime_events_merge()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();

        // Event 1: 08:00 to 09:00
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(8),
            'ended_at' => $start->copy()->addHours(9),
            'downtime_category' => 'Unplanned',
        ]);

        // Event 2: 08:30 to 09:30 (Overlaps Event 1)
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(8)->addMinutes(30),
            'ended_at' => $start->copy()->addHours(9)->addMinutes(30),
            'downtime_category' => 'Unplanned',
        ]);

        // Total downtime should be 90 minutes (1.5 hours) not 120 minutes.
        $kpis = $this->piService->calculateKpis($workstation, $start, $start->copy()->addHours(12));
        $this->assertEquals(5400, $kpis['time_model']['losses']['unplanned_downtime_secs']);
    }

    /**
     * 11. DATA INTEGRITY & EDGE CASES (Out-of-order events)
     */
    public function test_out_of_order_events_handling()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();

        // Event recorded later but happened earlier
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(4),
            'ended_at' => $start->copy()->addHours(5),
            'downtime_category' => 'Unplanned',
        ]);

        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(1),
            'ended_at' => $start->copy()->addHours(2),
            'downtime_category' => 'Unplanned',
        ]);

        $kpis = $this->piService->calculateKpis($workstation, $start, $start->copy()->addHours(8));

        // Both should be counted regardless of insertion order
        $this->assertEquals(7200, $kpis['time_model']['losses']['unplanned_downtime_secs']);
    }

    /**
     * 11. DATA INTEGRITY & EDGE CASES (Missing/Null ended_at timestamps)
     */
    public function test_open_ended_downtime_handling()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();

        // Event with no ended_at (ongoing downtime)
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(1),
            'ended_at' => null,
            'downtime_category' => 'Unplanned',
        ]);

        Carbon::setTestNow($start->copy()->addHours(5));
        $kpis = $this->piService->calculateKpis($workstation, $start, $start->copy()->addHours(8));

        // Duration should be calculated up to min(now, rangeEnd)
        // rangeEnd is 8h, now is 5h. So duration should be 5h - 1h = 4h (14400s)
        $this->assertEquals(14400, $kpis['time_model']['losses']['unplanned_downtime_secs']);

        Carbon::setTestNow();
    }

    /**
     * 11. DATA INTEGRITY & EDGE CASES (Machine offline periods)
     */
    public function test_machine_offline_period_handling()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();

        // Machine was "offline" / No power from 2h to 4h
        // In MES, this is typically recorded as a specific downtime category or just gap in events.
        // If it's a gap in events, PPT remains the same unless Planned Downtime is recorded.
        // If it's recorded as "Offline" (Planned or Unplanned depending on reason)
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(2),
            'ended_at' => $start->copy()->addHours(4),
            'downtime_category' => 'Planned', // Let's say it was scheduled maintenance
        ]);

        $kpis = $this->piService->calculateKpis($workstation, $start, $start->copy()->addHours(8));

        $this->assertEquals(7200, $kpis['time_model']['losses']['planned_downtime_secs']);
        $this->assertEquals(21600, $kpis['time_model']['planned_production_time_secs']); // 28800 - 7200
    }

    /**
     * 9. TREND ANALYSIS VALIDATION
     */
    public function test_trend_analysis_aggregation()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addDays(3);

        // Day 1: 100% OEE
        ProductionCycle::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addHours(1),
            'ended_at' => $start->copy()->addHours(1)->addSeconds(10),
            'cycle_time_seconds' => 10,
            'ideal_cycle_time_seconds' => 10,
        ]);

        // Day 2: 50% OEE (10s run, 10s unplanned downtime)
        DowntimeEvent::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addDays(1)->addHours(1),
            'ended_at' => $start->copy()->addDays(1)->addHours(1)->addSeconds(10),
            'downtime_category' => 'Unplanned',
        ]);
        ProductionCycle::create([
            'workstation_id' => $workstation->id,
            'started_at' => $start->copy()->addDays(1)->addHours(2),
            'ended_at' => $start->copy()->addDays(1)->addHours(2)->addSeconds(10),
            'cycle_time_seconds' => 10,
            'ideal_cycle_time_seconds' => 10,
        ]);

        $trends = $this->trendAnalysisService->getTrends($workstation, $start, $end, 'daily');

        $this->assertCount(3, $trends);
        // On Day 1, only one cycle. Calendar time is 24h. PPT = 24h. OpTime = 24h. NRT = 10s. FPT = 10s.
        // OEE = FPT / PPT = 10 / 86400.
        $this->assertGreaterThan(0, $trends[0]['oee']);
        $this->assertGreaterThan(0, $trends[1]['oee']);
        $this->assertEquals(0, $trends[2]['oee']);
    }

    /**
     * 12. PERFORMANCE TEST (High frequency events)
     */
    public function test_kpi_engine_performance_with_many_events()
    {
        $line = \App\Models\Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $start = Carbon::now()->startOfDay();

        // Inject 5000 production cycles
        $cycles = [];
        for ($i = 0; $i < 5000; $i++) {
            $cycles[] = [
                'workstation_id' => $workstation->id,
                'started_at' => $start->copy()->addSeconds($i * 5)->toDateTimeString(),
                'ended_at' => $start->copy()->addSeconds(($i * 5) + 5)->toDateTimeString(),
                'cycle_time_seconds' => 5,
                'ideal_cycle_time_seconds' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($cycles) >= 1000) {
                ProductionCycle::insert($cycles);
                $cycles = [];
            }
        }
        if (!empty($cycles)) {
            ProductionCycle::insert($cycles);
        }

        $startTime = microtime(true);
        $kpis = $this->piService->calculateKpis($workstation, $start, $start->copy()->addHours(12));
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertLessThan(1.0, $executionTime, "KPI calculation took too long: {$executionTime}s");
        $this->assertEquals(5000, $kpis['time_model']['net_run_time_secs'] / 5);
    }
}
