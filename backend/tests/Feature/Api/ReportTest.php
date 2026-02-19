<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\IssueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ReportTest extends TestCase
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

    public function test_can_generate_production_summary_report()
    {
        WorkOrder::factory()->count(5)->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'status' => 'DONE',
            'planned_qty' => 100,
            'produced_qty' => 95,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/production-summary?' . http_build_query([
                'start_date' => Carbon::now()->subDays(7)->toDateString(),
                'end_date' => Carbon::now()->toDateString(),
                'line_id' => $this->line->id,
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start', 'end'],
                    'line',
                    'work_orders' => [
                        'total',
                        'completed',
                        'in_progress',
                        'pending',
                        'blocked',
                        'cancelled',
                    ],
                    'production' => [
                        'total_planned',
                        'total_produced',
                        'completion_rate',
                    ],
                    'by_product_type',
                    'generated_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(5, $data['work_orders']['total']);
        $this->assertEquals(500, $data['production']['total_planned']);
        $this->assertEquals(475, $data['production']['total_produced']);
    }

    public function test_production_summary_requires_dates()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/production-summary');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_can_generate_batch_completion_report()
    {
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
        ]);

        Batch::factory()->count(3)->create([
            'work_order_id' => $workOrder->id,
            'status' => 'DONE',
            'target_qty' => 50,
            'produced_qty' => 50,
            'started_at' => Carbon::now()->subHours(5),
            'completed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/batch-completion?' . http_build_query([
                'start_date' => Carbon::now()->subDays(7)->toDateString(),
                'end_date' => Carbon::now()->toDateString(),
                'line_id' => $this->line->id,
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'summary' => [
                        'total_batches',
                        'total_produced',
                        'average_batch_size',
                    ],
                    'batches' => [
                        '*' => [
                            'batch_id',
                            'batch_number',
                            'work_order_no',
                            'product_type',
                            'line',
                            'target_qty',
                            'produced_qty',
                            'started_at',
                            'completed_at',
                            'cycle_time_minutes',
                            'cycle_time_hours',
                        ],
                    ],
                    'generated_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['summary']['total_batches']);
        $this->assertEquals(150, $data['summary']['total_produced']);
    }

    public function test_can_generate_downtime_report()
    {
        $issueType = IssueType::factory()->create(['name' => 'Machine Breakdown']);
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
        ]);

        Issue::factory()->count(2)->create([
            'work_order_id' => $workOrder->id,
            'issue_type_id' => $issueType->id,
            'status' => 'RESOLVED',
            'reported_at' => Carbon::now()->subHours(3),
            'resolved_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/downtime?' . http_build_query([
                'start_date' => Carbon::now()->subDays(7)->toDateString(),
                'end_date' => Carbon::now()->toDateString(),
                'line_id' => $this->line->id,
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'summary' => [
                        'total_issues',
                        'open_issues',
                        'resolved_issues',
                        'closed_issues',
                        'total_downtime_minutes',
                        'total_downtime_hours',
                        'average_resolution_time_minutes',
                    ],
                    'by_type',
                    'issues',
                    'generated_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['summary']['total_issues']);
        $this->assertGreaterThan(0, $data['summary']['total_downtime_minutes']);
    }

    public function test_can_export_batch_completion_report_as_csv()
    {
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
        ]);

        Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'status' => 'DONE',
            'started_at' => Carbon::now()->subHours(2),
            'completed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/export-csv?' . http_build_query([
                'report_type' => 'batch_completion',
                'start_date' => Carbon::now()->subDays(7)->toDateString(),
                'end_date' => Carbon::now()->toDateString(),
                'line_id' => $this->line->id,
            ]));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $csv = $response->getContent();
        $this->assertStringContainsString('Batch ID', $csv);
        $this->assertStringContainsString('Work Order', $csv);
        $this->assertStringContainsString('Product Type', $csv);
    }

    public function test_can_export_downtime_report_as_csv()
    {
        $issueType = IssueType::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
        ]);

        Issue::factory()->create([
            'work_order_id' => $workOrder->id,
            'issue_type_id' => $issueType->id,
            'reported_at' => Carbon::now()->subHours(1),
            'resolved_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/export-csv?' . http_build_query([
                'report_type' => 'downtime',
                'start_date' => Carbon::now()->subDays(7)->toDateString(),
                'end_date' => Carbon::now()->toDateString(),
                'line_id' => $this->line->id,
            ]));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $csv = $response->getContent();
        $this->assertStringContainsString('Issue ID', $csv);
        $this->assertStringContainsString('Downtime', $csv);
    }

    public function test_report_filters_by_date_range()
    {
        $workOrder1 = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $workOrder2 = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Request report for last 7 days only
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/production-summary?' . http_build_query([
                'start_date' => Carbon::now()->subDays(7)->toDateString(),
                'end_date' => Carbon::now()->toDateString(),
                'line_id' => $this->line->id,
            ]));

        $data = $response->json('data');
        // Should only include workOrder1 (within 7 days), not workOrder2
        $this->assertEquals(1, $data['work_orders']['total']);
    }
}
