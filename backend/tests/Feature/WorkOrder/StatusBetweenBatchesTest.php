<?php

namespace Tests\Feature\WorkOrder;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BomItem;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\IssueService;
use App\Services\Material\NetRequirementsService;
use App\Services\WorkOrder\BatchService;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * An order that has finished a batch has started: between two batches it is
 * still in progress, never back in the queue as PENDING — where it could be
 * deleted or rejected with parts already made, and MRP would plan its whole
 * material demand again.
 */
class StatusBetweenBatchesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));
        $this->operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));
    }

    public static function flows(): array
    {
        return ['whole batch' => [ProductionFlow::WHOLE_BATCH], 'transfer' => [ProductionFlow::TRANSFER]];
    }

    /** An order of 100 whose first batch of 40 is on its only step. */
    private function orderWithRunningBatch(string $status = WorkOrder::STATUS_IN_PROGRESS, ?ProductType $productType = null): array
    {
        $productType ??= ProductType::factory()->create();
        $order = WorkOrder::factory()->create([
            'process_snapshot' => null,
            'due_date' => today()->addDays(5),
            'line_id' => Line::factory()->create()->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
            'produced_qty' => 0,
            'status' => $status,
        ]);
        $batch = Batch::factory()->create([
            'work_order_id' => $order->id,
            'batch_number' => 1,
            'target_qty' => 40,
            'produced_qty' => 0,
            'status' => Batch::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
        ]);
        $step = BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 1,
            'name' => 'Assemble',
            'status' => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
            'started_by_id' => $this->operator->id,
        ]);

        return [$order, $step];
    }

    private function finishFirstBatch(?ProductType $productType = null): WorkOrder
    {
        [$order, $step] = $this->orderWithRunningBatch(productType: $productType);
        if (ProductionFlow::isTransfer()) {
            app(BatchService::class)->recordQuantity($step, $this->operator, 40);
            $step = $step->fresh();
        }
        app(BatchService::class)->completeStep($step, $this->operator, ['produced_qty' => 40]);

        $order->refresh();
        $this->assertSame(Batch::STATUS_DONE, $order->batches()->first()->status, 'The first batch finished.');
        $this->assertEquals(40, (float) $order->produced_qty);

        return $order;
    }

    #[DataProvider('flows')]
    public function test_an_order_between_batches_stays_in_progress(string $flow): void
    {
        ProductionFlow::set($flow);

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->finishFirstBatch()->status);
    }

    public function test_an_order_between_batches_cannot_be_deleted_or_rejected(): void
    {
        $order = $this->finishFirstBatch();
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->deleteJson("/api/v1/work-orders/{$order->id}")->assertStatus(422);
        $api->postJson("/api/v1/work-orders/{$order->id}/reject")->assertStatus(422);

        $this->assertNotSoftDeleted($order);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
    }

    public function test_an_order_between_batches_can_be_paused(): void
    {
        $order = $this->finishFirstBatch();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/work-orders/{$order->id}/pause")
            ->assertOk();

        $this->assertSame(WorkOrder::STATUS_PAUSED, $order->fresh()->status);
    }

    public function test_mrp_does_not_plan_the_whole_order_again_between_batches(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id, 'is_active' => true, 'version' => 1]);
        BomItem::factory()->create([
            'process_template_id' => $template->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 2,
            'scrap_percentage' => 0,
        ]);

        $this->finishFirstBatch($productType);

        // Batch 1 already used its share; an order on the floor is not "un-started demand".
        $report = app(NetRequirementsService::class)->report(today()->startOfDay(), today()->addDays(30)->endOfDay());
        $this->assertSame([], $report['requirements']);
    }

    public function test_an_order_unblocked_between_batches_goes_back_to_in_progress(): void
    {
        // Blocked before the pre-block status was recorded: the status is guessed from the batches.
        $order = $this->finishFirstBatch();
        $order->update(['status' => WorkOrder::STATUS_BLOCKED, 'status_before_block' => null]);

        app(IssueService::class)->checkAndUnblockWorkOrder($order->id);

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
    }

    public function test_an_order_that_finishes_its_plan_is_done(): void
    {
        [$order, $step] = $this->orderWithRunningBatch();
        $order->batches()->first()->update(['target_qty' => 100]);

        app(BatchService::class)->completeStep($step->fresh(), $this->operator, ['produced_qty' => 100]);

        $this->assertSame(WorkOrder::STATUS_DONE, $order->fresh()->status);
    }

    public function test_an_order_with_no_finished_batch_keeps_its_queue_status(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING, 'planned_qty' => 100]);
        app(\App\Services\WorkOrder\WorkOrderService::class)->updateWorkOrderStatus($order);
        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }
}
