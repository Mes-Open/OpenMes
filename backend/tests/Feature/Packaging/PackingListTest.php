<?php

namespace Tests\Feature\Packaging;

use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\UnitCarton;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\PalletPackingList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The packing list that goes with a pallet: cartons, the serial numbers in each, and what was packed loose. */
class PackingListTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_the_list_groups_units_by_carton_and_counts_what_ships(): void
    {
        $wo = WorkOrder::factory()->create(['customer_order_no' => 'PO-77', 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $pallet = Pallet::create(['work_order_id' => $wo->id, 'status' => 'closed', 'qty' => 4, 'destination' => 'Hamburg']);
        $box1 = UnitCarton::create(['work_order_id' => $wo->id, 'pallet_id' => $pallet->id, 'status' => UnitCarton::STATUS_CLOSED, 'qty' => 2, 'created_by_id' => $this->operator->id]);
        $box2 = UnitCarton::create(['work_order_id' => $wo->id, 'pallet_id' => $pallet->id, 'status' => UnitCarton::STATUS_OPEN, 'qty' => 1, 'created_by_id' => $this->operator->id]);
        SerialUnit::create(['serial_no' => 'SN-B', 'psn' => 'P-2', 'work_order_id' => $wo->id, 'pallet_id' => $pallet->id, 'carton_id' => $box1->id, 'status' => 'completed']);
        SerialUnit::create(['serial_no' => 'SN-A', 'psn' => 'P-1', 'work_order_id' => $wo->id, 'pallet_id' => $pallet->id, 'carton_id' => $box1->id, 'status' => 'completed']);
        SerialUnit::create(['serial_no' => 'SN-C', 'psn' => 'P-3', 'work_order_id' => $wo->id, 'pallet_id' => $pallet->id, 'carton_id' => $box2->id, 'status' => 'scrapped']);
        SerialUnit::create(['serial_no' => 'SN-D', 'psn' => 'P-4', 'work_order_id' => $wo->id, 'pallet_id' => $pallet->id, 'status' => 'completed']);

        $list = PalletPackingList::data($pallet->fresh());

        $this->assertSame($pallet->pallet_no, $list['pallet']['pallet_no']);
        $this->assertSame('PO-77', $list['pallet']['customer_order_no']);
        $this->assertSame('Hamburg', $list['pallet']['destination']);
        $this->assertCount(2, $list['cartons']);
        $this->assertSame($box1->carton_no, $list['cartons'][0]['carton_no']);
        $this->assertSame(['SN-A', 'SN-B'], array_column($list['cartons'][0]['units'], 'serial_no'), 'units within a carton are listed in serial order');
        $this->assertSame('scrapped', $list['cartons'][1]['units'][0]['status']);
        $this->assertSame(['SN-D'], array_column($list['loose_units'], 'serial_no'));
        $this->assertSame(['cartons' => 2, 'units' => 3, 'scrapped' => 1], $list['totals']);
    }

    public function test_the_document_is_a_pdf_for_signed_in_operators(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $pallet = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);

        $this->get(route('packaging.labels.pallet.packing-list', $pallet))->assertRedirect('/login');
        $this->actingAs($this->operator)->get(route('packaging.labels.pallet.packing-list', $pallet))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
