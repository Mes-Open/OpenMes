<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An operator books output under one of the shifts that run on the work
 * order's line — the ones the station offers. Any other shift id is refused,
 * so a crafted request cannot book output under another line's shift.
 */
class ShiftEntryLineTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    private WorkOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        ProductionFlow::set(ProductionFlow::WHOLE_BATCH);
        $this->line = Line::factory()->create();
        $this->operator = User::factory()->create()->assignRole('Operator');
        $this->order = WorkOrder::factory()->create([
            'line_id' => $this->line->id, 'planned_qty' => 10, 'produced_qty' => 0,
            'status' => WorkOrder::STATUS_IN_PROGRESS, 'planned_start_at' => null, 'counting_source' => 'operator',
        ]);
    }

    private function book(?int $shiftId, $qty = 2)
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id])
            ->post("/operator/workstation/{$this->order->id}/shift-entry", ['shift_id' => $shiftId, 'quantity' => $qty]);
    }

    public function test_output_is_booked_under_a_shift_of_the_line_or_a_plant_wide_one(): void
    {
        $own = Shift::create(['line_id' => $this->line->id, 'name' => 'Own', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);
        $all = Shift::create(['name' => 'All', 'start_time' => '14:00', 'end_time' => '22:00', 'is_active' => true]);

        $this->book($own->id)->assertSessionHas('success');
        $this->book($all->id)->assertSessionHas('success');

        $this->assertEquals(4, (float) $this->order->fresh()->produced_qty);
    }

    public function test_another_lines_shift_is_refused(): void
    {
        $other = Shift::create(['line_id' => Line::factory()->create()->id, 'name' => 'Other', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);

        $this->book($other->id)->assertSessionHasErrors('shift_id');

        $this->assertEquals(0, (float) $this->order->fresh()->produced_qty);
        $this->assertDatabaseCount('work_order_shift_entries', 0);
    }

    public function test_an_inactive_or_missing_shift_or_a_bad_quantity_is_refused(): void
    {
        $off = Shift::create(['line_id' => $this->line->id, 'name' => 'Off', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => false]);
        $own = Shift::create(['line_id' => $this->line->id, 'name' => 'Own', 'start_time' => '14:00', 'end_time' => '22:00', 'is_active' => true]);

        $this->book($off->id)->assertSessionHasErrors('shift_id');
        $this->book(999999)->assertSessionHasErrors('shift_id');
        $this->book(null)->assertSessionHasErrors('shift_id');
        $this->book($own->id, 0)->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('work_order_shift_entries', 0);
    }

    public function test_a_guest_cannot_book_output(): void
    {
        $own = Shift::create(['line_id' => $this->line->id, 'name' => 'Own', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);

        $this->post("/operator/workstation/{$this->order->id}/shift-entry", ['shift_id' => $own->id, 'quantity' => 2])->assertRedirect();
        $this->assertDatabaseCount('work_order_shift_entries', 0);
    }
}
