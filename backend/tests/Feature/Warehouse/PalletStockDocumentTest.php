<?php

namespace Tests\Feature\Warehouse;

use App\Enums\PalletStatus;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\Pallet;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Models\StockDocument;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Packaging\UnitPackingService;
use App\Services\Warehouse\WarehouseStockService;
use App\Services\Warehouse\WorkOrderStockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Finished goods booked pallet by pallet: received into the finished-goods
 * warehouse when a pallet closes, issued from it when the pallet ships.
 */
class PalletStockDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $fg;

    private WorkOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fg = Warehouse::factory()->finishedGoods()->isDefault()->create(['code' => 'FG-1']);
        $product = ProductType::factory()->create(['code' => 'PRD-FG', 'unit_of_measure' => 'pcs']);
        $this->order = WorkOrder::factory()->create(['line_id' => Line::factory(), 'product_type_id' => $product->id, 'planned_qty' => 3, 'produced_qty' => 3, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
    }

    private function mode(string $mode): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'pallet_stock_documents'], ['value' => json_encode($mode)]);
    }

    /** A pallet with three tested units and one scrapped. */
    private function pallet(): Pallet
    {
        $pallet = Pallet::create(['work_order_id' => $this->order->id, 'qty' => 4, 'status' => PalletStatus::Open->value, 'quality_status' => Pallet::QUALITY_PASS]);
        foreach (['A', 'B', 'C'] as $sn) {
            SerialUnit::create(['serial_no' => "SN-$sn", 'work_order_id' => $this->order->id, 'pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        }
        SerialUnit::create(['serial_no' => 'SN-X', 'work_order_id' => $this->order->id, 'pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_SCRAPPED]);

        return $pallet;
    }

    private function stock(): float
    {
        return app(WarehouseStockService::class)->available(['warehouse_id' => $this->fg->id, 'product_type_id' => $this->order->product_type_id]);
    }

    public function test_posted_mode_moves_stock_with_the_pallet_and_books_each_document_once(): void
    {
        $this->mode('post');
        $pallet = $this->pallet();

        $pallet->update(['status' => PalletStatus::Closed->value]);
        $receipt = StockDocument::where('pallet_id', $pallet->id)->where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->with('lines')->sole();
        $this->assertSame(StockDocument::STATUS_POSTED, $receipt->status);
        $this->assertEquals(3, $receipt->lines->first()->quantity, 'units on the pallet, scrap excepted');
        $this->assertEquals(3, $this->stock());

        // Completing the order - it packs onto pallets - does not receive the same goods a second time.
        $batch = \App\Models\Batch::factory()->create(['work_order_id' => $this->order->id]);
        \App\Models\BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'kind' => \App\Models\TemplateStep::KIND_PACKING]);
        app(WorkOrderStockDocumentService::class)->generateForCompletion($this->order);
        $this->assertSame(1, StockDocument::where('work_order_id', $this->order->id)->where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->count());

        $pallet->update(['status' => PalletStatus::Shipped->value]);
        $issue = StockDocument::where('pallet_id', $pallet->id)->where('type', StockDocument::TYPE_PRODUCT_ISSUE)->sole();
        $this->assertSame(StockDocument::STATUS_POSTED, $issue->status);
        $this->assertEquals(0, $this->stock());

        // Touching the shipped pallet again books nothing more.
        $pallet->update(['location' => 'Dock 2']);
        $this->assertSame(2, StockDocument::where('pallet_id', $pallet->id)->count());
    }

    public function test_an_order_that_never_palletises_keeps_its_completion_receipt(): void
    {
        $this->mode('post');

        app(WorkOrderStockDocumentService::class)->generateForCompletion($this->order);

        $this->assertSame(1, StockDocument::where('work_order_id', $this->order->id)->where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->whereNull('pallet_id')->count());
    }

    public function test_a_pallet_shipped_straight_from_open_is_received_before_it_leaves(): void
    {
        $this->mode('post');
        $pallet = $this->pallet();

        $pallet->update(['status' => PalletStatus::Shipped->value]);

        $this->assertSame([StockDocument::TYPE_PRODUCT_RECEIPT, StockDocument::TYPE_PRODUCT_ISSUE], StockDocument::where('pallet_id', $pallet->id)->orderBy('id')->pluck('type')->all());
        $this->assertEquals(0, $this->stock());
    }

    public function test_a_pallet_recorded_straight_as_closed_is_received_and_held_units_neither_ship_nor_count(): void
    {
        $this->mode('post');
        $closed = Pallet::create(['work_order_id' => $this->order->id, 'qty' => 5, 'status' => PalletStatus::Closed->value]);
        $this->assertEquals(5, StockDocument::where('pallet_id', $closed->id)->where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->sole()->lines()->sum('quantity'));

        $pallet = $this->pallet();
        $held = SerialUnit::create(['serial_no' => 'SN-H', 'work_order_id' => $this->order->id, 'pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_BLOCKED]);
        $pallet->update(['status' => PalletStatus::Closed->value]);
        $this->assertEquals(3, StockDocument::where('pallet_id', $pallet->id)->sole()->lines()->sum('quantity'), 'the held unit is not received');

        app(\App\Services\Packaging\UnitPackingService::class)->markPalletShipped($pallet);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $held->fresh()->status);
    }

    /**
     * The packing station closes a pallet with a guarded query update (so a double
     * click cannot close it twice), which fires no model events - the receipt has
     * to be booked all the same.
     */
    public function test_closing_a_pallet_at_the_packing_station_books_its_receipt(): void
    {
        $this->mode('post');
        $pallet = $this->pallet();
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->postJson(route('packaging.pallets.close', $pallet))->assertOk();

        $receipt = StockDocument::where('pallet_id', $pallet->id)->where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->sole();
        $this->assertSame(StockDocument::STATUS_POSTED, $receipt->status);
        $this->assertEquals(3, $this->stock());
    }

    public function test_a_pallet_closed_when_full_books_its_receipt(): void
    {
        $this->mode('draft');
        $pallet = $this->pallet();
        BatchStep::factory()->create([
            'batch_id' => Batch::factory()->create(['work_order_id' => $this->order->id])->id,
            'kind' => TemplateStep::KIND_PACKING,
            'config' => ['unit' => 'pallet', 'pallet_capacity' => 4],
        ]);

        $this->assertTrue(app(UnitPackingService::class)->closePalletIfFull($pallet));

        $this->assertSame(StockDocument::STATUS_DRAFT, StockDocument::where('pallet_id', $pallet->id)->sole()->status);
    }

    public function test_draft_mode_leaves_documents_for_the_warehouse_and_off_books_nothing(): void
    {
        $this->mode('draft');
        $drafted = $this->pallet();
        $drafted->update(['status' => PalletStatus::Closed->value]);
        $this->assertSame(StockDocument::STATUS_DRAFT, StockDocument::where('pallet_id', $drafted->id)->sole()->status);
        $this->assertEquals(0, $this->stock());

        $this->mode('off');
        $other = Pallet::create(['work_order_id' => $this->order->id, 'qty' => 2, 'status' => PalletStatus::Open->value]);
        $other->update(['status' => PalletStatus::Closed->value]);
        $this->assertSame(0, StockDocument::where('pallet_id', $other->id)->count());
    }
}
