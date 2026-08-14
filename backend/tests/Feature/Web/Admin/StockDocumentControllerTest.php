<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_and_open_the_create_form(): void
    {
        Warehouse::factory()->rawMaterial()->create();

        $this->actingAs($this->admin)->get(route('admin.stock-documents.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.stock-documents.create'))->assertOk();
    }

    public function test_admin_can_create_a_draft_document_with_lines(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $material = Material::factory()->create(['unit_of_measure' => 'kg']);

        $response = $this->actingAs($this->admin)->post(route('admin.stock-documents.store'), [
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'notes' => 'Manual release',
            'lines' => [
                ['material_id' => $material->id, 'quantity' => 12.5, 'unit_of_measure' => 'kg'],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $document = StockDocument::first();

        $response->assertRedirect(route('admin.stock-documents.show', $document));
        $this->assertSame(StockDocument::STATUS_DRAFT, $document->status);
        $this->assertDatabaseHas('stock_document_lines', [
            'stock_document_id' => $document->id,
            'material_id' => $material->id,
            'quantity' => 12.5,
        ]);
        // Creating never moves stock.
        $this->assertSame(0, WarehouseStock::count());
    }

    public function test_a_material_document_rejects_a_product_line(): void
    {
        Warehouse::factory()->rawMaterial()->create();
        $product = ProductType::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.store'), [
                'type' => StockDocument::TYPE_MATERIAL_ISSUE,
                'lines' => [['product_type_id' => $product->id, 'quantity' => 5]],
            ])
            ->assertSessionHasErrors('lines.0.material_id');
    }

    public function test_a_document_needs_at_least_one_line_with_a_positive_quantity(): void
    {
        Warehouse::factory()->rawMaterial()->create();
        $material = Material::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.store'), [
                'type' => StockDocument::TYPE_MATERIAL_ISSUE,
                'lines' => [],
            ])
            ->assertSessionHasErrors('lines');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.store'), [
                'type' => StockDocument::TYPE_MATERIAL_ISSUE,
                'lines' => [['material_id' => $material->id, 'quantity' => 0]],
            ])
            ->assertSessionHasErrors('lines.0.quantity');
    }

    public function test_admin_can_post_and_then_cancel_a_document(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $material = Material::factory()->create(['stock_quantity' => 100]);

        $document = StockDocument::factory()->create(['warehouse_id' => $warehouse->id]);
        $document->lines()->create(['material_id' => $material->id, 'quantity' => 40]);

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.post', $document))
            ->assertSessionHas('success');

        $this->assertSame(StockDocument::STATUS_POSTED, $document->fresh()->status);
        $this->assertEquals(60, (float) $material->fresh()->stock_quantity);
        $this->assertSame($this->admin->id, $document->fresh()->posted_by_id);

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.cancel', $document))
            ->assertSessionHas('success');

        $this->assertSame(StockDocument::STATUS_CANCELLED, $document->fresh()->status);
        $this->assertEquals(100, (float) $material->fresh()->stock_quantity);
    }

    public function test_posting_a_document_without_lines_reports_an_error(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $document = StockDocument::factory()->create(['warehouse_id' => $warehouse->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.post', $document))
            ->assertSessionHas('error');

        $this->assertSame(StockDocument::STATUS_DRAFT, $document->fresh()->status);
    }

    public function test_a_posted_document_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $document = StockDocument::factory()->posted()->create(['warehouse_id' => $warehouse->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.stock-documents.destroy', $document))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('stock_documents', ['id' => $document->id]);
    }

    public function test_a_draft_document_can_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $document = StockDocument::factory()->create(['warehouse_id' => $warehouse->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.stock-documents.destroy', $document))
            ->assertRedirect(route('admin.stock-documents.index'));

        $this->assertSoftDeleted('stock_documents', ['id' => $document->id]);
    }

    public function test_the_detail_page_renders_its_lines(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);
        $material = Material::factory()->create(['code' => 'FLOUR-01']);

        $document = StockDocument::factory()->create(['warehouse_id' => $warehouse->id]);
        $document->lines()->create(['material_id' => $material->id, 'quantity' => 7, 'unit_of_measure' => 'kg']);

        $this->actingAs($this->admin)
            ->get(route('admin.stock-documents.show', $document))
            ->assertOk()
            // Lines come as Inertia props, not through a shape.
            ->assertInertia(fn ($page) => $page
                ->component('admin/stock-documents/Show')
                ->where('document.lines.0.item_code', 'FLOUR-01')
                ->where('document.warehouse.code', 'RAW-1'));
    }

    public function test_guests_and_non_admins_are_kept_out(): void
    {
        $this->get(route('admin.stock-documents.index'))->assertRedirect(route('login'));

        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->get(route('admin.stock-documents.index'))
            ->assertForbidden();
    }

    public function test_a_line_cannot_carry_a_lot_from_another_material(): void
    {
        Warehouse::factory()->rawMaterial()->create();
        $material = Material::factory()->create();
        $foreignLot = MaterialLot::factory()->create(['material_id' => Material::factory()]);

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.store'), [
                'type' => StockDocument::TYPE_MATERIAL_ISSUE,
                'lines' => [[
                    'material_id' => $material->id,
                    'material_lot_id' => $foreignLot->id,
                    'quantity' => 5,
                ]],
            ])
            ->assertSessionHasErrors('lines.0.material_lot_id');

        $this->assertSame(0, StockDocument::count());
    }

    public function test_a_line_cannot_name_both_a_material_and_a_product(): void
    {
        Warehouse::factory()->rawMaterial()->create();
        $material = Material::factory()->create();
        $product = ProductType::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.store'), [
                'type' => StockDocument::TYPE_MATERIAL_ISSUE,
                'lines' => [[
                    'material_id' => $material->id,
                    'product_type_id' => $product->id,
                    'quantity' => 5,
                ]],
            ])
            ->assertSessionHasErrors('lines.0.product_type_id');
    }

    public function test_a_product_line_cannot_carry_a_material_lot(): void
    {
        Warehouse::factory()->finishedGoods()->create();
        $product = ProductType::factory()->create();
        $lot = MaterialLot::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.stock-documents.store'), [
                'type' => StockDocument::TYPE_PRODUCT_RECEIPT,
                'lines' => [[
                    'product_type_id' => $product->id,
                    'material_lot_id' => $lot->id,
                    'quantity' => 5,
                ]],
            ])
            ->assertSessionHasErrors('lines.0.material_lot_id');
    }
}
