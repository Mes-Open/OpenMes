<?php

namespace Tests\Feature\ProcessTemplate;

use App\Models\BomItem;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSnapshot;
use App\Services\Material\MaterialAllocationService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A not-started order takes the whole edited template — steps and BOM — and a
 * BOM line knows the template step it is consumed at, so a material follows its
 * step instead of a step number. Deleting a step that materials are consumed at
 * needs an explicit choice for them.
 *
 * Template: 1 Cleaning, 2 Weigh ASH, 3 Weigh MCC; ASH during step 2, MCC during step 3.
 */
class TemplateChangeBomStepTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductType $productType;

    private ProcessTemplate $template;

    private TemplateStep $cleaning;

    private TemplateStep $weighAsh;

    private TemplateStep $weighMcc;

    private Material $ash;

    private Material $mcc;

    private BomItem $ashLine;

    private BomItem $mccLine;

    private WorkOrder $order;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->ash = $this->material($type, 'ASH', 'Ashwagandha');
        $this->mcc = $this->material($type, 'MCC', 'Cellulose');

        $this->productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id, 'is_active' => true]);
        $this->cleaning = $this->step(1, 'Cleaning');
        $this->weighAsh = $this->step(2, 'Weigh ASH');
        $this->weighMcc = $this->step(3, 'Weigh MCC');
        $this->ashLine = $this->bomLine($this->ash, $this->weighAsh);
        $this->mccLine = $this->bomLine($this->mcc, $this->weighMcc);

        $this->order = $this->release('WO-1');
        $this->base = "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";
    }

    // ---- snapshot and reorder -------------------------------------------------

    public function test_snapshot_bom_line_records_the_step_it_belongs_to(): void
    {
        $this->assertStep($this->order, $this->ash, $this->weighAsh->id, 2, 'Weigh ASH');
    }

    public function test_reorder_moves_bom_line_with_its_step_on_a_not_started_order(): void
    {
        $this->reorder([$this->cleaning, $this->weighMcc, $this->weighAsh]);

        $this->assertStep($this->order, $this->ash, $this->weighAsh->id, 3, 'Weigh ASH');
        $this->assertStep($this->order, $this->mcc, $this->weighMcc->id, 2, 'Weigh MCC');
        $this->assertSame(WorkOrderSnapshot::REASON_TEMPLATE_EDITED, $this->order->fresh()->snapshots->last()->reason);
    }

    public function test_after_reorder_material_is_allocated_at_the_moved_step(): void
    {
        $this->reorder([$this->cleaning, $this->weighMcc, $this->weighAsh]);

        $batch = $this->order->fresh()->batches()->first();
        $this->assertSame('Weigh MCC', $batch->steps()->where('step_number', 2)->value('name'));

        $svc = app(MaterialAllocationService::class);
        $atTwo = $svc->allocateForStep($batch->steps()->where('step_number', 2)->first(), $this->admin);
        $atThree = $svc->allocateForStep($batch->steps()->where('step_number', 3)->first(), $this->admin);

        $this->assertSame([$this->mcc->id], $atTwo->pluck('material_id')->all());
        $this->assertSame([$this->ash->id], $atThree->pluck('material_id')->all());
    }

    public function test_snapshot_without_bom_step_identity_is_rebuilt_with_it(): void
    {
        $snapshot = $this->order->process_snapshot;
        $snapshot['steps'] = array_map(fn ($s) => array_diff_key($s, ['template_step_id' => 1]), $snapshot['steps']);
        $snapshot['bom'] = array_map(fn ($row) => array_diff_key($row, ['template_step_id' => 1, 'step_name' => 1]), $snapshot['bom']);
        $this->order->update(['process_snapshot' => $snapshot]);

        $this->reorder([$this->cleaning, $this->weighMcc, $this->weighAsh]);

        $this->assertStep($this->order, $this->ash, $this->weighAsh->id, 3, 'Weigh ASH');
        $this->assertStep($this->order, $this->mcc, $this->weighMcc->id, 2, 'Weigh MCC');
    }

    // ---- BOM-only edits ---------------------------------------------------------

    public function test_bom_quantity_edit_reaches_not_started_order(): void
    {
        $this->ashLine->update(['quantity_per_unit' => 2]);

        $this->assertEquals(2, $this->row($this->order, $this->ash)['quantity_per_unit']);
        $latest = $this->order->fresh()->snapshots->last();
        $this->assertSame(WorkOrderSnapshot::REASON_TEMPLATE_EDITED, $latest->reason);
        $this->assertSame($latest->version, $this->order->fresh()->snapshot_version);
    }

    public function test_material_removed_from_template_leaves_not_started_order(): void
    {
        $this->actingAs($this->admin)->delete("{$this->base}/bom/{$this->ashLine->id}")->assertRedirect();

        $this->assertNull($this->row($this->order, $this->ash));
        $this->assertNotNull($this->row($this->order, $this->mcc));
    }

    public function test_material_added_to_template_reaches_not_started_order(): void
    {
        $extra = $this->material(MaterialType::first(), 'MGS', 'Magnesium stearate');
        $this->bomLine($extra, $this->cleaning);

        $this->assertStep($this->order, $extra, $this->cleaning->id, 1, 'Cleaning');
    }

    public function test_order_with_an_additional_bom_keeps_its_materials_when_refreshed(): void
    {
        $extraTemplate = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id, 'version' => 2, 'is_active' => false]);
        $coating = $this->material(MaterialType::first(), 'COAT', 'Coating');
        BomItem::create(['process_template_id' => $extraTemplate->id, 'material_id' => $coating->id, 'quantity_per_unit' => 1, 'consumed_at' => 'start']);
        $order = app(WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-MULTI', 'line_id' => Line::factory()->create()->id, 'product_type_id' => $this->productType->id,
            'planned_qty' => 10, 'bom_template_ids' => [$this->template->id, $extraTemplate->id],
        ]);

        $this->reorder([$this->cleaning, $this->weighMcc, $this->weighAsh]);

        $this->assertNotNull($this->row($order, $coating));
        $this->assertStep($order, $this->ash, $this->weighAsh->id, 3, 'Weigh ASH');
        $this->assertSame([$this->template->id, $extraTemplate->id], $order->fresh()->process_snapshot['bom_template_ids']);
    }

    public function test_legacy_order_without_a_recorded_bom_selection_is_refreshed_from_its_template(): void
    {
        $snapshot = $this->order->process_snapshot;
        unset($snapshot['bom_template_ids'], $snapshot['bom_templates']);
        $this->order->update(['process_snapshot' => $snapshot]);
        $this->order->bomTemplates()->detach();

        $this->reorder([$this->cleaning, $this->weighMcc, $this->weighAsh]);

        $this->assertStep($this->order, $this->ash, $this->weighAsh->id, 3, 'Weigh ASH');
    }

    public function test_sub_assembly_line_records_and_follows_its_step(): void
    {
        $part = ProductType::factory()->create(['name' => 'Capsule shell']);
        BomItem::create(['process_template_id' => $this->template->id, 'template_step_id' => $this->weighAsh->id, 'product_type_id' => $part->id, 'quantity_per_unit' => 1, 'consumed_at' => 'during']);

        $this->reorder([$this->cleaning, $this->weighMcc, $this->weighAsh]);

        $row = collect($this->order->fresh()->process_snapshot['bom'])->firstWhere('product_type_id', $part->id);
        $this->assertSame([$this->weighAsh->id, 3, 'Weigh ASH'], [$row['template_step_id'], $row['step_number'], $row['step_name']]);
    }

    // ---- orders that are not touched -------------------------------------------

    public function test_started_order_keeps_its_steps_and_bom(): void
    {
        $running = $this->release('WO-RUN');
        $running->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $released = $running->fresh()->process_snapshot;

        $this->reorder([$this->cleaning, $this->weighMcc, $this->weighAsh]);
        $this->ashLine->update(['quantity_per_unit' => 2]);

        $this->assertEquals($released, $running->fresh()->process_snapshot);
    }

    public function test_order_with_a_material_allocation_is_treated_as_started(): void
    {
        MaterialAllocation::factory()->create([
            'work_order_id' => $this->order->id,
            'batch_id' => $this->order->batches()->first()->id,
            'material_id' => $this->ash->id,
        ]);
        $released = $this->order->fresh()->process_snapshot;

        $this->reorder([$this->cleaning, $this->weighMcc, $this->weighAsh]);

        $this->assertEquals($released, $this->order->fresh()->process_snapshot);
    }

    // ---- deleting a step that materials are consumed at -------------------------

    public function test_deleting_step_with_materials_without_a_choice_is_refused(): void
    {
        $this->actingAs($this->admin)->deleteJson("{$this->base}/steps/{$this->weighMcc->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('bom_items')
            ->assertJsonFragment(['bom_items' => ['Materials are consumed at this step: Cellulose. Choose whether to remove them from the BOM or move them to another step.']]);

        $this->assertNotSoftDeleted($this->weighMcc);
        $this->assertNotSoftDeleted($this->mccLine);
    }

    public function test_api_delete_of_step_with_materials_needs_a_choice(): void
    {
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/v1/template-steps/{$this->weighMcc->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('bom_items');

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/v1/template-steps/{$this->weighMcc->id}", [
            'bom_items' => 'move', 'move_to_step_id' => $this->cleaning->id,
        ])->assertOk();

        $this->assertSoftDeleted($this->weighMcc);
        $this->assertSame($this->cleaning->id, $this->mccLine->fresh()->template_step_id);
    }

    public function test_operator_cannot_delete_a_step(): void
    {
        $operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));

        $this->actingAs($operator, 'sanctum')->deleteJson("/api/v1/template-steps/{$this->weighMcc->id}", ['bom_items' => 'delete'])
            ->assertForbidden();
        $this->assertNotSoftDeleted($this->weighMcc);
    }

    public function test_delete_step_and_remove_its_materials(): void
    {
        $this->actingAs($this->admin)
            ->delete("{$this->base}/steps/{$this->weighMcc->id}", ['bom_items' => 'delete'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($this->weighMcc);
        $this->assertSoftDeleted($this->mccLine);
        $this->assertNull($this->row($this->order, $this->mcc));
        $this->assertCount(2, $this->order->fresh()->process_snapshot['steps']);
    }

    public function test_delete_step_and_move_its_materials_to_another_step(): void
    {
        $this->actingAs($this->admin)
            ->delete("{$this->base}/steps/{$this->weighMcc->id}", ['bom_items' => 'move', 'move_to_step_id' => $this->cleaning->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($this->cleaning->id, $this->mccLine->fresh()->template_step_id);
        $this->assertStep($this->order, $this->mcc, $this->cleaning->id, 1, 'Cleaning');
    }

    public function test_materials_cannot_be_moved_to_the_deleted_step_or_another_template(): void
    {
        $foreign = TemplateStep::factory()->create(['process_template_id' => ProcessTemplate::factory()->create()->id, 'step_number' => 1]);

        foreach ([$this->weighMcc->id, $foreign->id, null] as $target) {
            $this->actingAs($this->admin)
                ->deleteJson("{$this->base}/steps/{$this->weighMcc->id}", ['bom_items' => 'move', 'move_to_step_id' => $target])
                ->assertStatus(422)
                ->assertJsonValidationErrors('move_to_step_id');
        }
        $this->assertNotSoftDeleted($this->weighMcc);
    }

    public function test_per_carton_line_cannot_be_moved_to_a_step_with_other_packing_sizes(): void
    {
        $this->weighMcc->update(['kind' => TemplateStep::KIND_PACKING, 'config' => ['unit' => 'carton', 'carton_capacity' => 12]]);
        $this->mccLine->update(['per' => BomItem::PER_CARTON]);

        $this->actingAs($this->admin)
            ->deleteJson("{$this->base}/steps/{$this->weighMcc->id}", ['bom_items' => 'move', 'move_to_step_id' => $this->cleaning->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('move_to_step_id');
        $this->assertSame($this->weighMcc->id, $this->mccLine->fresh()->template_step_id);
    }

    public function test_step_without_materials_is_deleted_without_a_choice(): void
    {
        $pack = $this->step(4, 'Pack');

        $this->actingAs($this->admin)->delete("{$this->base}/steps/{$pack->id}")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSoftDeleted($pack);
    }

    public function test_template_page_lists_the_materials_of_each_step(): void
    {
        $this->actingAs($this->admin)->get($this->base)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('processTemplate.steps.0.bom_material_names', [])
                ->where('processTemplate.steps.1.bom_material_names', ['Ashwagandha']));
    }

    // ---- helpers ---------------------------------------------------------------

    private function material(MaterialType $type, string $code, string $name): Material
    {
        return Material::create(['code' => $code, 'name' => $name, 'material_type_id' => $type->id, 'unit_of_measure' => 'kg', 'stock_quantity' => 1000]);
    }

    private function step(int $number, string $name): TemplateStep
    {
        return TemplateStep::factory()->create(['process_template_id' => $this->template->id, 'step_number' => $number, 'name' => $name]);
    }

    private function bomLine(Material $material, TemplateStep $step): BomItem
    {
        return BomItem::create([
            'process_template_id' => $this->template->id,
            'template_step_id' => $step->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 0.5,
            'scrap_percentage' => 0,
            'consumed_at' => 'during',
        ]);
    }

    private function release(string $no): WorkOrder
    {
        $order = app(WorkOrderService::class)->createWorkOrder([
            'order_no' => $no,
            'line_id' => Line::factory()->create()->id,
            'product_type_id' => $this->productType->id,
            'planned_qty' => 10,
        ]);
        app(WorkOrderService::class)->createBatch($order, 10);

        return $order;
    }

    /** @param  list<TemplateStep>  $steps */
    private function reorder(array $steps): void
    {
        $this->actingAs($this->admin)
            ->postJson("{$this->base}/steps/reorder", ['order' => array_map(fn ($s) => $s->id, $steps)])
            ->assertOk();
    }

    private function row(WorkOrder $order, Material $material): ?array
    {
        return collect($order->fresh()->process_snapshot['bom'])->firstWhere('material_id', $material->id);
    }

    private function assertStep(WorkOrder $order, Material $material, int $templateStepId, int $number, string $name): void
    {
        $row = $this->row($order, $material);
        $this->assertSame([$templateStepId, $number, $name], [$row['template_step_id'], $row['step_number'], $row['step_name']]);
    }
}
