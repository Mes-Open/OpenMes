<?php

namespace Tests\Feature\Auditable;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\MaintenanceSchedule;
use App\Models\ProcessConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests confirming the Auditable trait now fires for models that
 * were extended in this branch. We exercise creation and assert the
 * matching audit_logs row exists.
 */
class AuditableExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_create_writes_audit_log(): void
    {
        $batch = Batch::factory()->create();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'App\\Models\\Batch',
            'entity_id'   => $batch->id,
            'action'      => 'created',
        ]);
    }

    public function test_batch_step_create_writes_audit_log(): void
    {
        $batch = Batch::factory()->create();

        $step = BatchStep::factory()->create([
            'batch_id' => $batch->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'App\\Models\\BatchStep',
            'entity_id'   => $step->id,
            'action'      => 'created',
        ]);
    }

    public function test_maintenance_schedule_create_writes_audit_log(): void
    {
        $schedule = MaintenanceSchedule::factory()->create();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'App\\Models\\MaintenanceSchedule',
            'entity_id'   => $schedule->id,
            'action'      => 'created',
        ]);
    }

    public function test_traits_are_present_on_extended_models(): void
    {
        // Confirms the trait registration even on models without a factory.
        $this->assertContains(
            \App\Traits\Auditable::class,
            class_uses_recursive(ProcessConfirmation::class),
            'ProcessConfirmation should use Auditable trait.'
        );
    }
}
