<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\Tool;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceEventControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * Minimal valid payload for store, parameterised by target field.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Routine Maintenance',
            'event_type'   => 'planned',
            'scheduled_at' => '2026-06-01 08:00:00',
            'line_id'      => Line::factory()->create()->id,
        ], $overrides);
    }

    // ── Bug 1: event_type enum mismatch ──────────────────────────────────────

    public function test_store_rejects_unknown_event_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), $this->validPayload([
                'event_type' => 'preventive',
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('event_type');
    }

    public function test_store_rejects_calibration_event_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), $this->validPayload([
                'event_type' => 'calibration',
            ]));

        $response->assertSessionHasErrors('event_type');
    }

    public function test_store_accepts_planned_event_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), $this->validPayload([
                'title'      => 'Planned Service',
                'event_type' => 'planned',
            ]));

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_events', [
            'title'      => 'Planned Service',
            'event_type' => 'planned',
        ]);
    }

    public function test_store_accepts_corrective_event_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), $this->validPayload([
                'title'      => 'Corrective Fix',
                'event_type' => 'corrective',
            ]));

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_events', [
            'title'      => 'Corrective Fix',
            'event_type' => 'corrective',
        ]);
    }

    public function test_store_accepts_inspection_event_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), $this->validPayload([
                'title'      => 'Quarterly Inspection',
                'event_type' => 'inspection',
            ]));

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_events', [
            'title'      => 'Quarterly Inspection',
            'event_type' => 'inspection',
        ]);
    }

    // ── Bug 2: scheduled_at is required ──────────────────────────────────────

    public function test_store_requires_scheduled_at(): void
    {
        $line = Line::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), [
                'title'      => 'No Schedule',
                'event_type' => 'planned',
                'line_id'    => $line->id,
            ]);

        $response->assertSessionHasErrors('scheduled_at');
    }

    // ── Bug 3: at least one of tool/line/workstation required ────────────────

    public function test_store_requires_one_of_tool_line_workstation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), [
                'title'        => 'Orphan Event',
                'event_type'   => 'planned',
                'scheduled_at' => '2026-06-01 08:00:00',
            ]);

        $response->assertSessionHasErrors(['tool_id', 'line_id', 'workstation_id']);
        $this->assertDatabaseMissing('maintenance_events', ['title' => 'Orphan Event']);
    }

    public function test_store_accepts_only_line(): void
    {
        $line = Line::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), [
                'title'        => 'Line-Only Event',
                'event_type'   => 'planned',
                'scheduled_at' => '2026-06-01 08:00:00',
                'line_id'      => $line->id,
            ]);

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_events', [
            'title'   => 'Line-Only Event',
            'line_id' => $line->id,
        ]);
    }

    public function test_store_accepts_only_workstation(): void
    {
        $ws = Workstation::factory()->create(['line_id' => Line::factory()->create()->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), [
                'title'          => 'Workstation-Only Event',
                'event_type'     => 'planned',
                'scheduled_at'   => '2026-06-01 08:00:00',
                'workstation_id' => $ws->id,
            ]);

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_events', [
            'title'          => 'Workstation-Only Event',
            'workstation_id' => $ws->id,
        ]);
    }

    public function test_store_accepts_only_tool(): void
    {
        $tool = Tool::create([
            'code'   => 'TOOL-T01',
            'name'   => 'Test Tool',
            'status' => Tool::STATUS_AVAILABLE,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.store'), [
                'title'        => 'Tool-Only Event',
                'event_type'   => 'planned',
                'scheduled_at' => '2026-06-01 08:00:00',
                'tool_id'      => $tool->id,
            ]);

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_events', [
            'title'   => 'Tool-Only Event',
            'tool_id' => $tool->id,
        ]);
    }

    // ── Update endpoint: same rules apply ────────────────────────────────────

    public function test_update_rejects_unknown_event_type(): void
    {
        $line = Line::factory()->create();
        $event = MaintenanceEvent::create([
            'title'      => 'Existing',
            'event_type' => 'planned',
            'status'     => MaintenanceEvent::STATUS_PENDING,
            'line_id'    => $line->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.maintenance-events.update', $event), [
                'title'        => 'Existing',
                'event_type'   => 'calibration',
                'scheduled_at' => '2026-06-01 08:00:00',
                'line_id'      => $line->id,
            ]);

        $response->assertSessionHasErrors('event_type');
    }

    public function test_update_requires_scheduled_at(): void
    {
        $line = Line::factory()->create();
        $event = MaintenanceEvent::create([
            'title'      => 'Existing',
            'event_type' => 'planned',
            'status'     => MaintenanceEvent::STATUS_PENDING,
            'line_id'    => $line->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.maintenance-events.update', $event), [
                'title'      => 'Existing',
                'event_type' => 'planned',
                'line_id'    => $line->id,
            ]);

        $response->assertSessionHasErrors('scheduled_at');
    }

    public function test_update_requires_one_of_tool_line_workstation(): void
    {
        $line = Line::factory()->create();
        $event = MaintenanceEvent::create([
            'title'      => 'Existing',
            'event_type' => 'planned',
            'status'     => MaintenanceEvent::STATUS_PENDING,
            'line_id'    => $line->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.maintenance-events.update', $event), [
                'title'        => 'Existing',
                'event_type'   => 'planned',
                'scheduled_at' => '2026-06-01 08:00:00',
            ]);

        $response->assertSessionHasErrors(['tool_id', 'line_id', 'workstation_id']);
    }
}
