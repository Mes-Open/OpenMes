<?php

namespace Tests\Feature\Api;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\ProductionDowntime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Admin');
        $this->line = Line::factory()->create();
    }

    public function test_index_filters_by_line_and_date_range(): void
    {
        $otherLine = Line::factory()->create();
        OeeRecord::factory()->create(['line_id' => $this->line->id, 'record_date' => '2026-05-10']);
        OeeRecord::factory()->create(['line_id' => $this->line->id, 'record_date' => '2026-05-15']);
        OeeRecord::factory()->create(['line_id' => $otherLine->id, 'record_date' => '2026-05-15']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/oee?line_id=' . $this->line->id . '&date_from=2026-05-12&date_to=2026-05-20');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($this->line->id, $data[0]['line_id']);
    }

    public function test_show_returns_records_for_specific_line(): void
    {
        OeeRecord::factory()->create(['line_id' => $this->line->id, 'record_date' => today()->subDay()->toDateString()]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/oee/' . $this->line->id . '?days=7');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_can_start_and_stop_downtime(): void
    {
        $reason = DowntimeReason::factory()->changeover()->create();

        $startResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/downtimes', [
                'line_id' => $this->line->id,
                'downtime_reason_id' => $reason->id,
                'notes' => 'Tool change',
            ]);

        $startResponse->assertStatus(201);
        $downtimeId = $startResponse->json('data.id');

        $stopResponse = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v1/downtimes/' . $downtimeId);

        $stopResponse->assertStatus(200);
        $this->assertNotNull($stopResponse->json('data.ended_at'));

        // Cannot stop twice
        $secondStop = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v1/downtimes/' . $downtimeId);
        $secondStop->assertStatus(422);
    }

    public function test_downtime_reasons_endpoint_returns_kind(): void
    {
        DowntimeReason::factory()->planned()->create(['code' => 'lunch']);
        DowntimeReason::factory()->unplanned()->create(['code' => 'jam']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/downtime-reasons');

        $response->assertStatus(200);
        $kinds = collect($response->json('data'))->pluck('kind')->all();
        $this->assertContains(DowntimeKind::Planned->value, $kinds);
        $this->assertContains(DowntimeKind::Unplanned->value, $kinds);
    }
}
