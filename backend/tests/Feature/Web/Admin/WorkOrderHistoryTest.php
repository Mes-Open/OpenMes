<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create(['account_type' => 'operator']);
        $this->operator->assignRole('Operator');
    }

    private function props($response): array
    {
        return $response->getOriginalContent()->getData()['page']['props'];
    }

    private function makeOrder(string $status, ?Carbon $completedAt = null): WorkOrder
    {
        return WorkOrder::factory()->create([
            'status' => $status,
            'completed_at' => $completedAt ?? ($status === WorkOrder::STATUS_DONE ? now() : null),
        ]);
    }

    // ── List: scope to terminal statuses ─────────────────────────────────

    public function test_lists_only_finished_orders(): void
    {
        $this->makeOrder(WorkOrder::STATUS_DONE);
        $this->makeOrder(WorkOrder::STATUS_CANCELLED, now());
        $this->makeOrder(WorkOrder::STATUS_REJECTED, now());
        $this->makeOrder(WorkOrder::STATUS_PENDING);
        WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->admin)->get('/admin/reports?preset=all');

        $response->assertOk();
        $data = $this->props($response)['orders']['data'];
        $this->assertCount(3, $data);
        $statuses = array_column($data, 'status');
        $this->assertEqualsCanonicalizing(
            [WorkOrder::STATUS_DONE, WorkOrder::STATUS_CANCELLED, WorkOrder::STATUS_REJECTED],
            $statuses,
        );
    }

    public function test_status_filter_narrows_to_one(): void
    {
        $this->makeOrder(WorkOrder::STATUS_DONE);
        $this->makeOrder(WorkOrder::STATUS_CANCELLED, now());

        $response = $this->actingAs($this->admin)->get('/admin/reports?preset=all&status=CANCELLED');

        $data = $this->props($response)['orders']['data'];
        $this->assertCount(1, $data);
        $this->assertSame('CANCELLED', $data[0]['status']);
    }

    // ── Date presets ─────────────────────────────────────────────────────

    public function test_yesterday_preset_excludes_today_and_older(): void
    {
        $today = $this->makeOrder(WorkOrder::STATUS_DONE, now());
        $yesterday = $this->makeOrder(WorkOrder::STATUS_DONE, now()->subDay());
        $lastWeek = $this->makeOrder(WorkOrder::STATUS_DONE, now()->subDays(8));

        $response = $this->actingAs($this->admin)->get('/admin/reports?preset=yesterday');

        $data = $this->props($response)['orders']['data'];
        $ids = array_column($data, 'id');
        $this->assertContains($yesterday->id, $ids);
        $this->assertNotContains($today->id, $ids);
        $this->assertNotContains($lastWeek->id, $ids);
    }

    public function test_custom_range_filters_by_completed_at(): void
    {
        $inRange = $this->makeOrder(WorkOrder::STATUS_DONE, Carbon::parse('2026-03-15'));
        $outRange = $this->makeOrder(WorkOrder::STATUS_DONE, Carbon::parse('2026-05-01'));

        $response = $this->actingAs($this->admin)
            ->get('/admin/reports?preset=custom&from=2026-03-01&to=2026-03-31');

        $ids = array_column($this->props($response)['orders']['data'], 'id');
        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outRange->id, $ids);
    }

    // ── Search ───────────────────────────────────────────────────────────

    public function test_search_by_order_no(): void
    {
        $hit = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now(), 'order_no' => 'WO-FIND-ME']);
        $this->makeOrder(WorkOrder::STATUS_DONE);

        $response = $this->actingAs($this->admin)->get('/admin/reports?preset=all&search=FIND-ME');

        $data = $this->props($response)['orders']['data'];
        $this->assertCount(1, $data);
        $this->assertSame($hit->id, $data[0]['id']);
    }

    public function test_search_by_lot_number(): void
    {
        $wo = $this->makeOrder(WorkOrder::STATUS_DONE);
        Batch::factory()->create(['work_order_id' => $wo->id, 'lot_number' => 'LOT-XYZ-001']);
        $this->makeOrder(WorkOrder::STATUS_DONE);

        $response = $this->actingAs($this->admin)->get('/admin/reports?preset=all&search=XYZ');

        $data = $this->props($response)['orders']['data'];
        $this->assertCount(1, $data);
        $this->assertSame($wo->id, $data[0]['id']);
        $this->assertContains('LOT-XYZ-001', $data[0]['lots']);
    }

    // ── Summary aggregates ───────────────────────────────────────────────

    public function test_summary_counts_and_sums(): void
    {
        WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now(), 'planned_qty' => 100, 'produced_qty' => 95]);
        WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now(), 'planned_qty' => 50, 'produced_qty' => 50]);

        $response = $this->actingAs($this->admin)->get('/admin/reports?preset=all');

        $summary = $this->props($response)['summary'];
        $this->assertSame(2, $summary['orders']);
        $this->assertEquals(145, $summary['produced']);
        $this->assertEquals(150, $summary['planned']);
    }

    // ── Authorization ────────────────────────────────────────────────────

    public function test_guest_redirected(): void
    {
        $this->get('/admin/reports')->assertRedirect('/login');
    }

    public function test_operator_forbidden(): void
    {
        $this->actingAs($this->operator)->get('/admin/reports')->assertForbidden();
    }

    // ── CSV export ───────────────────────────────────────────────────────

    public function test_export_returns_csv_of_filtered_orders(): void
    {
        WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now(), 'order_no' => 'WO-CSV-1']);
        $this->makeOrder(WorkOrder::STATUS_PENDING); // excluded

        $response = $this->actingAs($this->admin)->get('/admin/reports/export?preset=all');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString('WO-CSV-1', $body);
        $this->assertStringContainsString('Order', $body); // header row
    }

    // ── Detail drill-down ────────────────────────────────────────────────

    public function test_detail_returns_full_execution_record(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'lot_number' => 'LOT-DETAIL-1',
            'status' => Batch::STATUS_DONE,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
            'produced_qty' => 40,
            'target_qty' => 40,
        ]);
        BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 1,
            'name' => 'Assembly',
            'started_by_id' => $this->operator->id,
            'completed_by_id' => $this->operator->id,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
            'duration_minutes' => 60,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/reports/{$wo->id}");

        $response->assertOk();
        $detail = $this->props($response)['workOrder'];
        $this->assertSame($wo->id, $detail['id']);
        $this->assertCount(1, $detail['batches']);
        $this->assertSame('LOT-DETAIL-1', $detail['batches'][0]['lot_number']);
        $this->assertCount(1, $detail['batches'][0]['steps']);
        $this->assertSame('Assembly', $detail['batches'][0]['steps'][0]['name']);
        $this->assertSame($this->operator->name, $detail['batches'][0]['steps'][0]['completed_by']);
        $this->assertSame(60, $detail['batches'][0]['steps'][0]['duration_minutes']);
    }

    public function test_detail_forbidden_for_operator(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);

        $this->actingAs($this->operator)->get("/admin/reports/{$wo->id}")->assertForbidden();
    }

    public function test_detail_aggregates_operators_who_produced_the_order(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'status' => Batch::STATUS_DONE]);

        // Alice completes two steps, Bob one.
        BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'completed_by_id' => $alice->id, 'started_by_id' => $alice->id]);
        BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 2, 'completed_by_id' => $alice->id, 'started_by_id' => $bob->id]);
        BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 3, 'completed_by_id' => $bob->id, 'started_by_id' => $bob->id]);

        $response = $this->actingAs($this->admin)->get("/admin/reports/{$wo->id}");

        $operators = $this->props($response)['workOrder']['operators'];
        $names = array_column($operators, 'name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);

        // Sorted by steps completed desc → Alice (2) first.
        $this->assertSame('Alice', $operators[0]['name']);
        $this->assertSame(2, $operators[0]['steps_completed']);
    }
}
