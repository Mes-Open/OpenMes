<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\Shift;
use App\Models\ShiftHandover;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftHandoverTest extends TestCase
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

    public function test_supervisor_or_admin_can_get_shift_balance(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover?line_id='.$this->line->id)
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'lines',
                    'selected_line_id',
                    'balance' => [
                        'line_id',
                        'shift_id',
                        'window',
                        'produced_qty',
                        'good_qty',
                        'packed_qty',
                        'shipped_qty',
                        'discrepancies',
                    ],
                    'recent',
                ],
            ]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/shift-handover')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/shift-handover')
            ->assertStatus(403);
    }

    public function test_admin_can_store_handover_snapshot(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/shift-handover', [
                'line_id' => $this->line->id,
                'notes' => 'End of A-shift.',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('shift_handovers', [
            'line_id' => $this->line->id,
            'confirmed_by' => $this->admin->id,
            'notes' => 'End of A-shift.',
        ]);
    }

    // ── Handover details on the recent list ─────────────────────────────

    public function test_recent_items_include_the_full_handover_snapshot(): void
    {
        $shift = Shift::create([
            'code' => 'I', 'name' => 'Morning', 'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'is_active' => true, 'sort_order' => 1, 'line_id' => null,
        ]);
        $supervisor = User::factory()->create(['name' => 'Sam Supervisor']);
        $handover = ShiftHandover::factory()->create([
            'shift_id' => $shift->id,
            'line_id' => $this->line->id,
            'business_date' => '2026-11-10',
            'shift_start' => '2026-11-10 06:00:00',
            'shift_end' => '2026-11-10 14:00:00',
            'scrap_qty' => 7,
            'discrepancies' => ['unpacked' => ['label' => 'Good output not yet packed', 'value' => 42, 'severity' => 'warning']],
            'breakdown' => ['wip_total_qty' => 12, 'open_pallets_count' => 1, 'open_pallets' => [], 'shift' => ['name' => 'Morning']],
            'notes' => 'Line 1 conveyor jammed twice.',
            'confirmed_by' => $supervisor->id,
            'confirmed_at' => '2026-11-10 14:05:00',
        ]);

        $item = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover')
            ->assertOk()
            ->json('data.recent.0');

        $this->assertSame($handover->id, $item['id']);
        $this->assertSame('2026-11-10', $item['business_date']);
        $this->assertSame('2026-11-10 06:00', $item['shift_start']);
        $this->assertSame('2026-11-10 14:00', $item['shift_end']);
        $this->assertSame($this->line->id, $item['line_id']);
        $this->assertSame($this->line->name, $item['line_name']);
        $this->assertSame($shift->id, $item['shift_id']);
        $this->assertSame('Morning', $item['shift_name']);
        $this->assertSame(7, $item['scrap_qty']);
        $this->assertSame(42, $item['discrepancies']['unpacked']['value']);
        $this->assertSame(12, $item['breakdown']['wip_total_qty']);
        $this->assertSame('Line 1 conveyor jammed twice.', $item['notes']);
        $this->assertSame('Sam Supervisor', $item['confirmed_by']);
        $this->assertSame($supervisor->id, $item['confirmed_by_id']);
        $this->assertSame('2026-11-10 14:05', $item['confirmed_at']);
        // Keys that existed before stay in place.
        foreach (['produced_qty', 'good_qty', 'packed_qty', 'shipped_qty', 'wip_open_pallets_qty', 'wip_unpacked_qty'] as $key) {
            $this->assertArrayHasKey($key, $item);
        }
    }

    public function test_recent_item_keeps_names_of_soft_deleted_line_shift_and_user(): void
    {
        $shift = Shift::create([
            'code' => 'II', 'name' => 'Afternoon', 'start_time' => '14:00:00', 'end_time' => '22:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'is_active' => true, 'sort_order' => 2, 'line_id' => null,
        ]);
        $supervisor = User::factory()->create(['name' => 'Former Supervisor']);
        ShiftHandover::factory()->create([
            'shift_id' => $shift->id,
            'line_id' => $this->line->id,
            'confirmed_by' => $supervisor->id,
        ]);

        $this->line->delete();
        $shift->delete();
        $supervisor->delete();

        $item = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover')
            ->assertOk()
            ->json('data.recent.0');

        $this->assertSame($this->line->name, $item['line_name']);
        $this->assertSame('Afternoon', $item['shift_name']);
        $this->assertSame('Former Supervisor', $item['confirmed_by']);
    }

    public function test_recent_list_is_the_20_newest_across_all_lines(): void
    {
        $other = Line::factory()->create();
        ShiftHandover::factory()->count(11)->create(['line_id' => $this->line->id]);
        ShiftHandover::factory()->count(11)->create(['line_id' => $other->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover?line_id='.$this->line->id)
            ->assertOk()
            ->assertJsonPath('data.selected_line_id', $this->line->id)
            ->assertJsonCount(20, 'data.recent');

        // line_id scopes the balance only; recent still spans both lines.
        $this->assertCount(2, collect($response->json('data.recent'))->pluck('line_id')->unique());
        $this->assertArrayNotHasKey('recent_meta', $response->json('data'));
    }

    public function test_balance_endpoint_ignores_a_non_numeric_line_id(): void
    {
        // Pre-existing contract: junk such as `line_id=all` means "all lines", not 422.
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover?line_id=all')
            ->assertOk()
            ->assertJsonPath('data.selected_line_id', null);
    }

    public function test_empty_discrepancies_and_breakdown_are_json_objects(): void
    {
        $handover = ShiftHandover::factory()->create(['discrepancies' => [], 'breakdown' => null]);

        $this->actingAs($this->admin, 'sanctum');

        $recent = $this->getJson('/api/v1/shift-handover')->assertOk()->getContent();
        $this->assertStringContainsString('"discrepancies":{}', $recent);
        $this->assertStringContainsString('"breakdown":{}', $recent);

        $show = $this->getJson("/api/v1/shift-handover/{$handover->id}")->assertOk()->getContent();
        $this->assertStringContainsString('"discrepancies":{}', $show);
        $this->assertStringContainsString('"breakdown":{}', $show);
    }

    public function test_shift_name_comes_from_the_snapshot_not_the_renamed_shift(): void
    {
        $shift = Shift::create([
            'code' => 'I', 'name' => 'Morning', 'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'is_active' => true, 'sort_order' => 1, 'line_id' => null,
        ]);
        $snapshotted = ShiftHandover::factory()->create([
            'shift_id' => $shift->id,
            'breakdown' => ['shift' => ['name' => 'Morning', 'code' => 'I', 'start' => '06:00', 'end' => '14:00']],
        ]);
        $legacy = ShiftHandover::factory()->create(['shift_id' => $shift->id, 'breakdown' => []]);

        $shift->update(['name' => 'Early']);

        $this->actingAs($this->admin, 'sanctum');
        $this->getJson("/api/v1/shift-handover/{$snapshotted->id}")
            ->assertOk()
            ->assertJsonPath('data.shift_name', 'Morning');
        // Without a snapshot name, fall back to the shift's current name.
        $this->getJson("/api/v1/shift-handover/{$legacy->id}")
            ->assertOk()
            ->assertJsonPath('data.shift_name', 'Early');
    }

    // ── History ──────────────────────────────────────────────────────────

    public function test_history_is_paginated_newest_first_with_default_page_size(): void
    {
        $handovers = collect(range(1, 27))->map(fn (int $i) => ShiftHandover::factory()->create([
            'confirmed_at' => now()->subHours(30 - $i),
        ]));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover/history')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 27)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $handovers->last()->id);

        $this->assertArrayHasKey('notes', $response->json('data.0'));
        $this->assertArrayNotHasKey('balance', $response->json());
    }

    public function test_history_supports_per_page_and_page(): void
    {
        $handovers = collect(range(1, 5))->map(fn (int $i) => ShiftHandover::factory()->create([
            'confirmed_at' => now()->subHours(10 - $i),
        ]));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover/history?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);

        // Newest first: page 2 holds the 3rd and 4th most recent.
        $this->assertSame(
            [$handovers[2]->id, $handovers[1]->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_history_filters_by_line(): void
    {
        $other = Line::factory()->create();
        $mine = ShiftHandover::factory()->create(['line_id' => $this->line->id]);
        ShiftHandover::factory()->create(['line_id' => $other->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover/history?line_id='.$this->line->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_history_filters_by_inclusive_business_date_range(): void
    {
        ShiftHandover::factory()->create(['business_date' => '2026-11-04']);
        $inside1 = ShiftHandover::factory()->create(['business_date' => '2026-11-05']);
        $inside2 = ShiftHandover::factory()->create(['business_date' => '2026-11-07']);
        ShiftHandover::factory()->create(['business_date' => '2026-11-08']);

        $ids = collect($this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover/history?business_date_from=2026-11-05&business_date_to=2026-11-07')
            ->assertOk()
            ->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$inside1->id, $inside2->id], $ids);
    }

    public function test_history_parameters_are_validated(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/v1/shift-handover/history?business_date_from=2026-11-07&business_date_to=2026-11-05')
            ->assertStatus(422)->assertJsonValidationErrors('business_date_to');
        $this->getJson('/api/v1/shift-handover/history?business_date_from=07.11.2026')
            ->assertStatus(422)->assertJsonValidationErrors('business_date_from');
        $this->getJson('/api/v1/shift-handover/history?per_page=0')
            ->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/shift-handover/history?per_page=101')
            ->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/shift-handover/history?line_id=all')
            ->assertStatus(422)->assertJsonValidationErrors('line_id');
        $this->getJson('/api/v1/shift-handover/history?page=0')
            ->assertStatus(422)->assertJsonValidationErrors('page');
    }

    public function test_guest_cannot_read_history(): void
    {
        $this->getJson('/api/v1/shift-handover/history')->assertStatus(401);
    }

    public function test_operator_cannot_read_history(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/shift-handover/history')
            ->assertStatus(403);
    }

    // ── Single handover ──────────────────────────────────────────────────

    public function test_supervisor_can_show_one_handover(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $handover = ShiftHandover::factory()->create([
            'line_id' => $this->line->id,
            'scrap_qty' => 3,
            'discrepancies' => ['awaiting_shipment' => ['label' => 'Packed but not yet shipped', 'value' => 9, 'severity' => 'info']],
            'breakdown' => ['wip_total_qty' => 4],
            'notes' => 'Handed over to B-shift.',
        ]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/v1/shift-handover/{$handover->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $handover->id)
            ->assertJsonPath('data.line_id', $this->line->id)
            ->assertJsonPath('data.line_name', $this->line->name)
            ->assertJsonPath('data.scrap_qty', 3)
            ->assertJsonPath('data.discrepancies.awaiting_shipment.severity', 'info')
            ->assertJsonPath('data.breakdown.wip_total_qty', 4)
            ->assertJsonPath('data.notes', 'Handed over to B-shift.')
            ->assertJsonPath('data.confirmed_by', $handover->confirmedBy->name);
    }

    public function test_show_returns_404_for_missing_handover(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover/999999')
            ->assertNotFound();
    }

    public function test_guest_cannot_show_handover(): void
    {
        $handover = ShiftHandover::factory()->create();

        $this->getJson("/api/v1/shift-handover/{$handover->id}")->assertStatus(401);
    }

    public function test_operator_cannot_show_handover(): void
    {
        $handover = ShiftHandover::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson("/api/v1/shift-handover/{$handover->id}")
            ->assertStatus(403);
    }

    public function test_handovers_are_isolated_per_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);

        $supervisorA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $supervisorA->assignRole('Supervisor');

        $own = ShiftHandover::factory()->create(['tenant_id' => $tenantA->id]);
        $foreign = ShiftHandover::factory()->create(['tenant_id' => $tenantB->id]);

        $ids = collect($this->actingAs($supervisorA, 'sanctum')
            ->getJson('/api/v1/shift-handover')
            ->assertOk()
            ->json('data.recent'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($foreign->id), 'Cross-tenant handover leaked into the recent list');

        $historyIds = collect($this->getJson('/api/v1/shift-handover/history')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($historyIds->contains($own->id));
        $this->assertFalse($historyIds->contains($foreign->id), 'Cross-tenant handover leaked into history');

        $this->getJson("/api/v1/shift-handover/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/shift-handover/{$own->id}")->assertOk();
    }
}
