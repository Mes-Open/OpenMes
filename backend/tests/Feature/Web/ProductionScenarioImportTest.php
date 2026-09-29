<?php

namespace Tests\Feature\Web;

use App\Models\Batch;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\Pallet;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\ScrapReason;
use App\Models\SerialUnit;
use App\Models\TemplateStep;
use App\Models\UnitCarton;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → Import with a `scenario` section: shop-floor events replayed
 * through the production services at their own times, all or nothing.
 */
class ProductionScenarioImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');
        $this->admin = User::factory()->create(['username' => 'boss', 'password' => Hash::make('boss-secret')]);
        $this->admin->assignRole('Admin');
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));
        $this->plant();
    }

    /** One line, three benches, a product with assembly → test → packing, its sequences, a component with a lot, an error code. */
    private function plant(): void
    {
        foreach (['production_flow_mode' => 'transfer', 'workstation_routing_enabled' => true] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
        }
        $line = Line::factory()->create(['code' => 'SC-L']);
        $bench = fn (string $code) => Workstation::factory()->create(['code' => $code, 'line_id' => $line->id]);
        [$assembly, $test, $packing] = [$bench('SC-A'), $bench('SC-T'), $bench('SC-P')];
        $product = ProductType::factory()->create(['code' => 'SC-PT']);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Assemble', 'workstation_id' => $assembly->id]);
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Test', 'workstation_id' => $test->id]);
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 3, 'name' => 'Pack', 'workstation_id' => $packing->id, 'kind' => TemplateStep::KIND_PACKING,
            'config' => TemplateStep::normalisePackingConfig(['unit' => 'carton', 'carton_capacity' => 2])]);
        foreach ([LotSequence::PURPOSE_PROCESS_SERIAL => 'P-[seq]', LotSequence::PURPOSE_UNIT_SERIAL => 'S-[seq]'] as $purpose => $pattern) {
            LotSequence::create(['product_type_id' => $product->id, 'purpose' => $purpose, 'name' => $purpose, 'prefix' => '', 'pattern' => $pattern, 'pad_size' => 4, 'next_number' => 1, 'reset_period' => 'none']);
        }
        $type = MaterialType::firstOrCreate(['code' => 'sc_component'], ['name' => 'Component']);
        Material::factory()->create(['code' => 'SC-M', 'material_type_id' => $type->id, 'tracking_type' => 'batch']);
        ScrapReason::create(['code' => 'SC-NC', 'name' => 'Non-conformity', 'category' => 'method', 'is_active' => true]);
    }

    private function import(array $data)
    {
        return $this->actingAs($this->admin)->post(route('settings.import'), [
            'settings_file' => UploadedFile::fake()->createWithContent('scenario.json', json_encode($data)),
        ]);
    }

    private function order(): array
    {
        return [
            ['at' => '-1d 07:00', 'do' => 'work_order', 'order_no' => 'WO-SC-1', 'product' => 'SC-PT', 'line' => 'SC-L', 'quantity' => 2, 'due' => '+2d'],
            ['at' => '+1m', 'do' => 'batch', 'order' => 'WO-SC-1', 'quantity' => 2],
        ];
    }

    public function test_a_scenario_is_replayed_through_the_line_at_its_own_times(): void
    {
        $events = [
            ...$this->order(),
            ['at' => '+2m', 'do' => 'step_start', 'order' => 'WO-SC-1', 'step' => 1, 'by' => 'op-a'],
            ['at' => '+1m', 'do' => 'unit_start', 'order' => 'WO-SC-1', 'ref' => 'u1', 'workstation' => 'SC-A', 'by' => 'op-a'],
            ['at' => '+30s', 'do' => 'unit_start', 'order' => 'WO-SC-1', 'ref' => 'u2', 'workstation' => 'SC-A', 'by' => 'op-a'],
            ['at' => '+5m', 'do' => 'component', 'unit' => 'u1', 'identifier' => 'LOT-SC-1', 'material' => 'SC-M', 'step' => 1, 'workstation' => 'SC-A', 'by' => 'op-a'],
            ['do' => 'subassembly', 'order' => 'WO-SC-1', 'material' => 'SC-M', 'sn' => 'SUB-0001', 'workstation' => 'SC-A', 'by' => 'op-a'],
            ['do' => 'component', 'unit' => 'u2', 'identifier' => 'SUB-0001', 'step' => 1, 'workstation' => 'SC-A', 'by' => 'op-a'],
            ['do' => 'unit_serial', 'unit' => 'u1', 'workstation' => 'SC-A', 'by' => 'op-a'],
            ['do' => 'unit_serial', 'unit' => 'u2', 'workstation' => 'SC-A', 'by' => 'op-a'],
            ['at' => '+1m', 'do' => 'step_log', 'order' => 'WO-SC-1', 'step' => 1, 'good' => 2, 'by' => 'op-a'],
            ['do' => 'step_complete', 'order' => 'WO-SC-1', 'step' => 1, 'by' => 'op-a'],
            ['at' => '+10m', 'do' => 'step_start', 'order' => 'WO-SC-1', 'step' => 2],
            ['at' => '+2m', 'do' => 'test', 'unit' => 'u1', 'workstation' => 'SC-T', 'result' => 'pass', 'steps' => [['name' => 'Voltage', 'value' => 5.02, 'unit' => 'V', 'low' => 4.75, 'high' => 5.25]]],
            ['at' => '+2m', 'do' => 'test', 'unit' => 'u2', 'workstation' => 'SC-T', 'result' => 'fail'],
            ['at' => '+5m', 'do' => 'test', 'unit' => 'u2', 'workstation' => 'SC-T', 'result' => 'pass'],
            ['at' => '+1m', 'do' => 'block', 'unit' => 'u2', 'reason' => 'SC-NC', 'note' => 'Scratch', 'workstation' => 'SC-T'],
            ['at' => '+15m', 'do' => 'unblock', 'unit' => 'u2', 'note' => 'Polished', 'workstation' => 'SC-T'],
            ['do' => 'step_log', 'order' => 'WO-SC-1', 'step' => 2, 'good' => 2],
            ['do' => 'step_complete', 'order' => 'WO-SC-1', 'step' => 2],
            ['at' => '+20m', 'do' => 'pallet_open', 'order' => 'WO-SC-1', 'ref' => 'P1', 'workstation' => 'SC-P'],
            ['at' => '+1m', 'do' => 'pack', 'unit' => 'u1', 'workstation' => 'SC-P'],
            ['at' => '+1m', 'do' => 'pack', 'unit' => 'u2', 'workstation' => 'SC-P'],
            ['do' => 'carton_close', 'order' => 'WO-SC-1', 'pallet' => 'P1', 'workstation' => 'SC-P'],
            ['do' => 'step_complete', 'order' => 'WO-SC-1', 'step' => 3],
            // Shipped straight from open: the pallet is closed on the way, as at the bench.
            ['at' => '-1d 15:00', 'do' => 'pallet_ship', 'pallet' => 'P1'],
        ];

        $this->import([
            'scenario' => [
                'requires' => ['production_flow_mode' => 'transfer'],
                'users' => [
                    ['username' => 'op-a', 'name' => 'Assembler', 'role' => 'Operator', 'lines' => ['SC-L'], 'password' => 'op-a-pass'],
                    ['username' => 'boss', 'name' => 'Someone else', 'role' => 'Operator', 'password' => 'changed'],
                    ['username' => 'bench-p', 'name' => 'Packing bench', 'account_type' => 'workstation', 'workstation' => 'SC-P', 'lines' => ['SC-L'], 'password' => 'bench-pass'],
                ],
                'material_lots' => [['material' => 'SC-M', 'lot_number' => 'LOT-SC-1', 'quantity' => 100, 'received' => '-3d 06:00']],
                'events' => $events,
            ],
        ])->assertSessionHas('success', __('Imported :count configuration items and replayed :events production events.', ['count' => 0, 'events' => count($events)]));

        $order = WorkOrder::where('order_no', 'WO-SC-1')->firstOrFail();
        $this->assertSame(WorkOrder::STATUS_DONE, $order->status);
        $this->assertSame('2026-09-19 07:00:00', $order->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-22', $order->due_date->format('Y-m-d'));
        $this->assertSame(Batch::STATUS_DONE, $order->batches()->first()->status);
        $assembly = $order->batches()->first()->steps()->where('step_number', 1)->first();
        $this->assertSame('2026-09-19 07:03:00', $assembly->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('op-a', $assembly->startedBy->username);

        $units = SerialUnit::where('work_order_id', $order->id)->whereNotNull('psn')->orderBy('id')->get();
        $this->assertSame(['P-0001', 'P-0002'], $units->pluck('psn')->all());
        // The sub-assembly registered by its serial is the unit the second product got.
        $this->assertSame(SerialUnit::where('serial_no', 'SUB-0001')->value('id'), $units[1]->components()->first()->component_serial_unit_id);
        $this->assertSame(['S-0001', 'S-0002'], $units->pluck('serial_no')->all());
        $this->assertSame([SerialUnit::STATUS_SHIPPED, SerialUnit::STATUS_SHIPPED], $units->pluck('status')->all());
        $started = $units[0]->history()->where('parameters->event', 'started')->first();
        $this->assertSame('2026-09-19 07:04:00', $started->processed_at->format('Y-m-d H:i:s'));
        $this->assertSame('material_lot', $units[0]->history()->where('parameters->event', 'component_bound')->first()->parameters['kind']);
        $this->assertSame(MaterialLot::where('lot_number', 'LOT-SC-1')->value('id'), $units[0]->components()->first()->material_lot_id);
        $this->assertSame(['fail', 'pass'], $units[1]->history()->whereNotNull('result')->orderBy('processed_at')->pluck('result')->all());
        $this->assertTrue($units[1]->history()->where('parameters->event', 'unblocked')->exists());

        $carton = UnitCarton::where('work_order_id', $order->id)->firstOrFail();
        $this->assertSame([UnitCarton::STATUS_CLOSED, 2], [$carton->status, (int) $carton->qty]);
        $pallet = Pallet::findOrFail($carton->pallet_id);
        $this->assertSame('shipped', $pallet->status->value);
        $this->assertSame('2026-09-19 15:00:00', $pallet->shipped_at->format('Y-m-d H:i:s'));

        $operator = User::where('username', 'op-a')->firstOrFail();
        $this->assertTrue($operator->hasRole('Operator'));
        $this->assertTrue(Hash::check('op-a-pass', $operator->password));
        $this->assertSame(['SC-L'], $operator->lines()->pluck('code')->all());
        $bench = User::where('username', 'bench-p')->firstOrFail();
        $this->assertSame(['workstation', Workstation::where('code', 'SC-P')->value('id')], [$bench->account_type, $bench->workstation_id]);
        $this->assertTrue($bench->hasRole('Operator'));
        // An account that already exists is used, never changed.
        $this->assertTrue(Hash::check('boss-secret', $this->admin->fresh()->password));
        $this->assertFalse($this->admin->fresh()->hasRole('Operator'));
        // The clock is back where it was.
        $this->assertSame('2026-09-20 12:00:00', now()->format('Y-m-d H:i:s'));
    }

    public function test_the_import_sends_no_live_deltas(): void
    {
        // A plant file touches a thousand rows or more; one synchronous websocket
        // call each, after the commit, ran a slower host past its request limit.
        \Illuminate\Support\Facades\Event::fake([\App\Events\CollectionChanged::class]);

        $this->import(['scenario' => ['requires' => ['production_flow_mode' => 'transfer'], 'events' => $this->order()]])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-SC-1']);
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\CollectionChanged::class);
    }

    public function test_orders_are_planned_ahead_on_the_schedule_and_overlaps_are_refused(): void
    {
        $plan = fn (array $second) => ['scenario' => ['events' => [
            ['at' => '-1h', 'do' => 'work_order', 'order_no' => 'WO-PLAN-1', 'product' => 'SC-PT', 'line' => 'SC-L', 'quantity' => 10],
            ['do' => 'work_order', 'order_no' => 'WO-PLAN-2', 'product' => 'SC-PT', 'line' => 'SC-L', 'quantity' => 10],
            ['do' => 'schedule', 'order' => 'WO-PLAN-1', 'start' => '+1d 06:00', 'end' => '+1d 10:00'],
            ['do' => 'schedule', 'order' => 'WO-PLAN-2', ...$second],
        ]]];

        $this->import($plan(['start' => '+1d 09:00', 'end' => '+1d 12:00']))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'event 4 (schedule)'));
        $this->assertSame(0, WorkOrder::count());

        $this->import($plan(['start' => '+1d 10:00', 'end' => '+1d 14:00', 'shift' => 1]))->assertSessionHas('success');
        $first = WorkOrder::where('order_no', 'WO-PLAN-1')->firstOrFail();
        $this->assertSame(['2026-09-21 06:00', '2026-09-21 10:00', '2026-09-21'], [$first->planned_start_at->format('Y-m-d H:i'), $first->planned_end_at->format('Y-m-d H:i'), $first->due_date->format('Y-m-d')]);
        $this->assertSame(1, (int) WorkOrder::where('order_no', 'WO-PLAN-2')->value('shift_number'));
    }

    public function test_a_failing_event_saves_nothing_from_the_file_and_names_the_event(): void
    {
        $this->import([
            'lines' => [['id' => 1, 'code' => 'SC-NEW', 'name' => 'Should roll back']],
            'scenario' => ['events' => [...$this->order(), ['at' => '+1m', 'do' => 'unit_start', 'order' => 'WO-SC-1', 'workstation' => 'NOPE']]],
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'event 3 (unit_start)') && str_contains($m, 'NOPE'));

        $this->assertDatabaseMissing('work_orders', ['order_no' => 'WO-SC-1']);
        $this->assertDatabaseMissing('lines', ['code' => 'SC-NEW']);
        $this->assertSame(0, SerialUnit::count());
    }

    public function test_the_required_flow_mode_and_past_times_are_enforced(): void
    {
        DB::table('system_settings')->where('key', 'production_flow_mode')->update(['value' => json_encode('whole_batch')]);
        $this->import(['scenario' => ['requires' => ['production_flow_mode' => 'transfer'], 'events' => $this->order()]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'transfer'));
        $this->assertSame(0, WorkOrder::count());

        $this->import(['scenario' => ['events' => [['at' => '+1d 07:00', 'do' => 'work_order', 'order_no' => 'WO-LATER', 'product' => 'SC-PT', 'quantity' => 1]]]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'past'));
        $this->import(['scenario' => ['events' => [['at' => '-1d 09:00', 'do' => 'work_order', 'order_no' => 'WO-A', 'product' => 'SC-PT', 'quantity' => 1], ['at' => '-1d 08:00', 'do' => 'batch', 'order' => 'WO-A']]]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'time order'));
        $this->assertSame(0, WorkOrder::count());
    }
}
