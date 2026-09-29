<?php

namespace Tests\Feature\Material;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Production hold (setting `hold_on_material_shortage`): a batch does not
 * start production while stock cannot cover its work order.
 */
class MaterialShortageHoldTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Supervisor', 'web');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
    }

    private function hold(bool $on): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'hold_on_material_shortage'], ['value' => json_encode($on)]);
    }

    /** An order for 10 needing 2 of a part per unit, with the given stock of it, and its first step. */
    private function firstStep(float $stock): BatchStep
    {
        $part = Material::factory()->create(['code' => 'PART-H', 'unit_of_measure' => 'pcs', 'material_type_id' => MaterialType::factory()->create()->id,
            'stock_quantity' => $stock, 'reserved_quantity' => 0]);
        $order = WorkOrder::factory()->create(['planned_qty' => 10, 'status' => WorkOrder::STATUS_IN_PROGRESS, 'process_snapshot' => ['bom' => [[
            'material_id' => $part->id, 'material_code' => 'PART-H', 'material_name' => $part->name, 'unit_of_measure' => 'pcs',
            'quantity_per_unit' => 2, 'scrap_percentage' => 0, 'consumed_at' => 'during',
        ]]]]);
        $batch = Batch::factory()->create(['work_order_id' => $order->id, 'status' => Batch::STATUS_PENDING, 'target_qty' => 10]);

        return BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'status' => BatchStep::STATUS_READY]);
    }

    public function test_a_batch_does_not_start_while_stock_cannot_cover_its_order(): void
    {
        $this->hold(true);
        $step = $this->firstStep(stock: 5);

        try {
            app(BatchService::class)->startStep($step, $this->supervisor);
            $this->fail('the start should have been held');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('PART-H', $e->getMessage());
            $this->assertStringContainsString('15', $e->getMessage(), 'what is missing: 20 needed, 5 on hand');
        }
        $this->assertSame(BatchStep::STATUS_READY, $step->fresh()->status);
    }

    public function test_the_hold_is_off_by_default_and_lets_a_covered_order_start(): void
    {
        $short = $this->firstStep(stock: 5);
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, app(BatchService::class)->startStep($short, $this->supervisor)->status);

        $this->hold(true);
        Material::query()->delete();
        $covered = $this->firstStep(stock: 100);
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, app(BatchService::class)->startStep($covered, $this->supervisor)->status);
    }

    public function test_the_settings_form_saves_the_switch(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $form = ['production_period' => 'none', 'workflow_mode' => 'status', 'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1, 'schedule_horizon_weeks' => 6, 'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation', 'production_qty_edit_policy' => 'none', 'scanner_mode' => 'hid'];

        $this->actingAs($admin)->post('/settings/system', $form + ['hold_on_material_shortage' => '1', 'pallet_stock_documents' => 'post'])->assertSessionHasNoErrors();
        $this->assertTrue(BatchService::holdsOnMaterialShortage());
        $this->assertSame('"post"', DB::table('system_settings')->where('key', 'pallet_stock_documents')->value('value'));
    }
}
