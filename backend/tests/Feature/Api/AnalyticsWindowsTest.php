<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The analytics endpoints count what happened in a window, and the window has
 * to be measured on the right column:
 *  - "done today" on completed_at, not on the last edit (updated_at);
 *  - throughput on the output ledger, not on each order's whole produced_qty
 *    dated by its last edit;
 *  - production-by-line with both dates inclusive.
 * Malformed parameters are 422s, not 500s.
 */
class AnalyticsWindowsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Line $line;

    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->line = Line::factory()->create();
        $this->productType = ProductType::factory()->create();
        Carbon::setTestNow(Carbon::parse('2026-03-12 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(array $attributes = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'produced_qty' => 0,
        ], $attributes));
    }

    private function api(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->getJson($uri);
    }

    public function test_done_today_counts_orders_completed_today_not_edited_today(): void
    {
        $this->order(['status' => 'DONE', 'completed_at' => now()->subDays(3)]); // touched today below
        $this->order(['status' => 'DONE', 'completed_at' => now()->subHour()]);

        WorkOrder::where('completed_at', '<', now()->startOfDay())->first()->update(['description' => 'Late note']);

        $this->api('/api/v1/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.completed_work_orders', 2)
            ->assertJsonPath('data.done_today_work_orders', 1);
    }

    public function test_throughput_dates_output_when_it_was_produced(): void
    {
        $order = $this->order();

        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));
        $order->update(['produced_qty' => 40]);
        Carbon::setTestNow(Carbon::parse('2026-03-10 15:00:00'));
        $order->update(['produced_qty' => 50]);
        Carbon::setTestNow(Carbon::parse('2026-03-11 08:00:00'));
        $order->update(['produced_qty' => 48]); // a correction nets out

        // An edit today that changes nothing about output.
        Carbon::setTestNow(Carbon::parse('2026-03-12 10:00:00'));
        $order->update(['description' => 'Customer called']);

        $days = collect($this->api('/api/v1/analytics/throughput?days=7')->assertOk()->json('data.daily_production'))
            ->mapWithKeys(fn ($row) => [substr((string) $row['date'], 0, 10) => (float) $row['total_produced']])
            ->all();

        $this->assertSame(['2026-03-10' => 50.0, '2026-03-11' => -2.0], $days);
    }

    public function test_throughput_days_counts_today_as_the_last_day(): void
    {
        $order = $this->order();
        // 6 and 7 days before 2026-03-12: inside and just outside days=7.
        foreach (['2026-03-06 09:00:00' => 10, '2026-03-05 09:00:00' => 4] as $at => $qty) {
            Carbon::setTestNow(Carbon::parse($at));
            $order->update(['produced_qty' => (float) $order->produced_qty + $qty]);
        }
        Carbon::setTestNow(Carbon::parse('2026-03-12 10:00:00'));

        $data = $this->api('/api/v1/analytics/throughput?days=7')->assertOk()->json('data');

        $this->assertSame('2026-03-06', $data['period_start']);
        $this->assertSame(['2026-03-06'], array_map(fn ($r) => substr((string) $r['date'], 0, 10), $data['daily_production']));
        $this->assertSame('2026-03-12', $this->api('/api/v1/analytics/throughput?days=1')->json('data.period_start'));
    }

    public function test_throughput_respects_line_filter_and_window(): void
    {
        $other = Line::factory()->create();
        $mine = $this->order();
        $theirs = $this->order(['line_id' => $other->id]);

        Carbon::setTestNow(Carbon::parse('2026-02-01 09:00:00')); // outside a 7-day window
        $mine->update(['produced_qty' => 5]);
        Carbon::setTestNow(Carbon::parse('2026-03-11 09:00:00'));
        $mine->update(['produced_qty' => 15]);
        $theirs->update(['produced_qty' => 99]);
        Carbon::setTestNow(Carbon::parse('2026-03-12 10:00:00'));

        $rows = $this->api("/api/v1/analytics/throughput?days=7&line_id={$this->line->id}")
            ->assertOk()->json('data.daily_production');

        $this->assertCount(1, $rows);
        $this->assertSame(10.0, (float) $rows[0]['total_produced']);
    }

    public function test_production_by_line_includes_the_end_day(): void
    {
        $this->order(['created_at' => '2026-03-12 09:30:00']);
        $this->order(['created_at' => '2026-03-11 23:59:00']);
        $this->order(['created_at' => '2026-03-13 00:00:00']);

        $count = fn (string $qs) => collect($this->api("/api/v1/analytics/production-by-line?{$qs}")->assertOk()->json('data'))
            ->firstWhere('line_id', $this->line->id)['total_work_orders'];

        $this->assertSame(1, $count('start_date=2026-03-12&end_date=2026-03-12'));
        $this->assertSame(2, $count('start_date=2026-03-11&end_date=2026-03-12'));
        $this->assertSame(2, $count('')); // default: the last 7 days up to today
    }

    public function test_malformed_parameters_are_rejected(): void
    {
        foreach ([
            'overview?line_id=abc' => 'line_id',
            'overview?line_id=999999' => 'line_id',
            'throughput?days=abc' => 'days',
            'throughput?days=0' => 'days',
            'cycle-time?days=-5' => 'days',
            'issue-stats?days=1.5' => 'days',
            'step-performance?line_id=abc' => 'line_id',
            'production-by-line?start_date=nope' => 'start_date',
            'production-by-line?start_date=2026-03-12&end_date=2026-03-11' => 'end_date',
        ] as $uri => $field) {
            $this->api("/api/v1/analytics/{$uri}")->assertStatus(422)->assertJsonValidationErrors($field);
        }
    }

    public function test_access(): void
    {
        $this->getJson('/api/v1/analytics/throughput')->assertUnauthorized();

        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $this->actingAs($operator, 'sanctum')->getJson('/api/v1/analytics/throughput')->assertForbidden();

        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $this->actingAs($supervisor, 'sanctum')->getJson('/api/v1/analytics/throughput')->assertOk();
    }
}
