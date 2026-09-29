<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Traceability\SerialTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The operator's order view lists the order's serial units and where each was last seen. */
class WorkOrderSerialUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_order_view_lists_its_units_with_their_last_event(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $line = Line::factory()->create(['is_active' => true]);
        $operator->lines()->attach($line->id);
        $first = Workstation::create(['line_id' => $line->id, 'code' => 'ST-1', 'name' => 'Start', 'is_active' => true]);
        $label = Workstation::create(['line_id' => $line->id, 'code' => 'ST-9', 'name' => 'Label', 'is_active' => true]);
        $order = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $serials = app(SerialTraceService::class);

        $serials->startUnit('P-1', $operator, ['work_order_id' => $order->id, 'workstation_id' => $first->id]);
        $serials->startUnit('P-2', $operator, ['work_order_id' => $order->id, 'workstation_id' => $first->id]);
        $serials->bindProcessSerial('SN-1', 'P-1', $operator, ['work_order_id' => $order->id, 'workstation_id' => $label->id]);

        $this->actingAs($operator)->get("/operator/work-order/{$order->id}?line={$line->id}&workstation=all")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('serialUnits.total', 2)
                // Newest first: P-2 was only started, P-1 has its label now.
                ->where('serialUnits.units.0.psn', 'P-2')
                ->where('serialUnits.units.0.last.parameters.event', 'started')
                ->where('serialUnits.units.1.serial_no', 'SN-1')
                ->where('serialUnits.units.1.last.parameters.event', 'label_applied')
                ->where('serialUnits.units.1.last.workstation', 'Label'));

        // An order without serial units carries an empty list.
        $plain = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->actingAs($operator)->get("/operator/work-order/{$plain->id}?line={$line->id}&workstation=all")
            ->assertInertia(fn (Assert $page) => $page->where('serialUnits.total', 0));
    }
}
