<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Line;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\UnitSerialisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnitLabelStationTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('operator.unit-labels.station'))->assertRedirect(route('login'));
    }

    public function test_without_a_selected_line_the_operator_picks_one_first(): void
    {
        $this->actingAs($this->operator)
            ->get(route('operator.unit-labels.station'))
            ->assertRedirect(route('operator.select-line'));
    }

    public function test_station_carries_the_selected_line_and_only_its_orders(): void
    {
        $line = Line::factory()->create(['name' => 'Line A']);
        $other = Line::factory()->create();
        $mine = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        WorkOrder::factory()->create(['line_id' => $other->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $line->id])
            ->get(route('operator.unit-labels.station'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/unit-labels/Station')
                ->where('line.name', 'Line A')
                ->has('workOrders', 1)
                ->where('workOrders.0.order_no', $mine->order_no));
    }

    // ── binding ─────────────────────────────────────────────────────────

    private function setting(string $key, mixed $value): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
        app(UnitSerialisation::class)->forget();
    }

    private function bind(User $as, array $payload)
    {
        return $this->actingAs($as)->postJson(route('operator.unit-labels.apply'), $payload);
    }

    public function test_binding_registers_the_unit_normalised_and_records_the_event(): void
    {
        $this->bind($this->operator, ['serial_no' => 'sn-1001 -0001', 'psn' => 'P-0001'])
            ->assertOk()
            ->assertJsonPath('created', true)
            ->assertJsonPath('unit.serial_no', 'SN-1001-0001')
            ->assertJsonPath('unit.psn', 'P-0001');

        $unit = SerialUnit::where('serial_no', 'SN-1001-0001')->firstOrFail();
        $this->assertNotNull($unit->extra_data['label_applied_at'] ?? null);
        $this->assertSame('label_applied', $unit->history()->firstOrFail()->parameters['event']);
        $this->assertSame($this->operator->id, $unit->history()->firstOrFail()->operator_id);
    }

    public function test_configured_patterns_are_enforced(): void
    {
        $this->setting('unit_serial_pattern', '^SN-[0-9]{4}-[0-9]{4}$');
        $this->setting('unit_psn_pattern', '^P-[0-9]+$');

        $this->bind($this->operator, ['serial_no' => 'ABC', 'psn' => 'P-0001'])
            ->assertUnprocessable()->assertJsonValidationErrors(['serial_no']);
        $this->bind($this->operator, ['serial_no' => 'SN-1001-0001', 'psn' => 'Q-0184'])
            ->assertUnprocessable()->assertJsonValidationErrors(['psn']);
        $this->bind($this->operator, ['serial_no' => 'SN-1001-0001', 'psn' => 'P-0001'])->assertOk();
    }

    public function test_process_serial_can_be_required(): void
    {
        $this->bind($this->operator, ['serial_no' => 'SN-1'])->assertOk();

        $this->setting('unit_psn_required', true);
        $this->bind($this->operator, ['serial_no' => 'SN-2'])
            ->assertUnprocessable()->assertJsonValidationErrors(['psn']);
    }

    public function test_a_process_serial_owned_by_another_unit_is_refused_unless_shared(): void
    {
        $this->bind($this->operator, ['serial_no' => 'SN-1', 'psn' => 'P-1'])->assertOk();
        $this->bind($this->operator, ['serial_no' => 'SN-2', 'psn' => 'P-1'])
            ->assertUnprocessable()->assertJsonPath('rebindable', false);

        $this->setting('unit_psn_unique', false);
        $this->bind($this->operator, ['serial_no' => 'SN-2', 'psn' => 'P-1'])->assertOk();
    }

    public function test_rescanning_the_same_binding_is_confirmed_not_duplicated(): void
    {
        $this->bind($this->operator, ['serial_no' => 'SN-1', 'psn' => 'P-1'])->assertOk()->assertJsonPath('created', true);
        $this->bind($this->operator, ['serial_no' => 'SN-1', 'psn' => 'P-1'])->assertOk()->assertJsonPath('created', false);

        $this->assertSame(1, SerialUnit::where('serial_no', 'SN-1')->count());
    }

    public function test_only_a_supervisor_can_move_a_unit_to_another_process_serial(): void
    {
        Role::findOrCreate('Supervisor', 'web');
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $this->bind($this->operator, ['serial_no' => 'SN-1', 'psn' => 'P-1'])->assertOk();

        // Refused, and flagged as the kind of refusal a supervisor may override.
        $this->bind($this->operator, ['serial_no' => 'SN-1', 'psn' => 'P-2'])
            ->assertUnprocessable()->assertJsonPath('rebindable', true);
        // An operator's `force` is ignored.
        $this->bind($this->operator, ['serial_no' => 'SN-1', 'psn' => 'P-2', 'force' => true, 'reason' => 'x'])
            ->assertUnprocessable();

        $this->bind($supervisor, ['serial_no' => 'SN-1', 'psn' => 'P-2', 'force' => true, 'reason' => 'Wrong label at the labelling bench'])
            ->assertOk()->assertJsonPath('rebound', true)->assertJsonPath('unit.psn', 'P-2');

        $event = SerialUnit::where('serial_no', 'SN-1')->firstOrFail()->history()->reorder('id', 'desc')->firstOrFail();
        $this->assertSame('process_serial_rebound', $event->parameters['event']);
        $this->assertSame('P-1', $event->parameters['previous_psn']);
        $this->assertSame('Wrong label at the labelling bench', $event->notes);
    }

    public function test_a_shipped_or_scrapped_unit_cannot_be_bound(): void
    {
        SerialUnit::create(['serial_no' => 'SN-GONE', 'status' => SerialUnit::STATUS_SCRAPPED]);

        $this->bind($this->operator, ['serial_no' => 'SN-GONE', 'psn' => 'P-9'])->assertUnprocessable();
    }

    public function test_scrapping_a_unit_needs_a_reason_and_is_final(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'SN-1', 'psn' => 'P-1']);

        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.scrap', $unit), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.scrap', $unit), ['reason' => 'Cracked housing'])
            ->assertOk()->assertJsonPath('unit.status', 'scrapped');

        $event = $unit->history()->reorder('id', 'desc')->firstOrFail();
        $this->assertSame('scrapped', $event->parameters['event']);
        $this->assertSame('Cracked housing', $event->notes);

        // Scrapped: no more binding, no packing.
        $this->bind($this->operator, ['serial_no' => 'SN-1', 'psn' => 'P-2'])->assertUnprocessable();
    }

    public function test_a_shipped_unit_cannot_be_scrapped_from_the_station(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'SN-OUT', 'status' => SerialUnit::STATUS_SHIPPED]);

        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.scrap', $unit), ['reason' => 'x'])->assertUnprocessable();
    }
}
