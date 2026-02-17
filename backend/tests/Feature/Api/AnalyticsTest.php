<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\IssueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Line $line;
    protected ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->line = Line::factory()->create();
        $this->productType = ProductType::factory()->create();
    }

    public function test_can_get_overview_statistics()
    {
        // Create test data
        WorkOrder::factory()->count(5)->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'status' => 'IN_PROGRESS',
        ]);

        WorkOrder::factory()->count(3)->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'status' => 'DONE',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/overview?line_id=' . $this->line->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_work_orders',
                    'active_work_orders',
                    'completed_work_orders',
                    'blocked_work_orders',
                    'total_batches',
                    'active_batches',
                    'open_issues',
                    'critical_issues',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(8, $data['total_work_orders']);
        $this->assertEquals(5, $data['active_work_orders']);
        $this->assertEquals(3, $data['completed_work_orders']);
    }

    public function test_can_get_production_by_line()
    {
        // Create work orders for different lines
        $line1 = Line::factory()->create(['name' => 'Line 1']);
        $line2 = Line::factory()->create(['name' => 'Line 2']);

        WorkOrder::factory()->count(5)->create([
            'line_id' => $line1->id,
            'product_type_id' => $this->productType->id,
            'status' => 'DONE',
        ]);

        WorkOrder::factory()->count(3)->create([
            'line_id' => $line2->id,
            'product_type_id' => $this->productType->id,
            'status' => 'IN_PROGRESS',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/production-by-line');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'line_id',
                        'line_name',
                        'line_code',
                        'total_work_orders',
                        'completed',
                        'in_progress',
                        'pending',
                        'blocked',
                        'total_planned_qty',
                        'total_produced_qty',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_can_get_cycle_time_statistics()
    {
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
        ]);

        $batch = Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'status' => 'DONE',
            'started_at' => Carbon::now()->subHours(5),
            'completed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/cycle-time?line_id=' . $this->line->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'batches',
                    'average_cycle_time_minutes',
                    'average_cycle_time_hours',
                    'total_batches',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['total_batches']);
        $this->assertGreaterThan(0, $data['average_cycle_time_minutes']);
    }

    public function test_can_get_throughput_metrics()
    {
        WorkOrder::factory()->count(3)->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'produced_qty' => 100,
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/throughput?line_id=' . $this->line->id . '&days=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'daily_production',
                    'average_daily_throughput',
                    'period_start',
                    'period_end',
                ],
            ]);
    }

    public function test_can_get_issue_statistics()
    {
        $issueType = IssueType::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
        ]);

        Issue::factory()->count(3)->create([
            'work_order_id' => $workOrder->id,
            'issue_type_id' => $issueType->id,
            'reported_at' => Carbon::now()->subHours(5),
            'resolved_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/issue-stats?line_id=' . $this->line->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'by_type',
                    'by_status',
                    'average_resolution_time_minutes',
                    'average_resolution_time_hours',
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['average_resolution_time_minutes']);
    }

    public function test_can_get_step_performance_metrics()
    {
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
        ]);

        $batch = Batch::factory()->create([
            'work_order_id' => $workOrder->id,
        ]);

        BatchStep::factory()->count(3)->create([
            'batch_id' => $batch->id,
            'status' => 'DONE',
            'duration_minutes' => 30,
            'name' => 'Test Step',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/step-performance?line_id=' . $this->line->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'step_name',
                        'average_duration_minutes',
                        'total_completions',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
    }

    public function test_overview_respects_line_filter()
    {
        $line2 = Line::factory()->create();

        WorkOrder::factory()->count(3)->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
        ]);

        WorkOrder::factory()->count(5)->create([
            'line_id' => $line2->id,
            'product_type_id' => $this->productType->id,
        ]);

        // Get stats for line 1 only
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/overview?line_id=' . $this->line->id);

        $data = $response->json('data');
        $this->assertEquals(3, $data['total_work_orders']);

        // Get stats for all lines
        $responseAll = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/overview');

        $dataAll = $responseAll->json('data');
        $this->assertEquals(8, $dataAll['total_work_orders']);
    }
}
