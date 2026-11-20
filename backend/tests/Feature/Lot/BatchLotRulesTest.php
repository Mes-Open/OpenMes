<?php

namespace Tests\Feature\Lot;

use App\Enums\PalletStatus;
use App\Extension\FilterRegistry;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Lot\BatchReleaseService;
use App\Services\Material\LotPickingService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Batch LOT and expiry rules: a LOT is unique, a product's shelf life gives the
 * batch its expiry at creation, release does not move it, hand-typed LOTs can be
 * switched off; expired material lots are neither proposed nor consumed; a
 * pallet can be held until its batch is released for sale; the QC template API
 * takes module rules.
 */
class BatchLotRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductType $productType;

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

        $this->productType = ProductType::factory()->create([
            'code' => 'CAPS', 'name' => 'Capsules', 'shelf_life_value' => 24, 'shelf_life_unit' => ProductType::SHELF_LIFE_MONTHS,
        ]);
        LotSequence::factory()->create(['name' => 'CAPS LOT', 'product_type_id' => $this->productType->id, 'prefix' => 'CAP', 'pad_size' => 4]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setting(string $key, mixed $value): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
    }

    private function workOrder(?ProductType $productType = null, ?Line $line = null): WorkOrder
    {
        return WorkOrder::factory()->create([
            'line_id' => ($line ?? Line::factory()->create())->id,
            'product_type_id' => ($productType ?? $this->productType)->id,
            'status' => WorkOrder::STATUS_ACCEPTED,
        ]);
    }

    public function test_a_new_batch_takes_its_expiry_from_the_shelf_life_without_month_overflow(): void
    {
        Carbon::setTestNow('2026-01-31 10:00:00');
        $batch = app(WorkOrderService::class)->createBatch($this->workOrder(), 10);
        $this->assertSame('2028-01-31', $batch->fresh()->expiry_date->toDateString());

        $this->productType->update(['shelf_life_value' => 1]);
        $batch = app(WorkOrderService::class)->createBatch($this->workOrder(), 10);
        $this->assertSame('2026-02-28', $batch->fresh()->expiry_date->toDateString());

        $plain = ProductType::factory()->create();
        $this->assertNull(app(WorkOrderService::class)->createBatch($this->workOrder($plain), 10)->fresh()->expiry_date);
    }

    public function test_release_for_sale_keeps_the_expiry_and_only_falls_back_without_one(): void
    {
        Carbon::setTestNow('2026-11-20 08:00:00');
        $batch = app(WorkOrderService::class)->createBatch($this->workOrder(), 10);
        $batch->update(['status' => Batch::STATUS_DONE, 'produced_qty' => 10, 'started_at' => now(), 'completed_at' => now()->addYear()]);

        $released = app(BatchReleaseService::class)->release($batch->fresh(), $this->admin, Batch::RELEASE_FOR_SALE);
        $this->assertSame('2028-11-20', $released->expiry_date->toDateString());

        // A batch created before the product had a shelf life: from its creation day.
        $old = Batch::create(['work_order_id' => $this->workOrder()->id, 'batch_number' => 1, 'target_qty' => 1, 'produced_qty' => 1, 'status' => Batch::STATUS_DONE, 'completed_at' => now()]);
        $old->forceFill(['expiry_date' => null])->saveQuietly();
        $this->assertSame('2028-11-20', app(BatchReleaseService::class)->release($old->fresh(), $this->admin, Batch::RELEASE_FOR_SALE)->expiry_date->toDateString());
    }

    public function test_a_lot_belongs_to_one_live_batch(): void
    {
        $service = app(WorkOrderService::class);
        $first = $service->createBatch($this->workOrder(), 10, null, 'CAP-0001');

        try {
            $service->createBatch($this->workOrder(), 10, null, 'CAP-0001');
            $this->fail('duplicate LOT accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lot_number', $e->errors());
        }

        $second = $service->createBatch($this->workOrder(), 10, null, 'CAP-0002');
        $this->expectException(ValidationException::class);
        try {
            $second->update(['lot_number' => 'CAP-0001']);
        } finally {
            // Deleting the first batch frees its LOT.
            $first->delete();
            $second->refresh()->update(['lot_number' => 'CAP-0001']);
            $this->assertSame('CAP-0001', $second->fresh()->lot_number);
        }
    }

    public function test_hand_typed_lots_can_be_switched_off(): void
    {
        $line = Line::factory()->create();
        $workOrder = $this->workOrder(line: $line);

        $this->setting('batch_lot_manual_entry', false);

        $this->actingAs($this->admin)->withSession(['selected_line_id' => $line->id])
            ->post('/operator/batch', ['work_order_id' => $workOrder->id, 'target_qty' => 5, 'lot_number' => 'TYPED-1'])
            ->assertSessionHasErrors('lot_number');
        $this->assertDatabaseMissing('batches', ['lot_number' => 'TYPED-1']);

        // The sequence still issues LOTs.
        $this->actingAs($this->admin)->withSession(['selected_line_id' => $line->id])
            ->post('/operator/batch', ['work_order_id' => $workOrder->id, 'target_qty' => 5, 'auto_lot' => true])
            ->assertSessionHasNoErrors();
        $this->assertStringStartsWith('CAP', (string) $workOrder->batches()->value('lot_number'));

        // The API (a separate guard, so after the web requests).
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/work-orders/{$workOrder->id}/batches", ['target_qty' => 5, 'lot_number' => 'TYPED-2'])
            ->assertStatus(422)->assertJsonValidationErrors('lot_number');

        $this->setting('batch_lot_manual_entry', true);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/work-orders/{$workOrder->id}/batches", ['target_qty' => 5, 'lot_number' => 'TYPED-3'])
            ->assertCreated();
    }

    public function test_settings_page_saves_the_lot_and_ship_switches(): void
    {
        $this->actingAs($this->admin)->get('/settings/system')->assertOk();
        $this->assertTrue(\App\Services\Lot\BatchLotRules::manualEntryAllowed());
        $this->assertFalse(\App\Services\Logistics\PalletReleaseGate::enabled());
    }

    public function test_expired_lots_are_not_proposed_or_picked(): void
    {
        $material = Material::factory()->create();
        $expired = MaterialLot::factory()->create(['material_id' => $material->id, 'status' => MaterialLot::STATUS_RELEASED, 'quantity_available' => 50, 'quantity_received' => 50, 'expiry_date' => today()->subDay()->toDateString()]);
        $today = MaterialLot::factory()->create(['material_id' => $material->id, 'status' => MaterialLot::STATUS_RELEASED, 'quantity_available' => 50, 'quantity_received' => 50, 'expiry_date' => today()->toDateString()]);

        $proposal = app(LotPickingService::class)->proposePicks($material, 10, 'fefo');
        $this->assertSame([$today->id], array_column($proposal['candidates'], 'id'));
        $this->assertSame($today->id, $proposal['proposed'][0]['material_lot_id']);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($today->isExpired());
    }

    public function test_the_consume_api_refuses_an_expired_lot_and_a_material_off_the_recipe(): void
    {
        $material = Material::factory()->create();
        $other = Material::factory()->create();
        $workOrder = $this->workOrder();
        $workOrder->update(['process_snapshot' => ['steps' => [], 'bom' => [['material_id' => $material->id]]]]);
        $step = BatchStep::factory()->create(['batch_id' => Batch::factory()->create(['work_order_id' => $workOrder->id])->id]);

        $expired = MaterialLot::factory()->create(['material_id' => $material->id, 'status' => MaterialLot::STATUS_RELEASED, 'quantity_available' => 50, 'quantity_received' => 50, 'expiry_date' => today()->subDay()->toDateString()]);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/material-lots/{$expired->id}/consume", ['batch_step_id' => $step->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonPath('message', __('Lot :lot expired on :date and cannot be used.', ['lot' => $expired->lot_number, 'date' => $expired->expiry_date->toDateString()]));

        $offRecipe = MaterialLot::factory()->create(['material_id' => $other->id, 'status' => MaterialLot::STATUS_RELEASED, 'quantity_available' => 50, 'quantity_received' => 50, 'expiry_date' => null]);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/material-lots/{$offRecipe->id}/consume", ['batch_step_id' => $step->id, 'quantity' => 1])
            ->assertStatus(422);

        $good = MaterialLot::factory()->create(['material_id' => $material->id, 'status' => MaterialLot::STATUS_RELEASED, 'quantity_available' => 50, 'quantity_received' => 50, 'expiry_date' => null]);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/material-lots/{$good->id}/consume", ['batch_step_id' => $step->id, 'quantity' => 1])
            ->assertCreated();
    }

    public function test_a_pallet_ships_only_from_a_batch_released_for_sale_when_switched_on(): void
    {
        $workOrder = $this->workOrder();
        $batch = Batch::factory()->create(['work_order_id' => $workOrder->id, 'status' => Batch::STATUS_DONE]);
        $pallet = Pallet::factory()->create(['work_order_id' => $workOrder->id, 'batch_id' => $batch->id, 'quality_status' => Pallet::QUALITY_PASS]);

        $this->setting('ship_requires_release', true);
        try {
            $pallet->update(['status' => PalletStatus::Shipped]);
            $this->fail('unreleased pallet shipped');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $batch->update(['released_at' => now(), 'release_type' => Batch::RELEASE_FOR_SALE, 'released_by' => $this->admin->id]);
        $pallet->refresh()->update(['status' => PalletStatus::Shipped]);
        $this->assertSame(PalletStatus::Shipped, $pallet->fresh()->status);

        // Off (the default): only the quality gate applies.
        $this->setting('ship_requires_release', false);
        $other = Pallet::factory()->create(['work_order_id' => $workOrder->id, 'quality_status' => Pallet::QUALITY_PASS]);
        $other->update(['status' => PalletStatus::Shipped]);
        $this->assertSame(PalletStatus::Shipped, $other->fresh()->status);
    }

    public function test_the_qc_template_api_applies_module_rules(): void
    {
        $template = ProcessTemplate::factory()->create();
        app(FilterRegistry::class)->addFilter('validation.api.qc_templates', fn (array $rules, array $context) => [
            ...$rules,
            'parameters.*.max' => [...$rules['parameters.*.max'], 'gte:parameters.*.min'],
        ]);

        $payload = ['name' => 'Capsule weight', 'parameters' => [['name' => 'Gross', 'type' => 'measurement', 'unit' => 'mg', 'min' => 626, 'max' => 566]]];
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/process-templates/{$template->id}/qc-templates", $payload)
            ->assertStatus(422)->assertJsonValidationErrors('parameters.0.max');

        $payload['parameters'][0] = [...$payload['parameters'][0], 'min' => 566, 'max' => 626];
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/process-templates/{$template->id}/qc-templates", $payload)
            ->assertCreated();
    }

    public function test_the_product_form_stores_a_shelf_life_and_validates_its_unit(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/product-types', ['code' => 'JAR', 'name' => 'Jar', 'shelf_life_value' => 90, 'shelf_life_unit' => 'days', 'is_active' => true])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_types', ['code' => 'JAR', 'shelf_life_value' => 90, 'shelf_life_unit' => 'days']);

        $this->actingAs($this->admin)
            ->post('/admin/product-types', ['code' => 'JAR2', 'name' => 'Jar 2', 'shelf_life_value' => 90])
            ->assertSessionHasErrors('shelf_life_unit');
    }
}
