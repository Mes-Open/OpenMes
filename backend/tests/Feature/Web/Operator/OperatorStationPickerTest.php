<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The operator toolbar's workstation picker: the line's stations as the floor
 * names them, grouped, naturally ordered, with the active orders waiting at
 * each — on the queue and the workstation view alike.
 */
class OperatorStationPickerTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->line = Line::factory()->create();
    }

    private function station(string $code, string $name, array $extra = []): Workstation
    {
        return Workstation::factory()->create(['line_id' => $this->line->id, 'code' => $code, 'name' => $name, 'is_active' => true] + $extra);
    }

    private function asOperator()
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    public function test_stations_read_by_their_floor_code_grouped_and_naturally_ordered(): void
    {
        $this->station('FA-11', 'FA-11 · Lampa błyskowa');
        $this->station('FA-2', 'FA-2 · Rolki i nadanie PSN');
        $this->station('S1-01', 'FT-1 · Test wstępny');
        $this->station('PACK-5', 'Packing table');
        Workstation::factory()->create(['line_id' => $this->line->id, 'code' => 'OLD-1', 'name' => 'Retired', 'is_active' => false]);

        foreach (['operator.queue' => 'operator/Queue', 'operator.workstation' => 'operator/Workstation'] as $route => $component) {
            $this->asOperator()
                ->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->has('stationPicker', 4)
                    // Grouped by code family, FA before FT before PACK; FA-2 before FA-11.
                    ->where('stationPicker.0.code', 'FA-2')
                    ->where('stationPicker.0.name', 'Rolki i nadanie PSN')
                    ->where('stationPicker.0.group', 'FA')
                    ->where('stationPicker.1.code', 'FA-11')
                    // The name's own code wins over the record's ("S1-01" is FT-1 on the floor).
                    ->where('stationPicker.2.code', 'FT-1')
                    ->where('stationPicker.2.group', 'FT')
                    // No "code · name" pattern: the record's code and the whole name.
                    ->where('stationPicker.3.code', 'PACK-5')
                    ->where('stationPicker.3.name', 'Packing table')
                );
        }
    }

    public function test_equipment_class_or_station_type_names_the_group(): void
    {
        $type = WorkstationType::factory()->create(['name' => 'Final assembly']);
        $this->station('FA-2', 'FA-2 · Rolki', ['workstation_type_id' => $type->id]);
        $this->station('QC-1', 'QC-1 · Visual bench', ['workstation_type' => 'qc']);

        $this->asOperator()
            ->get(route('operator.queue'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stationPicker.0.group', 'Final assembly')
                ->where('stationPicker.1.group', 'qc')
            );
    }

    public function test_each_station_counts_the_active_orders_waiting_there(): void
    {
        $press = $this->station('FA-2', 'FA-2 · Rolki');
        $idle = $this->station('FA-4', 'FA-4 · Silnik');

        foreach ([BatchStep::STATUS_IN_PROGRESS, BatchStep::STATUS_READY] as $stepStatus) {
            $order = WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
            $batch = Batch::factory()->create(['work_order_id' => $order->id, 'status' => Batch::STATUS_IN_PROGRESS]);
            BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'workstation_id' => $press->id, 'status' => $stepStatus]);
        }
        // A finished order is not waiting anywhere.
        $done = WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_DONE]);
        $doneBatch = Batch::factory()->create(['work_order_id' => $done->id, 'status' => Batch::STATUS_DONE]);
        BatchStep::factory()->create(['batch_id' => $doneBatch->id, 'step_number' => 1, 'workstation_id' => $press->id, 'status' => BatchStep::STATUS_DONE]);

        $this->asOperator()
            ->get(route('operator.workstation'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stationPicker.0.id', $press->id)
                ->where('stationPicker.0.count', 2)
                ->where('stationPicker.1.id', $idle->id)
                ->where('stationPicker.1.count', 0)
            );
    }

    public function test_a_cumulative_line_offers_no_station_picker(): void
    {
        $this->station('FA-2', 'FA-2 · Rolki');
        DB::table('system_settings')->updateOrInsert(['key' => 'production_tracking_mode'], ['value' => json_encode('cumulative')]);

        $this->asOperator()
            ->get(route('operator.queue'))
            ->assertInertia(fn (Assert $page) => $page->has('stationPicker', 0));
    }
}
