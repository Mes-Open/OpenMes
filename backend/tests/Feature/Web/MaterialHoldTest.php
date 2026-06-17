<?php

namespace Tests\Feature\Web;

use App\Models\Batch;
use App\Models\BatchStepLotConsumption;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Lot\BatchReleaseService;
use App\Services\Quality\MaterialHoldService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manual quality hold/release on material lots + the batch-release quality gate
 * (no release while the work order is blocked or a consumed lot is on hold).
 */
class MaterialHoldTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_hold_and_release_a_lot(): void
    {
        $lot = MaterialLot::factory()->create(['status' => MaterialLot::STATUS_RELEASED]);

        $this->actingAs($this->admin)
            ->post("/admin/material-lots/{$lot->id}/hold", ['reason' => 'Suspected contamination'])
            ->assertRedirect()->assertSessionHas('success');

        $lot->refresh();
        $this->assertSame(MaterialLot::STATUS_QUARANTINE, $lot->status);
        $this->assertSame('Suspected contamination', $lot->hold_reason);
        $this->assertSame($this->admin->id, $lot->held_by_id);

        $this->actingAs($this->admin)
            ->post("/admin/material-lots/{$lot->id}/release")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->fresh()->status);
        $this->assertSame($this->admin->id, $lot->fresh()->released_by_id);
    }

    public function test_hold_requires_a_reason(): void
    {
        $lot = MaterialLot::factory()->create(['status' => MaterialLot::STATUS_RELEASED]);

        $this->actingAs($this->admin)
            ->post("/admin/material-lots/{$lot->id}/hold", [])
            ->assertSessionHasErrors('reason');
    }

    public function test_holding_a_consumed_lot_is_rejected(): void
    {
        $lot = MaterialLot::factory()->create(['status' => MaterialLot::STATUS_CONSUMED]);

        $this->expectException(\DomainException::class);
        app(MaterialHoldService::class)->hold($lot, 'too late', $this->admin);
    }

    public function test_batch_release_blocked_while_work_order_has_an_open_blocking_issue(): void
    {
        $batch = $this->doneBatch();
        $type = IssueType::factory()->create(['is_blocking' => true]);
        Issue::factory()->create([
            'work_order_id' => $batch->work_order_id,
            'issue_type_id' => $type->id,
            'status' => Issue::STATUS_OPEN,
        ]);

        $this->expectException(\RuntimeException::class);
        app(BatchReleaseService::class)->release($batch, $this->admin, Batch::RELEASE_FOR_PRODUCTION);
    }

    public function test_batch_release_blocked_when_a_consumed_lot_is_on_hold(): void
    {
        $batch = $this->doneBatch();
        $lot = MaterialLot::factory()->create(['status' => MaterialLot::STATUS_QUARANTINE]);
        BatchStepLotConsumption::create([
            'batch_step_id' => $batch->steps()->first()->id,
            'material_lot_id' => $lot->id,
            'quantity_consumed' => 1,
            'consumed_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        app(BatchReleaseService::class)->release($batch, $this->admin, Batch::RELEASE_FOR_PRODUCTION);
    }

    public function test_batch_release_succeeds_when_clear(): void
    {
        $batch = $this->doneBatch();

        $released = app(BatchReleaseService::class)->release($batch, $this->admin, Batch::RELEASE_FOR_PRODUCTION);

        $this->assertNotNull($released->released_at);
    }

    /** A DONE batch with a pre-assigned lot number (skips lot assignment on release). */
    private function doneBatch(): Batch
    {
        $wo = WorkOrder::factory()->create();
        $batch = app(WorkOrderService::class)->createBatch($wo, 10);
        $batch->update(['status' => Batch::STATUS_DONE, 'completed_at' => now(), 'lot_number' => 'LOT-TEST-1']);

        return $batch->fresh();
    }
}
