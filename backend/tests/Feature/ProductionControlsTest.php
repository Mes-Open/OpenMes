<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Line;
use App\Models\ProcessConfirmation;
use App\Models\ProcessTemplate;
use App\Models\QualityCheckTemplate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Production\PackagingChecklistService;
use App\Services\Production\ProcessConfirmationService;
use App\Services\Production\QualityCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionControlsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        $operatorRole = Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        foreach (['view work orders', 'create work orders', 'edit work orders', 'delete work orders'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }
        $adminRole->givePermissionTo(Permission::all());
        $operatorRole->givePermissionTo(['view work orders', 'edit work orders']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    // ── Process Confirmations ────────────────────────────────────

    public function test_confirm_parameters(): void
    {
        $batch = $this->createBatch();

        $service = app(ProcessConfirmationService::class);
        $confirmation = $service->confirm($batch, $this->operator);

        $this->assertEquals(ProcessConfirmation::TYPE_PARAMETERS, $confirmation->confirmation_type);
        $this->assertEquals($this->operator->id, $confirmation->confirmed_by);
        $this->assertNotNull($confirmation->confirmed_at);
    }

    public function test_confirm_drying_valid(): void
    {
        $batch = $this->createBatch();

        $service = app(ProcessConfirmationService::class);
        $confirmation = $service->confirmDrying($batch, $this->operator, 14);

        $this->assertEquals(ProcessConfirmation::TYPE_DRYING, $confirmation->confirmation_type);
        $this->assertEquals('14', $confirmation->value);
    }

    public function test_confirm_drying_below_minimum_fails(): void
    {
        $batch = $this->createBatch();

        $service = app(ProcessConfirmationService::class);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('at least 12 hours');
        $service->confirmDrying($batch, $this->operator, 8);
    }

    public function test_is_confirmed_today(): void
    {
        $batch = $this->createBatch();

        $service = app(ProcessConfirmationService::class);
        $this->assertFalse($service->isConfirmedToday($batch));

        $service->confirm($batch, $this->operator);
        $this->assertTrue($service->isConfirmedToday($batch));
    }

    public function test_api_confirm_parameters(): void
    {
        $batch = $this->createBatch();

        $response = $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/confirmations", [
                'confirmation_type' => 'parameters',
                'notes' => 'All parameters OK',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.confirmation_type', 'parameters');
    }

    public function test_api_confirm_drying(): void
    {
        $batch = $this->createBatch();

        $response = $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/confirmations", [
                'confirmation_type' => 'drying',
                'value' => '14',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.value', '14');
    }

    public function test_api_confirm_drying_below_min(): void
    {
        $batch = $this->createBatch();

        $response = $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/confirmations", [
                'confirmation_type' => 'drying',
                'value' => '8',
            ]);

        $response->assertStatus(422);
    }

    public function test_api_confirmation_status(): void
    {
        $batch = $this->createBatch();

        $response = $this->actingAs($this->operator, 'sanctum')
            ->getJson("/api/v1/batches/{$batch->id}/confirmations/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.confirmed_today', false)
            ->assertJsonPath('data.total_confirmations', 0);
    }

    // ── Quality Checks ───────────────────────────────────────────

    public function test_perform_quality_check(): void
    {
        $batch = $this->createBatch();

        $service = app(QualityCheckService::class);
        $check = $service->performCheck($batch, $this->operator, [
            ['sample_number' => 1, 'parameter_name' => 'Dimension A', 'parameter_type' => 'measurement', 'value_numeric' => 12.5, 'is_passed' => true],
            ['sample_number' => 1, 'parameter_name' => 'Fit check', 'parameter_type' => 'pass_fail', 'value_boolean' => true, 'is_passed' => true],
            ['sample_number' => 2, 'parameter_name' => 'Dimension A', 'parameter_type' => 'measurement', 'value_numeric' => 12.3, 'is_passed' => true],
            ['sample_number' => 2, 'parameter_name' => 'Fit check', 'parameter_type' => 'pass_fail', 'value_boolean' => true, 'is_passed' => true],
            ['sample_number' => 3, 'parameter_name' => 'Dimension A', 'parameter_type' => 'measurement', 'value_numeric' => 12.4, 'is_passed' => true],
            ['sample_number' => 3, 'parameter_name' => 'Fit check', 'parameter_type' => 'pass_fail', 'value_boolean' => true, 'is_passed' => true],
        ], 500);

        $this->assertTrue($check->all_passed);
        $this->assertCount(6, $check->samples);
        $this->assertEquals(500, $check->production_quantity);
    }

    public function test_quality_check_fails_if_any_sample_fails(): void
    {
        $batch = $this->createBatch();

        $service = app(QualityCheckService::class);
        $check = $service->performCheck($batch, $this->operator, [
            ['sample_number' => 1, 'parameter_name' => 'Fit', 'parameter_type' => 'pass_fail', 'value_boolean' => true, 'is_passed' => true],
            ['sample_number' => 2, 'parameter_name' => 'Fit', 'parameter_type' => 'pass_fail', 'value_boolean' => false, 'is_passed' => false],
            ['sample_number' => 3, 'parameter_name' => 'Fit', 'parameter_type' => 'pass_fail', 'value_boolean' => true, 'is_passed' => true],
        ]);

        $this->assertFalse($check->all_passed);
    }

    public function test_quality_check_status(): void
    {
        $batch = $this->createBatch();
        $service = app(QualityCheckService::class);

        $status = $service->getCheckStatus($batch);
        $this->assertTrue($status['needs_check']);
        $this->assertEquals(0, $status['total_checks']);

        // Perform 3 checks
        for ($i = 0; $i < 3; $i++) {
            $service->performCheck($batch, $this->operator, [
                ['sample_number' => 1, 'parameter_name' => 'Test', 'parameter_type' => 'pass_fail', 'is_passed' => true],
            ]);
        }

        $status = $service->getCheckStatus($batch);
        $this->assertFalse($status['needs_check']);
        $this->assertTrue($status['batch_requirement_met']);
    }

    public function test_api_perform_quality_check(): void
    {
        $batch = $this->createBatch();

        $response = $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/quality-checks", [
                'production_quantity' => 250,
                'samples' => [
                    ['sample_number' => 1, 'parameter_name' => 'Dimension', 'parameter_type' => 'measurement', 'value_numeric' => 12.5, 'is_passed' => true],
                    ['sample_number' => 2, 'parameter_name' => 'Dimension', 'parameter_type' => 'measurement', 'value_numeric' => 12.3, 'is_passed' => true],
                    ['sample_number' => 3, 'parameter_name' => 'Dimension', 'parameter_type' => 'measurement', 'value_numeric' => 12.4, 'is_passed' => true],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.all_passed', true);
        $this->assertCount(3, $response->json('data.samples'));
    }

    public function test_api_quality_check_status(): void
    {
        $batch = $this->createBatch();

        $response = $this->actingAs($this->operator, 'sanctum')
            ->getJson("/api/v1/batches/{$batch->id}/quality-checks/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.needs_check', true);
    }

    // ── QC Templates ─────────────────────────────────────────────

    public function test_api_create_qc_template(): void
    {
        $template = ProcessTemplate::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/process-templates/{$template->id}/qc-templates", [
                'name' => 'Filter QC',
                'min_checks_per_batch' => 3,
                'min_checks_per_day' => 2,
                'samples_per_check' => 3,
                'parameters' => [
                    ['name' => 'Measured dimension', 'type' => 'measurement', 'unit' => 'mm', 'min' => 12.0, 'max' => 13.0],
                    ['name' => 'Fit check', 'type' => 'pass_fail'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Filter QC')
            ->assertJsonCount(2, 'data.parameters');
    }

    public function test_api_list_qc_templates(): void
    {
        $template = ProcessTemplate::factory()->create();
        QualityCheckTemplate::create([
            'process_template_id' => $template->id,
            'name' => 'Test QC',
            'parameters' => [['name' => 'Test', 'type' => 'pass_fail']],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/process-templates/{$template->id}/qc-templates");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    // ── Packaging Checklist ──────────────────────────────────────

    public function test_submit_packaging_checklist_all_pass(): void
    {
        $batch = $this->createBatch();
        $service = app(PackagingChecklistService::class);

        $checklist = $service->submit($batch, $this->operator, [
            'udi_readable' => true,
            'packaging_condition' => true,
            'labels_readable' => true,
            'label_matches_product' => true,
        ]);

        $this->assertTrue($checklist->fresh()->all_passed);
    }

    public function test_packaging_checklist_partial_fail(): void
    {
        $batch = $this->createBatch();
        $service = app(PackagingChecklistService::class);

        $checklist = $service->submit($batch, $this->operator, [
            'udi_readable' => true,
            'packaging_condition' => false,
            'labels_readable' => true,
            'label_matches_product' => true,
        ]);

        $this->assertFalse($checklist->fresh()->all_passed);
    }

    public function test_cannot_submit_checklist_twice(): void
    {
        $batch = $this->createBatch();
        $service = app(PackagingChecklistService::class);

        $service->submit($batch, $this->operator, [
            'udi_readable' => true,
            'packaging_condition' => true,
            'labels_readable' => true,
            'label_matches_product' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $service->submit($batch, $this->operator, [
            'udi_readable' => true,
            'packaging_condition' => true,
            'labels_readable' => true,
            'label_matches_product' => true,
        ]);
    }

    public function test_api_submit_packaging_checklist(): void
    {
        $batch = $this->createBatch();

        $response = $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/packaging-checklist", [
                'udi_readable' => true,
                'packaging_condition' => true,
                'labels_readable' => true,
                'label_matches_product' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.all_passed', true);
    }

    public function test_api_get_packaging_checklist(): void
    {
        $batch = $this->createBatch();

        $response = $this->actingAs($this->operator, 'sanctum')
            ->getJson("/api/v1/batches/{$batch->id}/packaging-checklist");

        $response->assertStatus(200)
            ->assertJsonPath('is_complete', false);
    }

    // ── Template Step Fields ─────────────────────────────────────

    public function test_template_step_has_confirmation_fields(): void
    {
        $template = ProcessTemplate::factory()->create();
        $step = \App\Models\TemplateStep::factory()->create([
            'process_template_id' => $template->id,
            'min_duration_minutes' => 720, // 12h drying
            'requires_confirmation' => true,
        ]);

        $this->assertEquals(720, $step->min_duration_minutes);
        $this->assertTrue($step->requires_confirmation);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function createBatch(): Batch
    {
        $line = Line::factory()->create();
        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        return Batch::create([
            'work_order_id' => $wo->id,
            'batch_number' => 1,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }
}
