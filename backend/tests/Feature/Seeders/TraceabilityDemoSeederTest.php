<?php

namespace Tests\Feature\Seeders;

use App\Models\MaterialLot;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Traceability\TraceabilityService;
use Database\Seeders\TraceabilityDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraceabilityDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_genealogy_that_traces_end_to_end(): void
    {
        User::factory()->create();

        $this->seed(TraceabilityDemoSeeder::class);

        $tracer = app(TraceabilityService::class);
        $steel = MaterialLot::where('lot_number', 'TR-RAW-STEEL-01')->firstOrFail();

        // Steel -> frames -> bikes: the recall walk crosses the semi-finished lot.
        $affected = collect($tracer->recallImpact(collect([$steel]))['work_orders'])->pluck('order_no');
        $this->assertContains('TR-FRAME-001', $affected);
        $this->assertContains('TR-BIKE-001', $affected);
        $this->assertContains('TR-BIKE-002', $affected);

        $this->assertSame('pallet', $tracer->resolve(WorkOrder::where('order_no', 'TR-BIKE-001')->firstOrFail()->pallets()->firstOrFail()->pallet_no)['type']);
        $this->assertSame('batch', $tracer->resolve('TR-FG-L001')['type']);
        $this->assertSame(6, SerialUnit::where('serial_no', 'like', 'TR-SN-%')->count());
    }

    public function test_running_it_twice_adds_nothing(): void
    {
        User::factory()->create();

        $this->seed(TraceabilityDemoSeeder::class);
        $this->seed(TraceabilityDemoSeeder::class);

        $this->assertSame(4, WorkOrder::where('order_no', 'like', 'TR-%')->count());
        $this->assertSame(1, MaterialLot::where('lot_number', 'TR-RAW-STEEL-01')->count());
    }

    public function test_it_is_not_part_of_the_default_seed(): void
    {
        $this->assertStringNotContainsString(
            'TraceabilityDemoSeeder',
            file_get_contents(database_path('seeders/DatabaseSeeder.php')),
        );
    }
}
