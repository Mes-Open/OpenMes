<?php

namespace Tests\Feature\Quality;

use App\Enums\PalletStatus;
use App\Models\Batch;
use App\Models\Pallet;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Production\QualityCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quality results linked to a pallet (#106): a quality check can target a pallet,
 * the pallet derives a quality_status (pending/pass/fail), and the status gates
 * shipping.
 */
class PalletQualityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function service(): QualityCheckService
    {
        return app(QualityCheckService::class);
    }

    /** A pallet + a batch on the same work order. */
    private function palletWithBatch(): array
    {
        $wo = WorkOrder::factory()->create();
        $pallet = Pallet::factory()->closed()->create(['work_order_id' => $wo->id]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);

        return [$pallet, $batch];
    }

    private function samples(bool $pass): array
    {
        return [[
            'sample_number' => 1, 'parameter_name' => 'Length', 'parameter_type' => 'measurement',
            'value_numeric' => 10, 'is_passed' => $pass,
        ]];
    }

    // ── Linking + status derivation ──────────────────────────────────────────

    public function test_pallet_starts_pending_with_no_quality_checks(): void
    {
        $pallet = Pallet::factory()->create();
        $this->assertSame(Pallet::QUALITY_PENDING, $pallet->fresh()->quality_status);
        $this->assertDatabaseHas('pallets', ['id' => $pallet->id, 'quality_status' => 'pending']);
    }

    public function test_linking_a_passing_check_sets_pallet_pass(): void
    {
        [$pallet, $batch] = $this->palletWithBatch();

        $check = $this->service()->performCheck($batch, $this->admin, $this->samples(true), null, null, null, $pallet);

        $this->assertSame($pallet->id, $check->pallet_id);
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);
    }

    public function test_linking_a_failing_check_sets_pallet_fail(): void
    {
        [$pallet, $batch] = $this->palletWithBatch();

        $this->service()->performCheck($batch, $this->admin, $this->samples(false), null, null, null, $pallet);

        $this->assertSame(Pallet::QUALITY_FAIL, $pallet->fresh()->quality_status);
    }

    public function test_one_failing_check_among_many_makes_the_pallet_fail(): void
    {
        [$pallet, $batch] = $this->palletWithBatch();

        $this->service()->performCheck($batch, $this->admin, $this->samples(true), null, null, null, $pallet);
        $this->service()->performCheck($batch, $this->admin, $this->samples(false), null, null, null, $pallet);

        $this->assertSame(Pallet::QUALITY_FAIL, $pallet->fresh()->quality_status);
    }

    public function test_check_without_pallet_leaves_pallets_untouched(): void
    {
        [$pallet, $batch] = $this->palletWithBatch();

        $this->service()->performCheck($batch, $this->admin, $this->samples(false)); // no pallet

        $this->assertSame(Pallet::QUALITY_PENDING, $pallet->fresh()->quality_status);
    }

    // ── Ship-gate ────────────────────────────────────────────────────────────

    public function test_pending_pallet_cannot_be_shipped(): void
    {
        $pallet = Pallet::factory()->closed()->create(); // quality_status = pending

        $this->actingAs($this->admin)
            ->from('/admin/pallets')
            ->put("/admin/pallets/{$pallet->id}", [
                'work_order_id' => $pallet->work_order_id,
                'status' => PalletStatus::Shipped->value,
            ])
            ->assertSessionHas('error');

        $this->assertSame(PalletStatus::Closed, $pallet->fresh()->status);
    }

    public function test_failed_pallet_cannot_be_shipped(): void
    {
        $pallet = Pallet::factory()->closed()->create(['quality_status' => Pallet::QUALITY_FAIL]);

        $this->actingAs($this->admin)
            ->from('/admin/pallets')
            ->put("/admin/pallets/{$pallet->id}", [
                'work_order_id' => $pallet->work_order_id,
                'status' => PalletStatus::Shipped->value,
            ])
            ->assertSessionHas('error');

        $this->assertSame(PalletStatus::Closed, $pallet->fresh()->status);
    }

    public function test_passed_pallet_can_be_shipped(): void
    {
        $pallet = Pallet::factory()->closed()->create(['quality_status' => Pallet::QUALITY_PASS]);

        $this->actingAs($this->admin)
            ->put("/admin/pallets/{$pallet->id}", [
                'work_order_id' => $pallet->work_order_id,
                'status' => PalletStatus::Shipped->value,
            ])
            ->assertRedirect('/admin/pallets');

        $this->assertSame(PalletStatus::Shipped, $pallet->fresh()->status);
        $this->assertNotNull($pallet->fresh()->shipped_at);
    }

    // ── Endpoint (QC-task perform) ───────────────────────────────────────────

    public function test_performing_a_control_links_the_chosen_pallet(): void
    {
        $wo = WorkOrder::factory()->create();
        $pallet = Pallet::factory()->closed()->create(['work_order_id' => $wo->id]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        $trigger = QualityControlTrigger::factory()->create();
        $task = QualityControlTask::factory()->create([
            'quality_control_trigger_id' => $trigger->id,
            'batch_id' => $batch->id,
            'work_order_id' => $wo->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/quality-tasks/{$task->id}/perform", [
                'pallet_id' => $pallet->id,
                'samples' => $this->samples(true),
            ])
            ->assertRedirect();

        $this->assertSame($pallet->id, $task->fresh()->qualityCheck->pallet_id);
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);
    }

    public function test_performing_rejects_a_pallet_from_another_work_order(): void
    {
        $batch = Batch::factory()->create();
        $otherPallet = Pallet::factory()->create(); // different work order
        $trigger = QualityControlTrigger::factory()->create();
        $task = QualityControlTask::factory()->create([
            'quality_control_trigger_id' => $trigger->id,
            'batch_id' => $batch->id,
            'work_order_id' => $batch->work_order_id,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/quality-tasks/{$task->id}/perform", [
                'pallet_id' => $otherPallet->id,
                'samples' => $this->samples(true),
            ])
            ->assertSessionHas('error');

        $this->assertSame(Pallet::QUALITY_PENDING, $otherPallet->fresh()->quality_status);
    }
}
