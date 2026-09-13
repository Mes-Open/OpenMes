<?php

namespace Tests\Feature\WorkOrder;

use App\Import\Importers\BomImporter;
use App\Models\Batch;
use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CsvImport\WorkOrderImportService;
use App\Services\Material\NetRequirementsService;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\ComponentWorkOrderService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComponentWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private function process(string $code): ProcessTemplate
    {
        $product = ProductType::factory()->create(['code' => $code, 'unit_of_measure' => 'pcs']);
        $template = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $product->id]);
        $template->steps()->update(['requires_confirmation' => false]);

        return $template;
    }

    private function addComponent(ProcessTemplate $parent, ProcessTemplate $child, float $qty): BomItem
    {
        return BomItem::create(['process_template_id' => $parent->id, 'product_type_id' => $child->product_type_id, 'quantity_per_unit' => $qty]);
    }

    private function order(ProcessTemplate $template, string $number = 'SOFA-1'): WorkOrder
    {
        return app(WorkOrderService::class)->createWorkOrder([
            'order_no' => $number, 'product_type_id' => $template->product_type_id,
            'planned_qty' => 2, 'generate_components' => true, 'customer_order_no' => 'SO1435', 'due_date' => now()->addDay(),
        ]);
    }

    public function test_two_sofas_generate_eight_parts_and_freeze_their_specification(): void
    {
        $sofa = $this->process('SOFA');
        $part = $this->process('PART');
        $line = $this->addComponent($sofa, $part, 4);
        $line->update(['extra_data' => ['foam_grade' => 'VB 18/40', 'length_mm' => 1985]]);
        $order = $this->order($sofa);
        $child = $order->childWorkOrders()->sole();
        $this->assertEquals(8, $child->planned_qty);
        $this->assertEquals('SO1435', $child->customer_order_no);
        $this->assertNull($child->customer_id);
        $this->assertEquals(1985, $child->extra_data['component_specification']['extra_data']['length_mm']);
        $line->update(['quantity_per_unit' => 99, 'extra_data' => ['length_mm' => 5]]);
        app(ComponentWorkOrderService::class)->generate($order);
        $this->assertEquals(8, $child->fresh()->planned_qty);
        $this->assertEquals(1985, $child->fresh()->extra_data['component_specification']['extra_data']['length_mm']);
        $this->assertCount(1, $order->childWorkOrders()->get());
        $this->assertEquals(0, $order->produced_qty);
    }

    public function test_three_levels_and_material_planning_do_not_double_count(): void
    {
        $sofa = $this->process('SOFA');
        $frame = $this->process('FRAME');
        $rail = $this->process('RAIL');
        $this->addComponent($sofa, $frame, 2);
        $this->addComponent($frame, $rail, 3);
        $wood = Material::factory()->create(['stock_quantity' => 0]);
        BomItem::factory()->create(['process_template_id' => $rail->id, 'material_id' => $wood->id, 'quantity_per_unit' => 0.5, 'scrap_percentage' => 0]);
        $root = $this->order($sofa);
        $this->assertEquals(4, $root->childWorkOrders()->sole()->planned_qty);
        $this->assertEquals(12, $root->childWorkOrders()->sole()->childWorkOrders()->sole()->planned_qty);
        $report = app(NetRequirementsService::class)->report(now()->startOfDay(), now()->addDays(3));
        $this->assertCount(1, $report['requirements']);
        $this->assertEquals(6, $report['requirements'][0]['required_qty']);
    }

    public function test_assembly_is_blocked_until_component_batches_are_complete(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Admin');
        $sofa = $this->process('SOFA');
        $part = $this->process('PART');
        $this->addComponent($sofa, $part, 4);
        $root = $this->order($sofa);
        $child = $root->childWorkOrders()->sole();
        $service = app(WorkOrderService::class);
        $assembly = $service->createBatch($root, 2)->steps()->sole();
        $this->assertFalse($assembly->canStart());
        try {
            app(BatchService::class)->startStep($assembly, $user);
            $this->fail('Assembly started before parts were complete');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('components', $e->errors());
        }
        $cutting = $service->createBatch($child, 8)->steps()->sole();
        $cutting = app(BatchService::class)->startStep($cutting, $user);
        app(BatchService::class)->completeStep($cutting, $user, ['produced_qty' => 8]);
        $this->assertTrue($assembly->fresh()->canStart());
        $this->assertEquals(0, $root->fresh()->produced_qty);
        $assembly = app(BatchService::class)->startStep($assembly->fresh(), $user);
        app(BatchService::class)->completeStep($assembly, $user, ['produced_qty' => 2]);
        $this->assertEquals(2, $root->fresh()->produced_qty);
    }

    public function test_file_import_is_opt_in_and_repeat_import_is_idempotent(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $rows = [['order_no' => 'IMPORTED', 'product_type_code' => 'SOFA', 'quantity' => 2]];
        $service = app(WorkOrderImportService::class);
        $this->assertEquals(1, $service->importFromFile($rows, ['generate_components' => true])['imported']);
        $result = $service->importFromFile($rows, ['generate_components' => true]);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(2, WorkOrder::count());
        $rows[0]['order_no'] = 'FLAT';
        $this->assertEquals(1, $service->importFromFile($rows)['imported']);
        $this->assertEquals(3, WorkOrder::count());
    }

    public function test_cycle_or_missing_process_rolls_back_the_whole_order(): void
    {
        $sofa = $this->process('SOFA');
        $part = $this->process('PART');
        $this->addComponent($sofa, $part, 4);
        $this->addComponent($part, $sofa, 1);
        $result = app(WorkOrderImportService::class)->importFromFile([
            ['order_no' => 'BAD', 'product_type_code' => 'SOFA', 'quantity' => 2],
        ], ['generate_components' => true]);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Circular', $result['errors'][0]['message']);
        $this->assertEquals(0, WorkOrder::count());
        $this->assertDatabaseCount('work_order_components', 0);
    }

    public function test_product_bom_import_and_invalid_recipe_replacement(): void
    {
        $sofa = $this->process('SOFA');
        $this->process('PART');
        $importer = app(BomImporter::class);
        $valid = ['product_type_code' => 'SOFA', 'component_kind' => 'product_type', 'component_code' => 'PART', 'quantity_per_unit' => 4, 'foam_grade' => 'VB 18/40'];
        $result = $importer->import([$valid], []);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('VB 18/40', $sofa->bomItems()->sole()->extra_data['foam_grade']);
        $valid['quantity_per_unit'] = 99;
        $result = $importer->import([$valid, ['product_type_code' => 'SOFA', 'quantity_per_unit' => 1]], []);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals(4, $sofa->bomItems()->sole()->quantity_per_unit);
    }

    public function test_pending_quantity_change_creates_a_new_version_from_the_frozen_bom(): void
    {
        $sofa = $this->process('SOFA');
        $line = $this->addComponent($sofa, $this->process('PART'), 4);
        $order = $this->order($sofa);
        $oldChild = $order->childWorkOrders()->sole();
        $line->update(['quantity_per_unit' => 99]);
        $order = app(ComponentWorkOrderService::class)->update($order, ['planned_qty' => 3]);
        $this->assertEquals(2, $order->component_plan['version']);
        $this->assertCount(1, $order->component_plan['history']);
        $this->assertEquals(WorkOrder::STATUS_CANCELLED, $oldChild->fresh()->status);
        $newChild = $order->childWorkOrders()->where('status', WorkOrder::STATUS_PENDING)->sole();
        $this->assertEquals(12, $newChild->planned_qty);
        $this->assertCount(1, app(ComponentWorkOrderService::class)->summary($order));
    }

    public function test_quantity_change_after_a_component_batch_exists_preserves_actuals(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $order = $this->order($sofa);
        app(WorkOrderService::class)->createBatch($order->childWorkOrders()->sole(), 8);
        $result = app(WorkOrderImportService::class)->importFromFile([
            ['order_no' => $order->order_no, 'product_type_code' => 'SOFA', 'quantity' => 3],
        ], ['generate_components' => true]);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals(2, $order->fresh()->planned_qty);
        $this->assertEquals(8, $order->childWorkOrders()->sole()->planned_qty);
    }

    public function test_repeated_branches_keep_independent_component_jobs(): void
    {
        $sofa = $this->process('SOFA');
        $left = $this->process('LEFT');
        $right = $this->process('RIGHT');
        $part = $this->process('PART');
        $this->addComponent($sofa, $left, 1);
        $this->addComponent($sofa, $right, 1);
        $this->addComponent($left, $part, 3);
        $this->addComponent($right, $part, 4);
        $root = $this->order($sofa);
        $jobs = WorkOrder::where('root_work_order_id', $root->id)->where('product_type_id', $part->product_type_id)->get();
        $this->assertCount(2, $jobs);
        $this->assertEqualsCanonicalizing([6, 8], $jobs->pluck('planned_qty')->all());
    }

    public function test_manufactured_material_is_dedicated_wip_and_only_its_raw_inputs_are_demanded(): void
    {
        $sofa = $this->process('SOFA');
        $part = $this->process('CUT-FOAM');
        $cut = Material::factory()->create(['is_manufactured' => true, 'producing_process_template_id' => $part->id, 'unit_of_measure' => 'pcs']);
        $foam = Material::factory()->create(['stock_quantity' => 0]);
        BomItem::factory()->create(['process_template_id' => $sofa->id, 'material_id' => $cut->id, 'quantity_per_unit' => 4, 'scrap_percentage' => 0]);
        BomItem::factory()->create(['process_template_id' => $part->id, 'material_id' => $foam->id, 'quantity_per_unit' => 0.5, 'scrap_percentage' => 0]);
        $root = $this->order($sofa);
        $this->assertEmpty($root->process_snapshot['bom']);
        $this->assertEquals(8, $root->childWorkOrders()->sole()->planned_qty);
        $report = app(NetRequirementsService::class)->report(now(), now()->addDays(3));
        $this->assertCount(1, $report['requirements']);
        $this->assertEquals($foam->id, $report['requirements'][0]['material_id']);
        $this->assertEquals(4, $report['requirements'][0]['required_qty']);
    }

    public function test_scrap_and_piece_rounding_compound_through_the_tree(): void
    {
        $sofa = $this->process('SOFA');
        $frame = $this->process('FRAME');
        $part = $this->process('PART');
        $this->addComponent($sofa, $frame, 2)->update(['scrap_percentage' => 10]);
        $this->addComponent($frame, $part, 3);
        $root = $this->order($sofa);
        $this->assertEquals(4, $root->components()->sole()->required_qty);
        $this->assertEquals(5, $root->childWorkOrders()->sole()->planned_qty);
        $this->assertEquals(15, $root->childWorkOrders()->sole()->childWorkOrders()->sole()->planned_qty);
    }

    public function test_a_pinned_component_process_version_is_used(): void
    {
        $sofa = $this->process('SOFA');
        $part = $this->process('PART');
        $newVersion = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $part->product_type_id, 'version' => 2]);
        $this->addComponent($sofa, $part, 4)->update(['component_template_id' => $part->id]);
        $root = $this->order($sofa);
        $this->assertEquals($part->id, $root->childWorkOrders()->sole()->process_snapshot['template_id']);
        $this->assertNotEquals($newVersion->id, $root->childWorkOrders()->sole()->process_snapshot['template_id']);
    }

    public function test_missing_manufactured_process_fails_without_creating_any_orders(): void
    {
        $sofa = $this->process('SOFA');
        $missing = ProductType::factory()->create();
        BomItem::create(['process_template_id' => $sofa->id, 'product_type_id' => $missing->id, 'quantity_per_unit' => 4]);
        try {
            $this->order($sofa);
            $this->fail('Missing process accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no producing process', $e->getMessage());
        }
        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_erp_import_generates_once_and_allows_pending_quantity_amendments(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $line = \App\Models\Line::factory()->create();
        $row = ['order_no' => 'ERP-SOFA', 'product_type_code' => 'SOFA', 'line_code' => $line->code, 'planned_qty' => 2, 'generate_components' => true];
        $service = app(WorkOrderImportService::class);
        $this->assertEmpty($service->importErp([$row], 'update_or_create')['errors']);
        $this->assertEmpty($service->importErp([$row], 'update_or_create')['errors']);
        $this->assertDatabaseCount('work_orders', 2);
        $row['planned_qty'] = 3;
        $this->assertEmpty($service->importErp([$row], 'update_or_create')['errors']);
        $this->assertEquals(12, WorkOrder::whereNotNull('parent_work_order_id')->where('status', WorkOrder::STATUS_PENDING)->sole()->planned_qty);
    }

    public function test_cancel_root_cancels_outstanding_children_and_preserves_the_plan(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $root = $this->order($sofa);
        $root->update(['status' => WorkOrder::STATUS_CANCELLED]);
        $this->assertEquals(WorkOrder::STATUS_CANCELLED, $root->childWorkOrders()->sole()->status);
        $this->assertNotNull($root->fresh()->component_plan);
        $this->assertDatabaseCount('work_order_components', 1);
    }

    public function test_assembly_cannot_create_more_batches_than_the_component_plan_supplies(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $root = $this->order($sofa);
        app(WorkOrderService::class)->createBatch($root, 2);
        $this->expectException(ValidationException::class);
        app(WorkOrderService::class)->createBatch($root, 1);
    }

    public function test_preview_and_admin_create_expose_the_component_hierarchy(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $data = ['order_no' => 'WEB-SOFA', 'product_type_id' => $sofa->product_type_id, 'planned_qty' => 2, 'generate_components' => true];
        $this->actingAs($admin)->postJson('/admin/work-orders/component-preview', $data)->assertOk()->assertJsonPath('data.components.0.planned_qty', 8);
        $this->assertDatabaseCount('work_orders', 0);
        $this->actingAs($admin)->post('/admin/work-orders', $data)->assertRedirect();
        $root = WorkOrder::where('order_no', 'WEB-SOFA')->sole();
        $this->actingAs($admin)->get('/admin/work-orders/'.$root->id)->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/work-orders/Show')->has('workOrder.component_production', 1)->where('workOrder.component_production.0.required_qty', 8));
    }

    public function test_quality_failure_does_not_supply_assembly(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $root = $this->order($sofa);
        $batch = app(WorkOrderService::class)->createBatch($root->childWorkOrders()->sole(), 8);
        $batch->update(['status' => Batch::STATUS_DONE, 'produced_qty' => 8]);
        $this->assertTrue(app(ComponentWorkOrderService::class)->ready($root));
        \App\Models\QualityCheck::factory()->create(['batch_id' => $batch->id, 'all_passed' => false]);
        $this->assertFalse(app(ComponentWorkOrderService::class)->ready($root));
    }

    public function test_scrapped_output_does_not_fulfil_component_demand(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $root = $this->order($sofa);
        $batch = app(WorkOrderService::class)->createBatch($root->childWorkOrders()->sole(), 8);
        $batch->update(['status' => Batch::STATUS_DONE, 'produced_qty' => 8, 'scrap_qty' => 2]);
        $this->assertFalse(app(ComponentWorkOrderService::class)->ready($root));
        $summary = app(ComponentWorkOrderService::class)->summary($root);
        $this->assertEquals(6, $summary[0]['good_qty']);
        $this->assertEquals(2, $summary[0]['scrap_qty']);
        $this->assertEquals(2, $summary[0]['remaining_qty']);
    }

    public function test_cancelled_parent_cannot_start_an_existing_assembly_batch(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $root = $this->order($sofa);
        $batch = app(WorkOrderService::class)->createBatch($root->childWorkOrders()->sole(), 8);
        $batch->update(['status' => Batch::STATUS_DONE, 'produced_qty' => 8]);
        $assembly = app(WorkOrderService::class)->createBatch($root, 2)->steps()->sole();
        $root->update(['status' => WorkOrder::STATUS_CANCELLED]);
        $this->assertFalse($assembly->fresh()->canStart());
    }

    public function test_non_piece_jobs_round_up_to_order_precision(): void
    {
        $sofa = $this->process('SOFA');
        $part = $this->process('PART');
        $part->productType->update(['unit_of_measure' => 'kg']);
        $this->addComponent($sofa, $part, 0.3333);
        $root = $this->order($sofa);
        $this->assertEquals(0.67, $root->childWorkOrders()->sole()->planned_qty);
    }

    public function test_excessive_depth_fails_instead_of_truncating_the_plan(): void
    {
        $root = $this->process('LEVEL-0');
        $parent = $root;
        for ($i = 1; $i <= 21; $i++) {
            $child = $this->process('LEVEL-'.$i);
            $this->addComponent($parent, $child, 1);
            $parent = $child;
        }
        try {
            $this->order($root);
            $this->fail('Deep structure was silently truncated');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('depth', $e->getMessage());
        }
        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_generated_orders_do_not_publish_events_from_a_rolled_back_import(): void
    {
        $sofa = $this->process('SOFA');
        $this->addComponent($sofa, $this->process('PART'), 4);
        $events = [];
        \Illuminate\Support\Facades\Event::listen(\App\Events\WorkOrder\WorkOrderCreated::class, function ($event) use (&$events) {
            $events[] = $event->workOrder->id;
        });
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($sofa) {
                $this->order($sofa);
                throw new \RuntimeException('Rollback fixture');
            });
        } catch (\RuntimeException $e) {
            $this->assertEquals('Rollback fixture', $e->getMessage());
        }
        $this->assertSame([], $events);
        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_mixed_multi_bom_selection_keeps_both_component_occurrences(): void
    {
        $sofa = $this->process('SOFA');
        $alternative = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $sofa->product_type_id, 'version' => 2]);
        $part = $this->process('PART');
        $this->addComponent($sofa, $part, 3);
        $this->addComponent($alternative, $part, 4);
        $root = app(WorkOrderService::class)->createWorkOrder(['order_no' => 'MULTI-COMP', 'product_type_id' => $sofa->product_type_id,
            'bom_template_ids' => [$sofa->id, $alternative->id], 'planned_qty' => 2, 'generate_components' => true]);
        $this->assertEqualsCanonicalizing([6, 8], $root->childWorkOrders()->pluck('planned_qty')->all());
    }

    private function stockedOrder($parent, $part, float $stockQty, float $orderQty = 20): array
    {
        $warehouse = \App\Models\Warehouse::factory()->create(['is_active' => true, 'kind' => 'mixed']);
        $stock = \App\Models\WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_type_id' => $part->product_type_id, 'quantity' => $stockQty, 'unit_of_measure' => 'pcs']);
        $data = ['order_no' => 'STOCK-'.uniqid(), 'product_type_id' => $parent->product_type_id, 'planned_qty' => $orderQty,
            'generate_components' => true, 'use_component_stock' => true, 'component_warehouse_ids' => [$warehouse->id]];

        return [app(WorkOrderService::class)->createWorkOrder($data), $stock, $data];
    }

    public function test_component_stock_is_reserved_and_only_shortage_is_manufactured(): void
    {
        $parent = $this->process('STOCK-SOFA');
        $part = $this->process('STOCK-BEAM');
        $this->addComponent($parent, $part, 6);
        [$order, $stock, $data] = $this->stockedOrder($parent, $part, 50);
        $this->assertEquals(70, $order->childWorkOrders()->sole()->planned_qty);
        $this->assertEquals(50, $order->components()->sole()->reservations()->sum('quantity'));
        $this->assertEquals(50, $stock->fresh()->quantity);
        $data['order_no'] .= '-SECOND';
        $second = app(WorkOrderService::class)->createWorkOrder($data);
        $this->assertEquals(120, $second->childWorkOrders()->sole()->planned_qty);
        $this->assertEquals(0, $second->components()->sole()->stock_qty);
    }

    public function test_full_subassembly_stock_removes_descendant_production_and_issues_once(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $parent = $this->process('FULL-SOFA');
        $frame = $this->process('FULL-FRAME');
        $part = $this->process('FULL-BEAM');
        $this->addComponent($parent, $frame, 1);
        $this->addComponent($frame, $part, 6);
        [$order, $stock] = $this->stockedOrder($parent, $frame, 20);
        $this->assertCount(0, $order->childWorkOrders);
        $this->assertTrue(app(ComponentWorkOrderService::class)->ready($order));
        $order->update(['status' => WorkOrder::STATUS_ACCEPTED]);
        $batch = app(WorkOrderService::class)->createBatch($order, 20);
        $user = User::factory()->create();
        $user->assignRole('Admin');
        app(BatchService::class)->startStep($batch->steps()->first(), $user);
        $this->assertEquals(0, $stock->fresh()->quantity);
        $reservation = $order->components()->sole()->reservations()->sole();
        $this->assertEquals('issued', $reservation->status);
        $this->assertNotNull($reservation->stock_document_id);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\WorkOrder\ComponentStockService::class)->issue($order, 1, $user));
        $this->assertEquals(0, $stock->fresh()->quantity);
    }

    public function test_reservations_are_released_on_cancellation_and_replanned_on_resize(): void
    {
        $parent = $this->process('RESIZE-SOFA');
        $part = $this->process('RESIZE-PART');
        $this->addComponent($parent, $part, 6);
        [$order, $stock] = $this->stockedOrder($parent, $part, 50);
        $order = app(ComponentWorkOrderService::class)->update($order, ['planned_qty' => 10]);
        $this->assertEquals(10, $order->childWorkOrders()->where('status', 'PENDING')->sole()->planned_qty);
        $this->assertEquals(50, \App\Models\ComponentStockReservation::where('status', 'held')->sum('quantity'));
        $order->update(['status' => WorkOrder::STATUS_CANCELLED]);
        $this->assertEquals(0, \App\Models\ComponentStockReservation::where('status', 'held')->sum('quantity'));
        $this->assertEquals(50, $stock->fresh()->quantity);
    }

    public function test_reserved_stock_cannot_be_issued_by_another_document(): void
    {
        $parent = $this->process('GUARD-SOFA');
        $part = $this->process('GUARD-PART');
        $this->addComponent($parent, $part, 1);
        [$order, $stock] = $this->stockedOrder($parent, $part, 20);
        $this->expectException(ValidationException::class);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\Warehouse\WarehouseStockService::class)->adjust(['warehouse_id' => $stock->warehouse_id, 'product_type_id' => $part->product_type_id], -1, 'pcs'));
    }

    public function test_quality_block_after_reservation_blocks_assembly(): void
    {
        $parent = $this->process('QC-SOFA');
        $part = $this->process('QC-PART');
        $this->addComponent($parent, $part, 1);
        [$order, $stock] = $this->stockedOrder($parent, $part, 20);
        $stock->update(['component_status' => 'quarantine']);
        $this->assertFalse(app(ComponentWorkOrderService::class)->ready($order));
    }

    public function test_changed_stock_rejects_stale_preview_without_creating_orders(): void
    {
        $parent = $this->process('PREVIEW-SOFA');
        $part = $this->process('PREVIEW-PART');
        $this->addComponent($parent, $part, 1);
        [$order, $stock, $data] = $this->stockedOrder($parent, $part, 50);
        $data['order_no'] .= '-STALE';
        $snapshot = app(WorkOrderService::class)->buildProcessSnapshot($parent->product_type_id, []);
        $plan = app(\App\Services\WorkOrder\ComponentPlanService::class)->plan($snapshot, 20);
        $preview = app(\App\Services\WorkOrder\ComponentStockService::class)->net($plan, $data);
        $stock->update(['quantity' => 20]);
        try {
            app(WorkOrderService::class)->createWorkOrder($data + ['component_preview_token' => $preview['preview_token']]);
            $this->fail('Stale preview accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('component_preview_token', $e->errors());
        }
        $this->assertDatabaseMissing('work_orders', ['order_no' => $data['order_no']]);
    }

    public function test_engineering_documents_remain_frozen_when_component_quantity_changes(): void
    {
        $parent = $this->process('DOC-SOFA');
        $part = $this->process('DOC-PART');
        $this->addComponent($parent, $part, 1);
        $doc = \App\Models\EngineeringDocument::factory()->released()->create(['entity_type' => 'product_type', 'entity_id' => $part->product_type_id]);
        $order = $this->order($parent);
        $this->assertEquals($doc->id, $order->childWorkOrders()->sole()->process_snapshot['engineering_documents'][0]['document_id']);
        $doc->update(['lifecycle_status' => 'obsolete']);
        $order = app(ComponentWorkOrderService::class)->update($order, ['planned_qty' => 3]);
        $this->assertEquals($doc->id, $order->childWorkOrders()->where('status', 'PENDING')->sole()->process_snapshot['engineering_documents'][0]['document_id']);
    }

    public function test_operator_component_summary_does_not_expose_sibling_lines(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $parent = $this->process('VISIBLE-SOFA');
        $a = $this->process('VISIBLE-A');
        $b = $this->process('VISIBLE-B');
        $this->addComponent($parent, $a, 1);
        $this->addComponent($parent, $b, 1);
        $order = $this->order($parent);
        $children = $order->childWorkOrders()->get();
        $line = \App\Models\Line::factory()->create();
        $children[1]->update(['line_id' => $line->id]);
        $user = User::factory()->create();
        $user->assignRole('Operator');
        $user->lines()->attach($line->id);
        $this->actingAs($user);
        $summary = app(ComponentWorkOrderService::class)->summary($children[1]);
        $this->assertCount(1, $summary);
        $this->assertTrue(array_is_list($summary));
        $this->assertEquals($children[1]->id, $summary[0]['child_work_order_id']);
    }

    public function test_planner_dates_update_reservations_and_report_late_components(): void
    {
        $parent = $this->process('TIME-SOFA');
        $part = $this->process('TIME-PART');
        $this->addComponent($parent, $part, 6);
        [$order] = $this->stockedOrder($parent, $part, 50);
        $needed = now()->addDays(3)->startOfHour();
        $order->update(['planned_start_at' => $needed]);
        $child = $order->childWorkOrders()->sole();
        $child->update(['planned_end_at' => $needed->copy()->addHour()]);
        $this->assertTrue($order->components()->sole()->reservations()->sole()->needed_at->eq($needed));
        $this->assertEquals('late', app(ComponentWorkOrderService::class)->summary($order)[0]['schedule_status']);
    }

    public function test_partial_subassembly_stock_nets_before_descendants_and_preview_does_not_write(): void
    {
        $parent = $this->process('NET-SOFA');
        $frame = $this->process('NET-FRAME');
        $part = $this->process('NET-BEAM');
        $this->addComponent($parent, $frame, 1);
        $this->addComponent($frame, $part, 6);
        [$order, $stock, $data] = $this->stockedOrder($parent, $frame, 5);
        $frameOrder = $order->childWorkOrders()->sole();
        $this->assertEquals(15, $frameOrder->planned_qty);
        $this->assertEquals(90, $frameOrder->childWorkOrders()->sole()->planned_qty);
        $before = [WorkOrder::count(), \App\Models\ComponentStockReservation::count()];
        $preview = app(\App\Services\WorkOrder\ComponentStockService::class)->net($order->component_plan, $data);
        $this->assertEquals(0, $preview['components'][0]['stock_qty']);
        $this->assertSame($before, [WorkOrder::count(), \App\Models\ComponentStockReservation::count()]);
    }

    public function test_stock_plus_completed_shortage_releases_assembly_and_two_batches_issue_only_once(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Admin');
        $parent = $this->process('MIX-SOFA');
        $part = $this->process('MIX-PART');
        $this->addComponent($parent, $part, 6);
        [$order, $stock] = $this->stockedOrder($parent, $part, 50);
        $child = $order->childWorkOrders()->sole();
        $order->update(['status' => 'ACCEPTED']);
        $child->update(['status' => 'ACCEPTED']);
        $this->assertFalse(app(ComponentWorkOrderService::class)->ready($order));
        $partBatch = app(WorkOrderService::class)->createBatch($child, 70);
        $step = app(BatchService::class)->startStep($partBatch->steps()->first(), $user);
        app(BatchService::class)->completeStep($step, $user, ['produced_qty' => 70]);
        $this->assertTrue(app(ComponentWorkOrderService::class)->ready($order));
        foreach ([10, 10] as $quantity) {
            $batch = app(WorkOrderService::class)->createBatch($order->fresh(), $quantity);
            $step = app(BatchService::class)->startStep($batch->steps()->first(), $user);
            app(BatchService::class)->completeStep($step, $user, ['produced_qty' => $quantity]);
        }
        $this->assertEquals(0, $stock->fresh()->quantity);
        $this->assertEquals(20, $order->fresh()->produced_qty);
        $this->assertEquals(1, \App\Models\StockDocument::where('type', 'product_issue')->where('work_order_id', $order->id)->count());
    }

    public function test_incompatible_stock_is_not_allocated_until_specification_and_version_match(): void
    {
        $parent = $this->process('SPEC-SOFA');
        $part = $this->process('SPEC-PART');
        $bom = $this->addComponent($parent, $part, 1);
        $bom->update(['component_template_id' => $part->id, 'extra_data' => ['length_mm' => 200]]);
        [$order, $stock, $data] = $this->stockedOrder($parent, $part, 50);
        $this->assertEquals(0, $order->components()->sole()->stock_qty);
        $stock->update(['component_specification' => ['component_template_id' => $part->id, 'extra_data' => ['length_mm' => 200]]]);
        $data['order_no'] .= '-MATCH';
        $matched = app(WorkOrderService::class)->createWorkOrder($data);
        $this->assertEquals(20, $matched->components()->sole()->stock_qty);
    }

    public function test_unchecking_production_does_not_drop_uncovered_bom_requirements(): void
    {
        $parent = $this->process('CHECK-SOFA');
        $part = $this->process('CHECK-PART');
        $this->addComponent($parent, $part, 1);
        [$order, $stock, $data] = $this->stockedOrder($parent, $part, 20);
        $data['order_no'] .= '-UNCHECK';
        $data['excluded_component_paths'] = [$order->components()->sole()->path];
        try {
            app(WorkOrderService::class)->createWorkOrder($data);
            $this->fail('Uncovered component omitted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('excluded_component_paths', $e->errors());
        }
        $this->assertDatabaseMissing('work_orders', ['order_no' => $data['order_no']]);
        $order->update(['status' => 'CANCELLED']);
        $fromStock = app(WorkOrderService::class)->createWorkOrder($data);
        $this->assertCount(0, $fromStock->childWorkOrders);
        $this->assertEquals(20, $fromStock->components()->sole()->required_qty);
    }

    public function test_issued_component_document_cannot_be_reversed_independently(): void
    {
        $parent = $this->process('REV-SOFA');
        $part = $this->process('REV-PART');
        $this->addComponent($parent, $part, 1);
        [$order, $stock] = $this->stockedOrder($parent, $part, 20);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\WorkOrder\ComponentStockService::class)->issue($order, 1, null));
        $document = \App\Models\StockDocument::findOrFail($order->components()->sole()->reservations()->sole()->stock_document_id);
        $this->expectException(ValidationException::class);
        app(\App\Services\Warehouse\StockDocumentService::class)->cancel($document);
    }

    public function test_lot_expiring_before_planned_start_is_not_eligible_and_lot_availability_caps_stock(): void
    {
        $parent = $this->process('LOT-SOFA');
        $part = $this->process('LOT-PART');
        $material = Material::factory()->create(['is_manufactured' => true, 'producing_process_template_id' => $part->id, 'unit_of_measure' => 'pcs']);
        BomItem::factory()->create(['process_template_id' => $parent->id, 'material_id' => $material->id, 'quantity_per_unit' => 1, 'scrap_percentage' => 0]);
        $warehouse = \App\Models\Warehouse::factory()->create(['is_active' => true, 'kind' => 'mixed']);
        $lot = \App\Models\MaterialLot::factory()->create(['material_id' => $material->id, 'quantity_received' => 50, 'quantity_available' => 5, 'expiry_date' => now()->addDay()->toDateString()]);
        \App\Models\WarehouseStock::create(['warehouse_id' => $warehouse->id, 'material_id' => $material->id, 'material_lot_id' => $lot->id, 'quantity' => 50, 'unit_of_measure' => 'pcs']);
        $plan = app(\App\Services\WorkOrder\ComponentPlanService::class)->plan(app(WorkOrderService::class)->buildProcessSnapshot($parent->product_type_id, []), 20);
        $options = ['use_component_stock' => true, 'component_warehouse_ids' => [$warehouse->id], 'planned_start_at' => now()->addDays(3)];
        $service = app(\App\Services\WorkOrder\ComponentStockService::class);
        $this->assertEquals(0, $service->net($plan, $options)['components'][0]['stock_qty']);
        $options['planned_start_at'] = now();
        $this->assertEquals(5, $service->net($plan, $options)['components'][0]['stock_qty']);
    }

    public function test_file_and_erp_imports_share_stock_netting_and_preserve_start_time(): void
    {
        $parent = $this->process('IMPORT-SOFA');
        $part = $this->process('IMPORT-PART');
        $this->addComponent($parent, $part, 1);
        [$order, $stock] = $this->stockedOrder($parent, $part, 50);
        $line = \App\Models\Line::factory()->create();
        $needed = now()->addDays(2)->startOfHour()->toIso8601String();
        $service = app(WorkOrderImportService::class);
        $file = $service->importFromFile([['order_no' => 'FILE-STOCK', 'product_type_code' => 'IMPORT-SOFA', 'quantity' => 20, 'planned_start_at' => $needed]], ['generate_components' => true, 'use_component_stock' => true, 'component_warehouse_id' => $stock->warehouse_id]);
        $this->assertEquals([], $file['errors']);
        $erp = $service->importErp([['order_no' => 'ERP-STOCK', 'product_type_code' => 'IMPORT-SOFA', 'line_code' => $line->code, 'planned_qty' => 20, 'planned_start_at' => $needed, 'generate_components' => true, 'use_component_stock' => true, 'component_warehouse_ids' => [$stock->warehouse_id]]], 'update_or_create');
        $this->assertEquals([], $erp['errors']);
        $this->assertEquals(20, WorkOrder::where('order_no', 'FILE-STOCK')->sole()->components()->sole()->stock_qty);
        $erpOrder = WorkOrder::where('order_no', 'ERP-STOCK')->sole();
        $this->assertEquals(10, $erpOrder->components()->sole()->stock_qty);
        $this->assertTrue($erpOrder->planned_start_at->eq($needed));
    }

    public function test_material_component_issue_does_not_hide_purchased_input_document(): void
    {
        $parent = $this->process('RAW-SOFA');
        $part = $this->process('RAW-PART');
        $componentMaterial = Material::factory()->create(['is_manufactured' => true, 'producing_process_template_id' => $part->id, 'unit_of_measure' => 'pcs']);
        $raw = Material::factory()->create(['unit_of_measure' => 'pcs']);
        foreach ([$componentMaterial, $raw] as $material) {
            BomItem::factory()->create(['process_template_id' => $parent->id, 'material_id' => $material->id, 'quantity_per_unit' => 1, 'scrap_percentage' => 0]);
        }
        $warehouse = \App\Models\Warehouse::factory()->create(['is_active' => true, 'is_default' => true, 'kind' => 'raw_material']);
        \App\Models\WarehouseStock::create(['warehouse_id' => $warehouse->id, 'material_id' => $componentMaterial->id, 'quantity' => 20, 'unit_of_measure' => 'pcs']);
        $order = app(WorkOrderService::class)->createWorkOrder(['order_no' => 'RAW-STOCK', 'product_type_id' => $parent->product_type_id, 'planned_qty' => 20, 'generate_components' => true, 'use_component_stock' => true, 'component_warehouse_ids' => [$warehouse->id]]);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\WorkOrder\ComponentStockService::class)->issue($order, 1, null));
        $documents = app(\App\Services\Warehouse\WorkOrderStockDocumentService::class)->generateForCompletion($order);
        $issue = collect($documents)->firstWhere('type', 'material_issue');
        $this->assertNotNull($issue);
        $this->assertEquals($raw->id, $issue->lines()->sole()->material_id);
        $this->assertEquals(20, $issue->lines()->sole()->quantity);
    }

    public function test_minute_scheduled_order_is_not_in_backlog_and_resize_warns_about_late_components(): void
    {
        $parent = $this->process('SCHEDULE-SOFA');
        $part = $this->process('SCHEDULE-PART');
        $this->addComponent($parent, $part, 1);
        $order = $this->order($parent);
        $needed = now()->addDays(2)->startOfHour();
        $line = \App\Models\Line::factory()->create();
        $order->update(['line_id' => $line->id, 'due_date' => null, 'planned_start_at' => $needed, 'planned_end_at' => $needed->copy()->addHour()]);
        $planner = app(\App\Services\Schedule\SchedulePlannerService::class);
        $child = $order->childWorkOrders()->sole();
        $result = $planner->resizeOrder($child, ['planned_start_at' => $needed, 'planned_end_at' => $needed->copy()->addHours(2)]);
        $this->assertFalse($result['conflict']);
        $this->assertNotEmpty($result['warnings']);
        $board = $planner->board(['view_mode' => 'hourly', 'start_date' => $needed->toDateString()]);
        $this->assertNotContains($order->id, collect($board['backlogOrders'])->pluck('id')->all());
        $this->assertContains($order->id, collect($board['workOrders'])->pluck('id')->all());
    }

    public function test_unidentified_receipt_invalidates_certified_component_balance(): void
    {
        $parent = $this->process('RECEIPT-SOFA');
        $part = $this->process('RECEIPT-PART');
        $bom = $this->addComponent($parent, $part, 1);
        [$order, $stock, $data] = $this->stockedOrder($parent, $part, 50);
        $order->update(['status' => 'CANCELLED']);
        $bom->update(['extra_data' => ['length_mm' => 200]]);
        $stock->update(['component_specification' => ['extra_data' => ['length_mm' => 200]]]);
        $data['order_no'] .= '-CERTIFIED';
        $order = app(WorkOrderService::class)->createWorkOrder($data);
        $this->assertTrue(app(ComponentWorkOrderService::class)->ready($order));
        \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\Warehouse\WarehouseStockService::class)->adjust(['warehouse_id' => $stock->warehouse_id, 'product_type_id' => $part->product_type_id], 1, 'pcs'));
        $this->assertNull($stock->fresh()->component_specification);
        $this->assertFalse(app(ComponentWorkOrderService::class)->ready($order));
    }

    public function test_imported_planning_offsets_preserve_the_instant_for_reservations(): void
    {
        config(['app.timezone' => 'UTC']);
        $parent = $this->process('OFFSET-SOFA');
        $part = $this->process('OFFSET-PART');
        $this->addComponent($parent, $part, 1);
        [$order, $stock, $data] = $this->stockedOrder($parent, $part, 50);
        $data['order_no'] .= '-OFFSET';
        $data['planned_start_at'] = '2026-10-01T09:15:00+02:00';
        $data['planned_end_at'] = '2026-10-01T10:15:00+02:00';
        $order = app(WorkOrderService::class)->createWorkOrder($data);
        $this->assertEquals('2026-10-01 07:15:00', $order->planned_start_at->format('Y-m-d H:i:s'));
        $this->assertTrue($order->components()->sole()->reservations()->sole()->needed_at->eq('2026-10-01T07:15:00Z'));
        $this->assertTrue($order->planned_end_at->eq('2026-10-01T08:15:00Z'));
    }
}
