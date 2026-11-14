<?php

namespace Tests\Feature\Api\V1;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\SerialUnitComponent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Traceability\SerialTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialLotWhereUsedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->user = User::factory()->create();
        $this->user->assignRole('Admin');
    }

    private function bind(SerialUnit $parent, array $attributes): SerialUnitComponent
    {
        return SerialUnitComponent::create(array_merge([
            'serial_unit_id' => $parent->id,
            'quantity' => 1,
            'bound_at' => now(),
        ], $attributes));
    }

    private function bindLot(SerialUnit $unit, MaterialLot $lot, array $extra = []): SerialUnitComponent
    {
        return $this->bind($unit, ['identifier' => $lot->lot_number, 'material_lot_id' => $lot->id] + $extra);
    }

    private function bindUnit(SerialUnit $parent, SerialUnit $child, array $extra = []): SerialUnitComponent
    {
        return $this->bind($parent, ['identifier' => $child->serial_no, 'component_serial_unit_id' => $child->id] + $extra);
    }

    private function whereUsed(MaterialLot $lot, array $query = [], ?User $as = null)
    {
        $url = "/api/v1/material-lots/{$lot->id}/where-used".($query ? '?'.http_build_query($query) : '');

        return $this->actingAs($as ?? $this->user, 'sanctum')->getJson($url);
    }

    public function test_returns_direct_units_and_the_kits_that_contain_them(): void
    {
        $lot = MaterialLot::factory()->create(['lot_number' => 'PIN-2026-001']);

        $pinionWo = WorkOrder::factory()->create(['order_no' => 'WO-PINION']);
        $pinionBatch = Batch::factory()->create(['work_order_id' => $pinionWo->id]);
        $kitWo = WorkOrder::factory()->create(['order_no' => 'WO-KIT']);
        $pallet = Pallet::factory()->create(['work_order_id' => $kitWo->id, 'pallet_no' => 'PAL-000777']);

        $pinionA = SerialUnit::factory()->create(['serial_no' => 'PIN-A', 'work_order_id' => $pinionWo->id, 'batch_id' => $pinionBatch->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        $pinionB = SerialUnit::factory()->create(['serial_no' => 'PIN-B', 'work_order_id' => $pinionWo->id, 'batch_id' => $pinionBatch->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        $kit = SerialUnit::factory()->create(['serial_no' => 'KIT-1', 'work_order_id' => $kitWo->id, 'pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_SHIPPED]);
        $carton = SerialUnit::factory()->create(['serial_no' => 'BOX-1', 'work_order_id' => $kitWo->id, 'status' => SerialUnit::STATUS_SHIPPED]);
        // Unrelated: a pinion of another lot, and a unit bound to the lot then taken out.
        $other = SerialUnit::factory()->create(['serial_no' => 'PIN-X', 'work_order_id' => $pinionWo->id]);
        $removed = SerialUnit::factory()->create(['serial_no' => 'PIN-R', 'work_order_id' => $pinionWo->id]);

        $this->bindLot($pinionA, $lot);
        $this->bindLot($pinionB, $lot);
        $this->bindLot($removed, $lot, ['unbound_at' => now(), 'unbind_reason' => 'wrong part']);
        $this->bind($other, ['identifier' => 'PIN-OTHER-LOT', 'material_lot_id' => MaterialLot::factory()->create()->id]);
        $this->bindUnit($kit, $pinionA);
        $this->bindUnit($carton, $kit);
        // The batch the pinions came from also recorded consuming the lot.
        $step = BatchStep::factory()->create(['batch_id' => $pinionBatch->id]);
        BatchStepLotConsumption::create(['batch_step_id' => $step->id, 'material_lot_id' => $lot->id, 'quantity_consumed' => 2, 'consumed_at' => now()]);

        $response = $this->whereUsed($lot)->assertOk();
        $data = $response->json('data');

        $this->assertSame('unit', $data['precision']);
        $this->assertTrue($data['has_unit_bindings']);
        $this->assertFalse($data['truncated']);
        $this->assertSame('PIN-2026-001', $data['lot']['lot_number']);

        $this->assertSame(['PIN-A', 'PIN-B'], array_column($data['direct_units'], 'serial_no'));
        $this->assertSame(['KIT-1', 'BOX-1'], array_column($data['parent_units'], 'serial_no'));
        $this->assertSame([1, 2], array_column($data['parent_units'], 'depth'));

        $pinA = $data['direct_units'][0];
        $this->assertSame('WO-PINION', $pinA['work_order']['order_no']);
        $this->assertSame($pinionBatch->id, $pinA['batch']['id']);
        $this->assertFalse($pinA['top_level']);
        $this->assertSame('KIT-1', $pinA['contained_in'][0]['serial_no']);
        $this->assertSame('material_lot', $pinA['bindings'][0]['match']);
        $this->assertTrue($pinA['bindings'][0]['installed']);
        $this->assertTrue($data['direct_units'][1]['top_level']); // PIN-B is loose

        $kitRow = $data['parent_units'][0];
        $this->assertSame('PAL-000777', $kitRow['pallet']['pallet_no']);
        $this->assertSame('shipped', $kitRow['status']);
        $this->assertSame(['PIN-A'], array_column($kitRow['contains'], 'serial_no'));
        $this->assertArrayNotHasKey('bindings', $kitRow);
        $this->assertTrue($data['parent_units'][1]['top_level']);

        $this->assertSame(2, $data['totals']['direct_units']);
        $this->assertSame(2, $data['totals']['parent_units']);
        $this->assertSame(2, $data['totals']['top_level_units']); // PIN-B + BOX-1
        $this->assertSame(1, $data['totals']['removed_bindings']);
        $this->assertSame(2, $data['totals']['work_orders']);
        $this->assertSame(['completed' => 2, 'shipped' => 2], $data['totals']['by_status']);

        $summary = collect($data['work_orders'])->keyBy('work_order.order_no');
        $this->assertSame(2, $summary['WO-PINION']['direct_units']);
        $this->assertSame(0, $summary['WO-PINION']['parent_units']);
        $this->assertSame(0, $summary['WO-KIT']['direct_units']);
        $this->assertSame(2, $summary['WO-KIT']['parent_units']);
        $this->assertSame(['shipped' => 2], $summary['WO-KIT']['by_status']);

        // Batch level is still reported as a cross-check: 2 of 2 units of the batch are bound.
        $batchRow = $data['batch_level']['work_orders'][0]['batches'][0];
        $this->assertSame($pinionBatch->id, $batchRow['id']);
        $this->assertSame(2, $batchRow['candidate_units']);
        $this->assertSame(2, $batchRow['bound_units']);
    }

    public function test_query_count_does_not_grow_with_the_number_of_units(): void
    {
        $lot = MaterialLot::factory()->create();
        $wo = WorkOrder::factory()->create();
        $make = function (int $n) use ($lot, $wo) {
            foreach (range(1, $n) as $i) {
                $pinion = SerialUnit::factory()->create(['work_order_id' => $wo->id]);
                $kit = SerialUnit::factory()->create(['work_order_id' => $wo->id]);
                $this->bindLot($pinion, $lot);
                $this->bindUnit($kit, $pinion);
            }
        };
        $count = function () use ($lot) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->whereUsed($lot)->assertOk();
            // Only the traceability tables: auth / settings lookups vary with caches.
            $queries = collect(DB::getQueryLog())->pluck('query')
                ->filter(fn (string $sql) => preg_match('/from "(serial_unit_components|serial_units|work_orders|product_types|materials|batches|batch_steps|pallets|batch_step_lot_consumption)"/', $sql))
                ->count();
            DB::disableQueryLog();

            return $queries;
        };

        $make(2);
        $few = $count();
        $make(20);
        $many = $count();

        $this->assertSame(22, $this->whereUsed($lot)->json('data.totals.direct_units'));
        $this->assertGreaterThan(5, $few); // the filter does see the service's queries
        $this->assertSame($few, $many);
    }

    public function test_include_removed_returns_units_the_lot_was_taken_out_of(): void
    {
        $lot = MaterialLot::factory()->create();
        $unit = SerialUnit::factory()->create(['serial_no' => 'PIN-R']);
        $this->bindLot($unit, $lot, ['unbound_at' => now(), 'unbind_reason' => 'wrong part']);

        // Precision describes what is returned: with only removed bindings and
        // include_removed off, there is nothing unit-level to show.
        $without = $this->whereUsed($lot)->assertOk()->json('data');
        $this->assertSame('none', $without['precision']);
        $this->assertFalse($without['has_unit_bindings']);
        $this->assertSame([], $without['direct_units']);
        $this->assertSame(1, $without['totals']['removed_bindings']);

        $with = $this->whereUsed($lot, ['include_removed' => 1])->assertOk()->json('data');
        $this->assertSame('unit', $with['precision']);
        $this->assertTrue($with['has_unit_bindings']);
        $this->assertSame(['PIN-R'], array_column($with['direct_units'], 'serial_no'));
        $this->assertFalse($with['direct_units'][0]['bindings'][0]['installed']);
        $this->assertSame('wrong part', $with['direct_units'][0]['bindings'][0]['unbind_reason']);
    }

    public function test_only_removed_bindings_fall_back_to_batch_precision(): void
    {
        $lot = MaterialLot::factory()->create();
        $batch = Batch::factory()->create();
        $step = BatchStep::factory()->create(['batch_id' => $batch->id]);
        BatchStepLotConsumption::create(['batch_step_id' => $step->id, 'material_lot_id' => $lot->id, 'quantity_consumed' => 1, 'consumed_at' => now()]);
        $this->bindLot(SerialUnit::factory()->create(['batch_id' => $batch->id]), $lot, ['unbound_at' => now()]);

        $data = $this->whereUsed($lot)->assertOk()->json('data');

        $this->assertSame('batch', $data['precision']);
        $this->assertSame(1, $data['totals']['removed_bindings']);
    }

    public function test_include_removed_accepts_true_and_false_strings(): void
    {
        $lot = MaterialLot::factory()->create();
        $this->bindLot(SerialUnit::factory()->create(['serial_no' => 'PIN-R']), $lot, ['unbound_at' => now()]);

        $this->whereUsed($lot, ['include_removed' => 'true'])->assertOk()
            ->assertJsonPath('data.params.include_removed', true)
            ->assertJsonPath('data.direct_units.0.serial_no', 'PIN-R');
        $this->whereUsed($lot, ['include_removed' => 'false'])->assertOk()
            ->assertJsonPath('data.params.include_removed', false)
            ->assertJsonPath('data.direct_units', []);
    }

    public function test_identifier_only_bindings_scanned_before_the_lot_existed_are_found(): void
    {
        // Normalising off: the scan is stored as typed (lower case, spaces).
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_identifier_normalize'], ['value' => json_encode(false)]);
        $unit = SerialUnit::factory()->create(['serial_no' => 'SN-LATE']);
        app(SerialTraceService::class)->bindComponent($unit, ' late-lot -1', $this->user);
        $this->assertDatabaseHas('serial_unit_components', ['identifier' => 'late-lot -1', 'material_lot_id' => null]);

        $lot = MaterialLot::factory()->create(['lot_number' => 'LATE-LOT-1']);

        $data = $this->whereUsed($lot)->assertOk()->json('data');

        $this->assertSame('unit', $data['precision']);
        $this->assertSame(['SN-LATE'], array_column($data['direct_units'], 'serial_no'));
        $this->assertSame('identifier', $data['direct_units'][0]['bindings'][0]['match']);
    }

    public function test_falls_back_to_batch_level_when_no_unit_bindings_exist(): void
    {
        $lot = MaterialLot::factory()->create();
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-BATCH']);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id]);
        BatchStepLotConsumption::create(['batch_step_id' => $step->id, 'material_lot_id' => $lot->id, 'quantity_consumed' => 12, 'consumed_at' => now()]);
        BatchStepLotConsumption::create(['batch_step_id' => $step->id, 'material_lot_id' => $lot->id, 'quantity_consumed' => 3, 'consumed_at' => now()]);
        SerialUnit::factory()->count(3)->create(['work_order_id' => $wo->id, 'batch_id' => $batch->id]);
        SerialUnit::factory()->create(['work_order_id' => $wo->id]); // another batch's unit: not a candidate

        $data = $this->whereUsed($lot)->assertOk()->json('data');

        $this->assertSame('batch', $data['precision']);
        $this->assertFalse($data['has_unit_bindings']);
        $this->assertSame([], $data['direct_units']);
        $this->assertSame([], $data['parent_units']);
        $this->assertSame([], $data['work_orders']);

        $fallback = $data['batch_level'];
        $this->assertSame(1, $fallback['totals']['work_orders']);
        $this->assertSame(3, $fallback['totals']['candidate_units']);
        $this->assertEqualsWithDelta(15.0, $fallback['totals']['quantity_consumed'], 0.0001);
        $this->assertSame('WO-BATCH', $fallback['work_orders'][0]['work_order']['order_no']);
        $this->assertSame($batch->id, $fallback['work_orders'][0]['batches'][0]['id']);
        $this->assertSame(3, $fallback['work_orders'][0]['batches'][0]['candidate_units']);
        $this->assertSame(0, $fallback['work_orders'][0]['batches'][0]['bound_units']);
    }

    public function test_batch_level_still_shows_a_consumption_on_a_deleted_batch(): void
    {
        $lot = MaterialLot::factory()->create();
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-GONE']);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id]);
        BatchStepLotConsumption::create(['batch_step_id' => $step->id, 'material_lot_id' => $lot->id, 'quantity_consumed' => 4, 'consumed_at' => now()]);

        $batch->delete(); // soft-deletes its steps too
        $this->assertSoftDeleted($step);

        $data = $this->whereUsed($lot)->assertOk()->json('data');

        $this->assertSame('batch', $data['precision']);
        $this->assertSame('WO-GONE', $data['batch_level']['work_orders'][0]['work_order']['order_no']);
        $this->assertSame($batch->id, $data['batch_level']['work_orders'][0]['batches'][0]['id']);
    }

    public function test_deleted_material_and_product_are_still_named(): void
    {
        $material = Material::factory()->create(['code' => 'PIN-12', 'name' => 'Pinion 12T']);
        $lot = MaterialLot::factory()->create(['material_id' => $material->id]);
        $wo = WorkOrder::factory()->create();
        $productName = $wo->productType->name;
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id]);
        BatchStepLotConsumption::create(['batch_step_id' => $step->id, 'material_lot_id' => $lot->id, 'quantity_consumed' => 1, 'consumed_at' => now()]);
        $unit = SerialUnit::factory()->create(['work_order_id' => $wo->id, 'batch_id' => $batch->id, 'material_id' => $material->id]);
        $this->bindLot($unit, $lot);

        $wo->productType->delete();
        $material->delete();
        $this->assertSoftDeleted($material);

        $data = $this->whereUsed($lot)->assertOk()->json('data');

        $this->assertSame('PIN-12', $data['lot']['material']['code']);
        $this->assertSame('Pinion 12T', $data['direct_units'][0]['material']['name']);
        $this->assertSame($productName, $data['direct_units'][0]['work_order']['product']);
        $this->assertSame($productName, $data['work_orders'][0]['work_order']['product']);
        $this->assertSame($productName, $data['batch_level']['work_orders'][0]['work_order']['product']);
    }

    public function test_a_lot_with_no_usage_reports_precision_none(): void
    {
        $lot = MaterialLot::factory()->create();

        $data = $this->whereUsed($lot)->assertOk()->json('data');

        $this->assertSame('none', $data['precision']);
        $this->assertSame(0, $data['totals']['direct_units']);
        $this->assertSame([], $data['batch_level']['work_orders']);
    }

    public function test_a_containment_cycle_does_not_loop(): void
    {
        $lot = MaterialLot::factory()->create();
        $a = SerialUnit::factory()->create(['serial_no' => 'CYC-A']);
        $b = SerialUnit::factory()->create(['serial_no' => 'CYC-B']);
        $c = SerialUnit::factory()->create(['serial_no' => 'CYC-C']);
        $this->bindLot($a, $lot);
        // Corrupted data: A in B, B in C, C in A.
        $this->bindUnit($b, $a);
        $this->bindUnit($c, $b);
        $this->bindUnit($a, $c);

        $data = $this->whereUsed($lot)->assertOk()->json('data');

        $this->assertSame(['CYC-A'], array_column($data['direct_units'], 'serial_no'));
        $this->assertSame(['CYC-B', 'CYC-C'], array_column($data['parent_units'], 'serial_no'));
        $this->assertSame(['CYC-C'], array_column($data['direct_units'][0]['contains'], 'serial_no'));
        $this->assertFalse($data['truncated']);
    }

    public function test_depth_and_limit_bound_the_answer_and_say_so(): void
    {
        $lot = MaterialLot::factory()->create();
        $pinion = SerialUnit::factory()->create(['serial_no' => 'D-0']);
        $kit = SerialUnit::factory()->create(['serial_no' => 'D-1']);
        $box = SerialUnit::factory()->create(['serial_no' => 'D-2']);
        $this->bindLot($pinion, $lot);
        $this->bindUnit($kit, $pinion);
        $this->bindUnit($box, $kit);

        $shallow = $this->whereUsed($lot, ['max_depth' => 1])->assertOk()->json('data');
        $this->assertSame(['D-1'], array_column($shallow['parent_units'], 'serial_no'));
        $this->assertTrue($shallow['truncated']);
        $this->assertSame(['depth'], $shallow['truncated_by']);
        // D-1 sits in D-2, which max_depth cut off: unknown, not "top level" nor "inside nothing".
        $this->assertNull($shallow['parent_units'][0]['top_level']);
        $this->assertSame([], $shallow['parent_units'][0]['contained_in']);
        $this->assertSame(0, $shallow['totals']['top_level_units']);
        $this->assertSame(1, $shallow['totals']['top_level_unknown_units']);

        $direct = $this->whereUsed($lot, ['max_depth' => 0])->assertOk()->json('data');
        $this->assertSame([], $direct['parent_units']);
        $this->assertNull($direct['direct_units'][0]['top_level']);
        $this->assertSame(0, $direct['totals']['top_level_units']);
        $this->assertSame(1, $direct['totals']['top_level_unknown_units']);
        $this->assertSame(['depth'], $direct['truncated_by']);
        $this->assertSame(0, $direct['work_orders'][0]['top_level_units']);

        foreach (['D-3', 'D-4'] as $sn) {
            $this->bindLot(SerialUnit::factory()->create(['serial_no' => $sn]), $lot);
        }
        $limited = $this->whereUsed($lot, ['limit' => 2])->assertOk()->json('data');
        $this->assertCount(2, $limited['direct_units']);
        $this->assertSame([], $limited['parent_units']);
        $this->assertSame(3, $limited['totals']['direct_units']);
        $this->assertSame(2, $limited['totals']['direct_units_returned']);
        $this->assertSame(['limit'], $limited['truncated_by']);
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        $lot = MaterialLot::factory()->create();

        $this->whereUsed($lot, ['max_depth' => 99, 'limit' => 0, 'include_removed' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['max_depth', 'limit', 'include_removed']);
    }

    public function test_guest_is_unauthorized(): void
    {
        $lot = MaterialLot::factory()->create();

        $this->getJson("/api/v1/material-lots/{$lot->id}/where-used")->assertUnauthorized();
    }

    public function test_any_authenticated_role_may_read_like_the_genealogy_endpoints(): void
    {
        $lot = MaterialLot::factory()->create();
        $operator = tap(User::factory()->create(), fn (User $u) => $u->assignRole('Operator'));

        $this->whereUsed($lot, [], $operator)->assertOk();
        $this->actingAs($operator, 'sanctum')->getJson("/api/v1/material-lots/{$lot->id}/genealogy/forward")->assertOk();
    }

    public function test_a_lot_of_another_tenant_is_not_found(): void
    {
        $mine = Tenant::create(['name' => 'Plant A']);
        $theirs = Tenant::create(['name' => 'Plant B']);
        $user = User::factory()->create(['tenant_id' => $mine->id]);
        $lot = MaterialLot::factory()->create(['tenant_id' => $theirs->id]);

        $this->whereUsed($lot, [], $user)->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/material-lots/{$lot->id}/genealogy/forward")->assertNotFound();
    }

    public function test_index_filters_by_exact_lot_number(): void
    {
        $material = Material::factory()->create();
        $target = MaterialLot::factory()->create(['lot_number' => 'L-2026-0431', 'material_id' => $material->id, 'supplier_lot_no' => 'SUP-1']);
        MaterialLot::factory()->create(['lot_number' => 'L-2026-04310', 'material_id' => $material->id]);
        MaterialLot::factory()->create(['lot_number' => 'L-2026-0432', 'supplier_lot_no' => 'SUP-1']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/material-lots?lot_number=L-2026-0431')
            ->assertOk();
        $this->assertSame([$target->id], array_column($response->json('data'), 'id'));
        $this->assertSame(1, $response->json('meta.total'));

        // Combines with the existing filters.
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/material-lots?lot_number=L-2026-0431&supplier_lot_no=SUP&material_id='.$material->id)
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/material-lots?lot_number=L-2026-0432&material_id='.$material->id)
            ->assertOk()->assertJsonPath('meta.total', 0);
        // Existing filters still work on their own.
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/material-lots?supplier_lot_no=SUP-1')
            ->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_index_rejects_a_non_string_lot_number(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/material-lots?lot_number[]=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lot_number']);
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/material-lots?lot_number='.str_repeat('L', 256))
            ->assertUnprocessable();
    }

    public function test_index_lot_number_filter_is_tenant_scoped(): void
    {
        $mine = Tenant::create(['name' => 'Plant A']);
        $theirs = Tenant::create(['name' => 'Plant B']);
        $user = User::factory()->create(['tenant_id' => $mine->id]);
        MaterialLot::factory()->create(['lot_number' => 'SHARED-1', 'tenant_id' => $theirs->id]);
        $own = MaterialLot::factory()->create(['lot_number' => 'SHARED-1', 'tenant_id' => $mine->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/material-lots?lot_number=SHARED-1')->assertOk();
        $this->assertSame([$own->id], array_column($response->json('data'), 'id'));
    }
}
