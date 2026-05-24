<?php

namespace Tests\Unit\Services;

use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\User;
use App\Services\Quality\InboundInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * When lot tracking is enabled, completing an inspection (pass or fail)
 * should create a MaterialLot row so production can pick from / quarantine
 * the inspected stock.
 */
class InspectionCreatesLotTest extends TestCase
{
    use RefreshDatabase;

    private InboundInspectionService $svc;

    private Material $material;

    private User $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);
        $this->svc = app(InboundInspectionService::class);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M', 'name' => 'M',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 0,
        ]);
        $this->inspector = User::factory()->create();

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true)],
        );
    }

    private function makePlan(): InspectionPlan
    {
        return InspectionPlan::create([
            'name' => 'P',
            'material_id' => $this->material->id,
            'criteria' => [['name' => 'V', 'type' => 'pass_fail', 'required' => true]],
            'is_active' => true,
        ]);
    }

    public function test_pass_creates_available_lot_with_full_quantity(): void
    {
        $plan = $this->makePlan();
        $insp = $this->svc->start($this->material, 'LOT-PASS-1', 250, $plan, $this->inspector);
        $this->svc->recordResult($insp->results->first(), ['value_boolean' => true]);

        $this->svc->complete($insp);

        $lot = MaterialLot::firstWhere('inspection_id', $insp->id);
        $this->assertNotNull($lot);
        $this->assertSame('LOT-PASS-1', $lot->lot_number);
        $this->assertEqualsWithDelta(250.0, (float) $lot->quantity_received, 0.0001);
        $this->assertEqualsWithDelta(250.0, (float) $lot->quantity_available, 0.0001);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->status);
    }

    public function test_fail_creates_quarantined_lot_with_zero_available(): void
    {
        $plan = $this->makePlan();
        $insp = $this->svc->start($this->material, 'LOT-FAIL-1', 100, $plan, $this->inspector);
        $this->svc->recordResult($insp->results->first(), ['value_boolean' => false]);

        $this->svc->complete($insp);

        $lot = MaterialLot::firstWhere('inspection_id', $insp->id);
        $this->assertNotNull($lot);
        $this->assertSame(MaterialLot::STATUS_QUARANTINE, $lot->status);
        $this->assertEqualsWithDelta(100.0, (float) $lot->quantity_received, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $lot->quantity_available, 0.0001);
    }

    public function test_skips_lot_creation_when_tracking_disabled(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(false)],
        );

        $plan = $this->makePlan();
        $insp = $this->svc->start($this->material, 'LOT-OFF', 50, $plan, $this->inspector);
        $this->svc->recordResult($insp->results->first(), ['value_boolean' => true]);
        $this->svc->complete($insp);

        $this->assertSame(0, MaterialLot::count());
    }
}
