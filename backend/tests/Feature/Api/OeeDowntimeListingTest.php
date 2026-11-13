<?php

namespace Tests\Feature\Api;

use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\ProductionDowntime;
use App\Models\User;
use App\Models\Workstation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/oee and GET /api/v1/downtimes: the legacy capped response must
 * stay byte-for-byte compatible for existing clients (mobile app), while
 * pagination, extra filters and lean relations are opt-in.
 */
class OeeDowntimeListingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->line = Line::factory()->create();
    }

    /** One OEE record per day, newest = $start, going back $count days. */
    private function seedOeeDays(int $count, string $start = '2026-06-30', ?Line $line = null): void
    {
        $base = Carbon::parse($start);
        for ($i = 0; $i < $count; $i++) {
            OeeRecord::factory()->create([
                'line_id' => ($line ?? $this->line)->id,
                'record_date' => $base->copy()->subDays($i)->toDateString(),
            ]);
        }
    }

    private function downtime(array $attributes = []): ProductionDowntime
    {
        return ProductionDowntime::factory()->create(array_merge([
            'line_id' => $this->line->id,
            'downtime_reason_id' => DowntimeReason::factory()->create()->id,
        ], $attributes));
    }

    // ── OEE: legacy response ─────────────────────────────────────────────

    public function test_oee_default_response_keeps_legacy_shape_cap_and_order(): void
    {
        $this->seedOeeDays(101);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/oee');

        $response->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
        $data = $response->json('data');
        $this->assertCount(100, $data);

        $dates = array_column($data, 'record_date');
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates, 'records must stay newest first');
        $this->assertStringStartsWith('2026-06-30', $dates[0]);

        // Full relations are still embedded, not the lean projection.
        $this->assertArrayHasKey('line', $data[0]);
        $this->assertArrayHasKey('created_at', $data[0]['line']);
        $this->assertArrayHasKey('shift', $data[0]);
    }

    public function test_oee_legacy_filters_still_work_without_pagination(): void
    {
        $other = Line::factory()->create();
        $this->seedOeeDays(5, '2026-06-30');
        $this->seedOeeDays(5, '2026-06-30', $other);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/oee?line_id={$this->line->id}&date_from=2026-06-27&date_to=2026-06-29");

        $response->assertOk()->assertJsonMissingPath('meta');
        $this->assertCount(3, $response->json('data'));
        $this->assertSame([$this->line->id], array_values(array_unique(array_column($response->json('data'), 'line_id'))));
    }

    // ── OEE: pagination ──────────────────────────────────────────────────

    public function test_oee_paginates_with_meta_and_no_overlap(): void
    {
        $this->seedOeeDays(5);

        $first = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/oee?per_page=2&page=1');
        $last = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/oee?per_page=2&page=3');

        $first->assertOk()->assertJsonPath('meta', [
            'current_page' => 1,
            'per_page' => 2,
            'total' => 5,
            'last_page' => 3,
        ]);
        $this->assertCount(2, $first->json('data'));
        $last->assertOk()->assertJsonPath('meta.current_page', 3);
        $this->assertCount(1, $last->json('data'));

        $ids = collect([1, 2, 3])
            ->flatMap(fn ($p) => $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/v1/oee?per_page=2&page={$p}")->json('data'))
            ->pluck('id');
        $this->assertCount(5, $ids->unique());
    }

    public function test_oee_page_alone_defaults_to_the_legacy_cap_as_page_size(): void
    {
        $this->seedOeeDays(101);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/oee?page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 101)
            ->assertJsonPath('meta.last_page', 2);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_oee_pagination_combines_with_date_range_line_and_workstation(): void
    {
        $other = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $this->line->id]);
        $this->seedOeeDays(10, '2026-06-30');
        $this->seedOeeDays(10, '2026-06-30', $other);
        foreach (['2026-06-25', '2026-06-26', '2026-06-20'] as $day) {
            OeeRecord::factory()->create([
                'line_id' => $this->line->id,
                'workstation_id' => $ws->id,
                'record_date' => $day,
            ]);
        }

        $byRange = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/oee?line_id={$this->line->id}&date_from=2026-06-24&date_to=2026-06-30&per_page=50");
        $byRange->assertOk()->assertJsonPath('meta.total', 9); // 7 line-level days + 2 workstation rows
        foreach ($byRange->json('data') as $row) {
            $this->assertSame($this->line->id, $row['line_id']);
            $this->assertGreaterThanOrEqual('2026-06-24', substr($row['record_date'], 0, 10));
            $this->assertLessThanOrEqual('2026-06-30', substr($row['record_date'], 0, 10));
        }

        $byStation = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/oee?workstation_id={$ws->id}&date_from=2026-06-24&page=1");
        $byStation->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_oee_lean_mode_trims_embedded_relations(): void
    {
        $this->seedOeeDays(1);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/oee?lean=1');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(['id', 'name', 'code'], array_keys($response->json('data.0.line')));
    }

    public function test_oee_rejects_out_of_bounds_paging_and_bad_filters(): void
    {
        foreach (['per_page=0', 'per_page=101', 'per_page=abc', 'page=0', 'page=-1', 'workstation_id=999999', 'date_from=not-a-date', 'lean=maybe'] as $qs) {
            $key = explode('=', $qs)[0];
            $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/v1/oee?{$qs}")
                ->assertStatus(422)
                ->assertJsonValidationErrors($key);
        }
    }

    // ── Downtimes: legacy response ───────────────────────────────────────

    public function test_downtimes_default_response_keeps_legacy_shape_cap_and_order(): void
    {
        $reason = DowntimeReason::factory()->create();
        $base = Carbon::parse('2026-06-30 12:00:00');
        for ($i = 0; $i < 51; $i++) {
            $this->downtime([
                'downtime_reason_id' => $reason->id,
                'started_at' => $base->copy()->subHours($i),
            ]);
        }

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/downtimes');

        $response->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
        $data = $response->json('data');
        $this->assertCount(50, $data);
        $starts = array_column($data, 'started_at');
        $sorted = $starts;
        rsort($sorted);
        $this->assertSame($sorted, $starts, 'downtimes must stay newest first');

        // Full relations are still embedded.
        $this->assertArrayHasKey('reason', $data[0]);
        $this->assertArrayHasKey('created_at', $data[0]['line']);
    }

    public function test_downtimes_legacy_date_and_line_filters_still_work(): void
    {
        $other = Line::factory()->create();
        $this->downtime(['started_at' => '2026-06-10 08:00:00']);
        $this->downtime(['started_at' => '2026-06-10 22:00:00']);
        $this->downtime(['started_at' => '2026-06-11 08:00:00']);
        $this->downtime(['line_id' => $other->id, 'started_at' => '2026-06-10 09:00:00']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/downtimes?line_id={$this->line->id}&date=2026-06-10");

        $response->assertOk()->assertJsonMissingPath('meta');
        $this->assertCount(2, $response->json('data'));

        // An unknown line has always been an empty list, not a 422.
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/downtimes?line_id=999999')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    // ── Downtimes: pagination and new filters ────────────────────────────

    public function test_downtimes_paginate_with_meta(): void
    {
        $base = Carbon::parse('2026-06-30 12:00:00');
        for ($i = 0; $i < 5; $i++) {
            $this->downtime(['started_at' => $base->copy()->subHours($i)]);
        }

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/downtimes?per_page=2&page=2');

        $response->assertOk()->assertJsonPath('meta', [
            'current_page' => 2,
            'per_page' => 2,
            'total' => 5,
            'last_page' => 3,
        ]);
        $this->assertCount(2, $response->json('data'));

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/downtimes?page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_downtimes_date_range_is_inclusive_by_start_day(): void
    {
        $this->downtime(['started_at' => '2026-06-09 23:59:00']);
        $inFirst = $this->downtime(['started_at' => '2026-06-10 00:00:00']);
        $inLast = $this->downtime(['started_at' => '2026-06-12 23:59:00']);
        $this->downtime(['started_at' => '2026-06-13 00:00:00']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/downtimes?date_from=2026-06-10&date_to=2026-06-12');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$inFirst->id, $inLast->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_downtimes_filters_combine_with_pagination(): void
    {
        $other = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $this->line->id]);

        $openOnStation = $this->downtime([
            'workstation_id' => $ws->id,
            'started_at' => '2026-06-10 08:00:00',
            'ended_at' => null,
            'duration_minutes' => null,
        ]);
        $this->downtime(['workstation_id' => $ws->id, 'started_at' => '2026-06-10 06:00:00']);
        $this->downtime(['started_at' => '2026-06-10 07:00:00', 'ended_at' => null, 'duration_minutes' => null]);
        $this->downtime(['line_id' => $other->id, 'started_at' => '2026-06-10 07:30:00', 'ended_at' => null, 'duration_minutes' => null]);
        $this->downtime(['started_at' => '2026-06-20 07:00:00', 'ended_at' => null, 'duration_minutes' => null]);

        $openOnLine = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/downtimes?line_id={$this->line->id}&active=1&date_from=2026-06-01&date_to=2026-06-15&per_page=10");
        $openOnLine->assertOk()->assertJsonPath('meta.total', 2);
        foreach ($openOnLine->json('data') as $row) {
            $this->assertNull($row['ended_at']);
            $this->assertSame($this->line->id, $row['line_id']);
        }

        $closedOnStation = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/downtimes?workstation_id={$ws->id}&active=0");
        $closedOnStation->assertOk();
        $this->assertCount(1, $closedOnStation->json('data'));
        $this->assertNotNull($closedOnStation->json('data.0.ended_at'));

        $stationOpen = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/downtimes?workstation_id={$ws->id}&active=true&page=1");
        $stationOpen->assertOk()->assertJsonPath('data.0.id', $openOnStation->id);
    }

    public function test_downtimes_lean_mode_trims_embedded_relations(): void
    {
        $this->downtime(['reported_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/downtimes?lean=1');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(['id', 'name', 'code'], array_keys($response->json('data.0.line')));
        $this->assertEqualsCanonicalizing(['id', 'code', 'name', 'kind'], array_keys($response->json('data.0.reason')));
        $this->assertEqualsCanonicalizing(['id', 'name', 'username'], array_keys($response->json('data.0.reported_by')));
    }

    public function test_downtimes_rejects_out_of_bounds_paging_and_bad_filters(): void
    {
        foreach (['per_page=0', 'per_page=101', 'page=0', 'date=garbage', 'date_from=garbage', 'date_to=garbage', 'line_id=abc', 'active=maybe', 'workstation_id=999999'] as $qs) {
            $key = explode('=', $qs)[0];
            $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/v1/downtimes?{$qs}")
                ->assertStatus(422)
                ->assertJsonValidationErrors($key);
        }
    }

    // ── Authorization (unchanged) ────────────────────────────────────────

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/v1/oee')->assertUnauthorized();
        $this->getJson('/api/v1/oee?page=1')->assertUnauthorized();
        $this->getJson('/api/v1/downtimes')->assertUnauthorized();
        $this->getJson('/api/v1/downtimes?page=1')->assertUnauthorized();
    }

    public function test_operators_can_still_list_both(): void
    {
        // These routes are open to every authenticated role (operators report
        // downtime from the shop floor); paging must not change that.
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $this->seedOeeDays(1);
        $this->downtime();

        $this->actingAs($operator, 'sanctum')->getJson('/api/v1/oee?page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($operator, 'sanctum')->getJson('/api/v1/downtimes?page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($operator, 'sanctum')->getJson('/api/v1/oee')->assertOk();
        $this->actingAs($operator, 'sanctum')->getJson('/api/v1/downtimes')->assertOk();
    }
}
