<?php

namespace Tests\Feature;

use App\Models\MaintenanceEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceEventTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Operator', 'guard_name' => 'sanctum']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * Create a basic MaintenanceEvent with sensible defaults.
     */
    private function createEvent(array $overrides = []): MaintenanceEvent
    {
        return MaintenanceEvent::create(array_merge([
            'title'      => 'Routine Inspection',
            'event_type' => MaintenanceEvent::TYPE_PLANNED,
            'status'     => MaintenanceEvent::STATUS_PENDING,
        ], $overrides));
    }

    public function test_admin_can_list_maintenance_events(): void
    {
        $this->createEvent(['title' => 'Belt Replacement']);
        $this->createEvent(['title' => 'Oil Change', 'event_type' => MaintenanceEvent::TYPE_CORRECTIVE]);

        $response = $this->actingAs($this->admin)->get(route('admin.maintenance-events.index'));

        $response->assertStatus(200);
        $response->assertSee('Belt Replacement');
        $response->assertSee('Oil Change');
    }

    public function test_admin_can_create_maintenance_event(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.maintenance-events.store'), [
            'title'        => 'Quarterly Machine Check',
            'event_type'   => 'inspection',
            'scheduled_at' => '2026-03-01 08:00:00',
            'description'  => 'Standard quarterly inspection of all presses.',
        ]);

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $this->assertDatabaseHas('maintenance_events', [
            'title'      => 'Quarterly Machine Check',
            'event_type' => 'inspection',
            'status'     => MaintenanceEvent::STATUS_PENDING,
        ]);
    }

    public function test_create_stores_pending_status_regardless_of_input(): void
    {
        // Even if someone somehow passes a non-pending status, the controller
        // always sets status = pending on create.
        $this->actingAs($this->admin)->post(route('admin.maintenance-events.store'), [
            'title'      => 'Auto-Pending Event',
            'event_type' => 'planned',
        ]);

        $this->assertDatabaseHas('maintenance_events', [
            'title'  => 'Auto-Pending Event',
            'status' => MaintenanceEvent::STATUS_PENDING,
        ]);
    }

    public function test_admin_can_update_maintenance_event(): void
    {
        $event = $this->createEvent(['title' => 'Original Title']);

        $response = $this->actingAs($this->admin)->put(route('admin.maintenance-events.update', $event), [
            'title'      => 'Updated Title',
            'event_type' => 'corrective',
        ]);

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $this->assertDatabaseHas('maintenance_events', [
            'id'         => $event->id,
            'title'      => 'Updated Title',
            'event_type' => 'corrective',
        ]);
    }

    public function test_admin_can_start_event(): void
    {
        $event = $this->createEvent(['status' => MaintenanceEvent::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.start', $event));

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $this->assertDatabaseHas('maintenance_events', [
            'id'     => $event->id,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
        ]);

        // Verify started_at is populated.
        $this->assertNotNull($event->fresh()->started_at);
    }

    public function test_cannot_start_an_already_in_progress_event(): void
    {
        $event = $this->createEvent(['status' => MaintenanceEvent::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.start', $event));

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHas('error');

        // Status must remain in_progress (not change to something else).
        $this->assertDatabaseHas('maintenance_events', [
            'id'     => $event->id,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_admin_can_complete_event(): void
    {
        $event = $this->createEvent(['status' => MaintenanceEvent::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.complete', $event), [
                'resolution_notes' => 'All parts replaced successfully.',
                'actual_cost'      => 450.00,
            ]);

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $this->assertDatabaseHas('maintenance_events', [
            'id'               => $event->id,
            'status'           => MaintenanceEvent::STATUS_COMPLETED,
            'resolution_notes' => 'All parts replaced successfully.',
        ]);

        // Verify completed_at is populated.
        $this->assertNotNull($event->fresh()->completed_at);
    }

    public function test_cannot_complete_a_pending_event(): void
    {
        $event = $this->createEvent(['status' => MaintenanceEvent::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.complete', $event), [
                'resolution_notes' => 'Should not work.',
            ]);

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('maintenance_events', [
            'id'     => $event->id,
            'status' => MaintenanceEvent::STATUS_PENDING,
        ]);
    }

    public function test_admin_can_cancel_pending_event(): void
    {
        $event = $this->createEvent(['status' => MaintenanceEvent::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.cancel', $event));

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $this->assertDatabaseHas('maintenance_events', [
            'id'     => $event->id,
            'status' => MaintenanceEvent::STATUS_CANCELLED,
        ]);
    }

    public function test_admin_can_cancel_in_progress_event(): void
    {
        $event = $this->createEvent(['status' => MaintenanceEvent::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.cancel', $event));

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $this->assertDatabaseHas('maintenance_events', [
            'id'     => $event->id,
            'status' => MaintenanceEvent::STATUS_CANCELLED,
        ]);
    }

    public function test_cannot_cancel_a_completed_event(): void
    {
        $event = $this->createEvent(['status' => MaintenanceEvent::STATUS_COMPLETED]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-events.cancel', $event));

        $response->assertRedirect(route('admin.maintenance-events.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('maintenance_events', [
            'id'     => $event->id,
            'status' => MaintenanceEvent::STATUS_COMPLETED,
        ]);
    }

    public function test_maintenance_event_title_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.maintenance-events.store'), [
            'event_type' => 'planned',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_maintenance_event_type_must_be_valid(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.maintenance-events.store'), [
            'title'      => 'Bad Type Event',
            'event_type' => 'unknown_type',
        ]);

        $response->assertSessionHasErrors('event_type');
    }

    public function test_actual_cost_must_be_non_negative(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.maintenance-events.store'), [
            'title'       => 'Negative Cost Event',
            'event_type'  => 'planned',
            'actual_cost' => -50,
        ]);

        $response->assertSessionHasErrors('actual_cost');
    }

    public function test_guest_cannot_access_maintenance_events(): void
    {
        $response = $this->get(route('admin.maintenance-events.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_maintenance_event(): void
    {
        $response = $this->post(route('admin.maintenance-events.store'), [
            'title'      => 'Ghost Event',
            'event_type' => 'planned',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('maintenance_events', ['title' => 'Ghost Event']);
    }

    public function test_guest_cannot_start_maintenance_event(): void
    {
        $event = $this->createEvent();

        $response = $this->post(route('admin.maintenance-events.start', $event));
        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('maintenance_events', [
            'id'     => $event->id,
            'status' => MaintenanceEvent::STATUS_PENDING,
        ]);
    }
}
