<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Lot\BatchReleaseService;
use App\Services\Lot\LotService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LotTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private ProductType $productType;

    private LotSequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        foreach (['view work orders', 'create work orders', 'edit work orders', 'delete work orders'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }
        $adminRole->givePermissionTo(Permission::all());

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->productType = ProductType::factory()->create(['code' => 'FILTER', 'name' => 'Filter']);
        $this->sequence = LotSequence::factory()->create([
            'name' => 'Filter LOT',
            'product_type_id' => $this->productType->id,
            'prefix' => 'FLT',
            'pad_size' => 4,
            'year_prefix' => true,
        ]);
    }

    // ── LOT Generation ──────────────────────────────────────────

    public function test_lot_generation_format(): void
    {
        $service = app(LotService::class);
        $lot = $service->generateLot($this->productType);

        $year = now()->format('Y');
        $this->assertEquals("FLT-{$year}-0001", $lot);
    }

    public function test_lot_generation_increments(): void
    {
        $service = app(LotService::class);
        $lot1 = $service->generateLot($this->productType);
        $lot2 = $service->generateLot($this->productType);

        $year = now()->format('Y');
        $this->assertEquals("FLT-{$year}-0001", $lot1);
        $this->assertEquals("FLT-{$year}-0002", $lot2);
    }

    public function test_lot_generation_without_year(): void
    {
        $this->sequence->update(['year_prefix' => false]);

        $service = app(LotService::class);
        $lot = $service->generateLot($this->productType);

        $this->assertEquals('FLT-0001', $lot);
    }

    public function test_lot_generation_with_suffix(): void
    {
        $this->sequence->update(['suffix' => 'A']);

        $service = app(LotService::class);
        $lot = $service->generateLot($this->productType);

        $year = now()->format('Y');
        $this->assertEquals("FLT-{$year}-0001-A", $lot);
    }

    public function test_lot_generation_with_padding(): void
    {
        $this->sequence->update(['pad_size' => 6]);

        $service = app(LotService::class);
        $lot = $service->generateLot($this->productType);

        $year = now()->format('Y');
        $this->assertEquals("FLT-{$year}-000001", $lot);
    }

    public function test_lot_generation_falls_back_to_default(): void
    {
        $otherType = ProductType::factory()->create(['code' => 'CLIP', 'name' => 'Clip']);
        $defaultSeq = LotSequence::factory()->create([
            'name' => 'Default LOT',
            'product_type_id' => null,
            'prefix' => 'DEF',
        ]);

        $service = app(LotService::class);
        $lot = $service->generateLot($otherType);

        $year = now()->format('Y');
        $this->assertEquals("DEF-{$year}-0001", $lot);
    }

    public function test_lot_generation_throws_without_sequence(): void
    {
        $otherType = ProductType::factory()->create(['code' => 'NONE']);

        $service = app(LotService::class);
        $this->expectException(\RuntimeException::class);
        $service->generateLot($otherType);
    }

    public function test_lot_preview(): void
    {
        $service = app(LotService::class);
        $preview = $service->previewNext($this->productType);

        $year = now()->format('Y');
        $this->assertEquals("FLT-{$year}-0001", $preview);

        // Preview should NOT increment
        $this->assertEquals(1, $this->sequence->fresh()->next_number);
    }

    // ── LOT on Batch Start ──────────────────────────────────────

    public function test_lot_assigned_on_batch_start(): void
    {
        $service = app(LotService::class);
        $batch = $this->createTestBatch();

        $service->assignLotOnStart($batch, $this->productType);

        $batch->refresh();
        $year = now()->format('Y');
        $this->assertEquals("FLT-{$year}-0001", $batch->lot_number);
        $this->assertEquals(Batch::LOT_ON_START, $batch->lot_assigned_at);
    }

    // ── Workstation Assignment ───────────────────────────────────

    public function test_batch_created_with_workstation(): void
    {
        $line = Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $wo = $this->createWorkOrder($line);

        $service = app(WorkOrderService::class);
        $batch = $service->createBatch($wo, 100, $workstation->id);

        $this->assertEquals($workstation->id, $batch->workstation_id);
    }

    public function test_batch_created_with_lot_number(): void
    {
        $line = Line::factory()->create();
        $wo = $this->createWorkOrder($line);

        $service = app(WorkOrderService::class);
        $batch = $service->createBatch($wo, 100, null, 'FLT-2026-0001');

        $this->assertEquals('FLT-2026-0001', $batch->lot_number);
        $this->assertEquals(Batch::LOT_ON_START, $batch->lot_assigned_at);
    }

    public function test_workstation_conflict_detection(): void
    {
        $line = Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $wo = $this->createWorkOrder($line);

        $service = app(WorkOrderService::class);
        $service->createBatch($wo, 100, $workstation->id);

        $releaseService = app(BatchReleaseService::class);
        $conflicts = $releaseService->checkWorkstationConflicts($workstation->id);

        $this->assertCount(1, $conflicts);
    }

    // ── Release Workflow ─────────────────────────────────────────

    public function test_release_for_sale_sets_expiry(): void
    {
        $batch = $this->createCompletedBatch();

        $releaseService = app(BatchReleaseService::class);
        $released = $releaseService->release($batch, $this->admin, Batch::RELEASE_FOR_SALE);

        $this->assertNotNull($released->released_at);
        $this->assertEquals($this->admin->id, $released->released_by);
        $this->assertEquals(Batch::RELEASE_FOR_SALE, $released->release_type);
        $this->assertNotNull($released->expiry_date);
        $this->assertEquals(
            $batch->completed_at->addYears(3)->toDateString(),
            $released->expiry_date->toDateString()
        );
    }

    public function test_release_for_production_assigns_lot(): void
    {
        $batch = $this->createCompletedBatch();
        $this->assertNull($batch->lot_number);

        $releaseService = app(BatchReleaseService::class);
        $released = $releaseService->release($batch, $this->admin, Batch::RELEASE_FOR_PRODUCTION);

        $this->assertNotNull($released->lot_number);
        $this->assertEquals(Batch::LOT_ON_RELEASE, $released->lot_assigned_at);
        $this->assertEquals(Batch::RELEASE_FOR_PRODUCTION, $released->release_type);
        $this->assertNull($released->expiry_date); // No expiry for semi-finished
    }

    public function test_cannot_release_pending_batch(): void
    {
        $batch = $this->createTestBatch();

        $releaseService = app(BatchReleaseService::class);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('completed (DONE)');
        $releaseService->release($batch, $this->admin, Batch::RELEASE_FOR_SALE);
    }

    public function test_cannot_release_twice(): void
    {
        $batch = $this->createCompletedBatch();

        $releaseService = app(BatchReleaseService::class);
        $releaseService->release($batch, $this->admin, Batch::RELEASE_FOR_SALE);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already released');
        $releaseService->release($batch->fresh(), $this->admin, Batch::RELEASE_FOR_SALE);
    }

    // ── LOT Sequence CRUD (Web) ──────────────────────────────────

    public function test_admin_can_list_sequences(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.lot-sequences.index'));
        $response->assertStatus(200);
        $response->assertSee('Filter LOT');
    }

    public function test_admin_can_create_sequence(): void
    {
        $newType = ProductType::factory()->create(['code' => 'CLIP']);

        $response = $this->actingAs($this->admin)->post(route('admin.lot-sequences.store'), [
            'name' => 'Clip LOT',
            'product_type_id' => $newType->id,
            'prefix' => 'KLP',
            'pad_size' => 4,
            'year_prefix' => true,
        ]);

        $response->assertRedirect(route('admin.lot-sequences.index'));
        $this->assertDatabaseHas('lot_sequences', ['prefix' => 'KLP', 'product_type_id' => $newType->id]);
    }

    // ── API Tests ────────────────────────────────────────────────

    public function test_api_list_sequences(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/lot-sequences');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_api_preview_lot(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/lot/preview/{$this->productType->id}");

        $year = now()->format('Y');
        $response->assertStatus(200)
            ->assertJsonPath('data.next_lot', "FLT-{$year}-0001");
    }

    public function test_api_create_batch_with_workstation_and_lot(): void
    {
        $line = Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $wo = $this->createWorkOrder($line);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/work-orders/{$wo->id}/batches", [
                'target_qty' => min(500, (float) $wo->planned_qty),
                'workstation_id' => $workstation->id,
                'lot_number' => 'FLT-2026-TEST',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.workstation_id', $workstation->id)
            ->assertJsonPath('data.lot_number', 'FLT-2026-TEST');
    }

    public function test_api_release_batch(): void
    {
        $batch = $this->createCompletedBatch();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/release", [
                'release_type' => 'for_sale',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.release_type', 'for_sale');
        $this->assertNotNull($response->json('data.released_at'));
        $this->assertNotNull($response->json('data.expiry_date'));
    }

    public function test_api_release_returns_422_for_pending(): void
    {
        $batch = $this->createTestBatch();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/release", [
                'release_type' => 'for_sale',
            ]);

        $response->assertStatus(422);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function createWorkOrder(?Line $line = null): WorkOrder
    {
        return WorkOrder::factory()->create([
            'line_id' => $line?->id ?? Line::factory()->create()->id,
            'product_type_id' => $this->productType->id,
            'status' => WorkOrder::STATUS_ACCEPTED,
        ]);
    }

    private function createTestBatch(): Batch
    {
        $wo = $this->createWorkOrder();

        return Batch::create([
            'work_order_id' => $wo->id,
            'batch_number' => 1,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);
    }

    private function createCompletedBatch(): Batch
    {
        $wo = $this->createWorkOrder();

        return Batch::create([
            'work_order_id' => $wo->id,
            'batch_number' => 1,
            'target_qty' => 100,
            'produced_qty' => 100,
            'status' => Batch::STATUS_DONE,
            'started_at' => now()->subHours(8),
            'completed_at' => now(),
        ]);
    }
}
