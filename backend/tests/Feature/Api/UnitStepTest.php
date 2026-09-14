<?php

namespace Tests\Feature\Api;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\UnitStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Unit\UnitProgressionService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function authenticatedUser($role = 'Operator')
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** A Batch created from a Unit-mode ProcessTemplate. */
    protected function createUnitModeBatch()
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()
            ->withSteps(3)
            ->create(['product_type_id' => $productType->id, 'execution_mode' => ProcessTemplate::EXECUTION_MODE_UNIT]);

        $workOrder = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'process_snapshot' => $template->toSnapshot(),
        ]);

        $batch = app(WorkOrderService::class)->createBatch($workOrder, 5);

        return [$workOrder, $batch];
    }

    public function test_operator_can_register_a_unit(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createUnitModeBatch();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/unit-steps/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);

        $response->assertStatus(201)
            ->assertJsonPath('data.serial_no', 'SN-001');

        $this->assertDatabaseHas('unit_steps', ['serial_unit_id' => $response->json('data.id'), 'step_number' => 1, 'status' => UnitStep::STATUS_READY]);
    }

    public function test_register_requires_a_valid_batch_id(): void
    {
        $user = $this->authenticatedUser('Operator');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/unit-steps/register', ['batch_id' => 999999]);

        $response->assertStatus(422)->assertJsonValidationErrors(['batch_id']);
    }

    public function test_register_on_batch_mode_batch_is_rejected(): void
    {
        $user = $this->authenticatedUser('Operator');
        $workOrder = WorkOrder::factory()->create(); // default template is batch mode
        $batch = app(WorkOrderService::class)->createBatch($workOrder, 5);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/unit-steps/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'This batch is not running in unit execution mode.']);
    }

    public function test_guest_cannot_register_a_unit(): void
    {
        [, $batch] = $this->createUnitModeBatch();

        $response = $this->postJson('/api/v1/unit-steps/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);

        $response->assertStatus(401);
    }

    public function test_operator_can_start_and_complete_first_unit_step(): void
    {
        $user = $this->authenticatedUser('Operator');
        [, $batch] = $this->createUnitModeBatch();
        $token = $user->createToken('test')->plainTextToken;

        $register = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/unit-steps/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);
        $first = collect($register->json('unit_steps'))->firstWhere('step_number', 1);

        $start = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/unit-steps/{$first['id']}/start");
        $start->assertStatus(200)->assertJsonPath('data.status', UnitStep::STATUS_IN_PROGRESS);

        $complete = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/unit-steps/{$first['id']}/complete");
        $complete->assertStatus(200)->assertJsonPath('data.status', UnitStep::STATUS_DONE);
    }

    public function test_cannot_start_second_unit_step_before_first_done(): void
    {
        $user = $this->authenticatedUser('Operator');
        [, $batch] = $this->createUnitModeBatch();
        $token = $user->createToken('test')->plainTextToken;

        $register = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/unit-steps/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);
        $second = collect($register->json('unit_steps'))->firstWhere('step_number', 2);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/unit-steps/{$second['id']}/start");

        $response->assertStatus(422);
    }

    public function test_guest_cannot_start_a_unit_step(): void
    {
        [, $batch] = $this->createUnitModeBatch();
        // Register directly through the service (not an authenticated HTTP call)
        // so this test never authenticates at all — Sanctum's guard resolution
        // caches on the auth manager singleton for the life of the test, so an
        // earlier authenticated request here would leak into the "guest" one.
        $unit = app(UnitProgressionService::class)->registerUnit($batch, 'SN-001');
        $first = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 1)->first();

        $response = $this->postJson("/api/v1/unit-steps/{$first->id}/start");

        $response->assertStatus(401);
    }

    // ── Serialize later (#290 known-bugs item 3) ─────────────────────────────

    public function test_register_without_serial_or_auto_generate_creates_unserialized_unit(): void
    {
        $user = $this->authenticatedUser('Operator');
        [, $batch] = $this->createUnitModeBatch();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/unit-steps/register', ['batch_id' => $batch->id]);

        $response->assertStatus(201)->assertJsonPath('data.serial_no', null);
        $this->assertDatabaseHas('unit_steps', ['serial_unit_id' => $response->json('data.id'), 'step_number' => 1, 'status' => UnitStep::STATUS_READY]);
    }

    public function test_unserialized_unit_can_start_a_step(): void
    {
        [, $batch] = $this->createUnitModeBatch();
        $unit = app(UnitProgressionService::class)->registerUnit($batch);
        $first = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 1)->first();
        $user = $this->authenticatedUser('Operator');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/unit-steps/{$first->id}/start");

        $response->assertStatus(200)->assertJsonPath('data.status', UnitStep::STATUS_IN_PROGRESS);
    }

    public function test_operator_can_assign_a_serial_after_the_fact(): void
    {
        [, $batch] = $this->createUnitModeBatch();
        $progression = app(UnitProgressionService::class);
        $unit = $progression->registerUnit($batch);
        $first = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 1)->first();
        $user = $this->authenticatedUser('Operator');
        $progression->startUnitStep($first, $user);
        $progression->completeUnitStep($first->fresh(), $user);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/serial-units/{$unit->id}/assign-serial", ['serial_no' => 'SN-LATE-001']);

        $response->assertStatus(200)->assertJsonPath('data.serial_no', 'SN-LATE-001');
    }

    public function test_assign_serial_twice_is_rejected(): void
    {
        [, $batch] = $this->createUnitModeBatch();
        $unit = app(UnitProgressionService::class)->registerUnit($batch, 'SN-ALREADY-001');
        $user = $this->authenticatedUser('Operator');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/serial-units/{$unit->id}/assign-serial", ['serial_no' => 'SN-NEW-001']);

        $response->assertStatus(422)->assertJsonFragment(['message' => 'This unit already has a serial number.']);
    }

    public function test_guest_cannot_assign_a_serial(): void
    {
        [, $batch] = $this->createUnitModeBatch();
        $unit = app(UnitProgressionService::class)->registerUnit($batch);

        $response = $this->postJson("/api/v1/serial-units/{$unit->id}/assign-serial", ['serial_no' => 'SN-001']);

        $response->assertStatus(401);
    }
}
