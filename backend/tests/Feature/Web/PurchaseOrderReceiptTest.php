<?php

namespace Tests\Feature\Web;

use App\Models\Company;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Purchase order -> receipt -> reconciliation: the receipt must post through the
 * one stock-document path, and received quantity must be derived from the posted
 * document rather than stored on the order.
 */
class PurchaseOrderReceiptTest extends TestCase
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

    public function test_purchase_order_receipt_posts_stock_and_links_reconciliation(): void
    {
        $supplier = Company::create([
            'code' => 'SUP-TEST-01',
            'name' => '测试供应商',
            'type' => Company::TYPE_SUPPLIER,
            'is_active' => true,
        ]);
        $material = Material::factory()->create([
            'code' => 'MAT-PO-TEST',
            'name' => '测试原料',
            'unit_of_measure' => 'kg',
            'stock_quantity' => 0,
        ]);
        $warehouse = Warehouse::factory()->rawMaterial()->create();

        $this->actingAs($this->admin)->post('/admin/purchasing/orders', [
            'supplier_id' => $supplier->id,
            'ordered_at' => today()->toDateString(),
            'expected_at' => today()->addDays(3)->toDateString(),
            'lines' => [[
                'material_id' => $material->id,
                'quantity_ordered' => 10,
                'unit_price' => 12.5,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order = PurchaseOrder::query()->firstOrFail();
        $line = $order->lines()->firstOrFail();
        $this->assertSame(PurchaseOrder::STATUS_OPEN, $order->status);

        $this->actingAs($this->admin)->post('/admin/purchasing/receipts/order', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'reference' => 'DN-TEST-001',
            'lines' => [[
                'purchase_order_line_id' => $line->id,
                'quantity' => 4,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $document = StockDocument::query()->where('purchase_order_id', $order->id)->firstOrFail();
        $this->assertSame(StockDocument::STATUS_POSTED, $document->status);
        $this->assertSame($supplier->name, $document->supplier_name);
        $this->assertDatabaseHas('stock_document_lines', [
            'stock_document_id' => $document->id,
            'purchase_order_line_id' => $line->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $material->id,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'source_type' => StockMovement::SOURCE_STOCK_DOCUMENT,
            'source_id' => $document->id,
        ]);
        $this->assertSame(4.0, (float) $material->fresh()->stock_quantity);

        $this->actingAs($this->admin)->get('/admin/purchasing/reconciliation')->assertOk();
        $this->actingAs($this->admin)->get('/admin/purchasing/reconciliation/export')->assertOk();
        $this->actingAs($this->admin)->get('/admin/purchasing/orders')->assertOk();
        $this->actingAs($this->admin)->get('/admin/purchasing/receipts?purchase_order='.$order->id)->assertOk();

        $this->actingAs($this->admin)->post("/admin/purchasing/orders/{$order->id}/close")->assertRedirect();
        $this->assertSame(PurchaseOrder::STATUS_CLOSED, $order->fresh()->status);
    }

    public function test_receipt_may_not_exceed_the_remaining_order_quantity(): void
    {
        $supplier = Company::create([
            'code' => 'SUP-TEST-02',
            'name' => '第二个供应商',
            'type' => Company::TYPE_SUPPLIER,
            'is_active' => true,
        ]);
        $material = Material::factory()->create([
            'code' => 'MAT-PO-TEST-2',
            'unit_of_measure' => 'kg',
            'stock_quantity' => 0,
        ]);
        $warehouse = Warehouse::factory()->rawMaterial()->create();

        $order = PurchaseOrder::create([
            'number' => 'PO/2026/0099',
            'supplier_id' => $supplier->id,
            'ordered_at' => today()->toDateString(),
            'expected_at' => today()->toDateString(),
        ]);
        $line = $order->lines()->create([
            'line_no' => 1,
            'material_id' => $material->id,
            'quantity_ordered' => 5,
            'unit_price' => 0,
            'unit_of_measure' => 'kg',
        ]);

        $this->actingAs($this->admin)->post('/admin/purchasing/receipts/order', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'reference' => 'DN-TEST-002',
            'lines' => [[
                'purchase_order_line_id' => $line->id,
                'quantity' => 6,
            ]],
        ])->assertSessionHasErrors('lines');

        $this->assertDatabaseMissing('stock_documents', [
            'purchase_order_id' => $order->id,
            'status' => StockDocument::STATUS_POSTED,
        ]);
    }
}
