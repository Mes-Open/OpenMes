<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The selected line and bench travel in the address of every operator screen,
 * so a link opens the same context wherever it is followed from.
 */
class OperatorContextInUrlTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    private Line $otherLine;

    private Workstation $bench;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->line = Line::factory()->create(['is_active' => true]);
        $this->otherLine = Line::factory()->create(['is_active' => true]);
        $this->bench = Workstation::create(['line_id' => $this->line->id, 'code' => 'PACK-1', 'name' => 'Packing', 'is_active' => true]);
        $this->operator->lines()->attach([$this->line->id, $this->otherLine->id]);
    }

    public function test_choosing_a_line_and_bench_lands_on_an_address_that_names_them(): void
    {
        $this->actingAs($this->operator)->post(route('operator.select-line.post'), ['line_id' => $this->line->id, 'workstation_id' => $this->bench->id])
            ->assertRedirect(route('operator.queue', ['line' => $this->line->id, 'workstation' => $this->bench->id]));

        $this->actingAs($this->operator)->post(route('operator.select-line.post'), ['line_id' => $this->line->id])
            ->assertRedirect(route('operator.queue', ['line' => $this->line->id, 'workstation' => 'all']));
    }

    public function test_the_address_switches_the_line_and_bench_on_every_operator_screen(): void
    {
        // Start on one line...
        $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->otherLine->id])
            ->get('/operator/queue')
            ->assertInertia(fn (Assert $page) => $page->where('line.id', $this->otherLine->id));

        // ...and a link carrying the other one opens it, with the bench.
        $this->actingAs($this->operator)->get("/operator/queue?line={$this->line->id}&workstation={$this->bench->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('line.id', $this->line->id)->where('selectedWorkstation.id', $this->bench->id))
            ->assertSessionHas('selected_line_id', $this->line->id)
            ->assertSessionHas('selected_workstation_id', $this->bench->id);

        foreach (['/operator/workstation', '/operator/unit-labels/station', '/operator/packaging'] as $screen) {
            $this->actingAs($this->operator)->get("{$screen}?line={$this->line->id}&workstation={$this->bench->id}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('line.id', $this->line->id)->where('selectedWorkstation.id', $this->bench->id));
        }

        // Back to the whole line: the bench clears.
        $this->actingAs($this->operator)->get("/operator/queue?line={$this->line->id}&workstation=all")
            ->assertInertia(fn (Assert $page) => $page->where('selectedWorkstation', null));
    }

    public function test_a_work_order_link_carrying_the_line_opens_from_another_line(): void
    {
        $order = WorkOrder::factory()->create(['line_id' => $this->line->id]);

        // The session sits on the other line; without the context the order is refused...
        $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->otherLine->id])
            ->get("/operator/work-order/{$order->id}")
            ->assertRedirect(route('operator.queue'));

        // ...and with it the page opens on the order's line, bench selected.
        $this->actingAs($this->operator)->get("/operator/work-order/{$order->id}?line={$this->line->id}&workstation={$this->bench->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('line.id', $this->line->id)->where('selectedWorkstation.id', $this->bench->id));
    }

    public function test_the_packing_station_lives_under_operator_for_operators_and_under_packaging_for_staff(): void
    {
        $query = "line={$this->line->id}&workstation={$this->bench->id}";
        $this->actingAs($this->operator)->get("/packaging/station?{$query}")
            ->assertRedirect("/operator/packaging?{$query}");

        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin)->get("/packaging/station?{$query}")->assertOk();
    }

    public function test_a_line_the_operator_is_not_assigned_to_is_ignored(): void
    {
        $foreign = Line::factory()->create(['is_active' => true]);

        $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id])
            ->get("/operator/queue?line={$foreign->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('line.id', $this->line->id));
        $this->assertSame($this->line->id, (int) session('selected_line_id'));
    }

    public function test_an_operator_with_one_line_skips_the_line_picker(): void
    {
        $solo = User::factory()->create();
        $solo->assignRole('Operator');
        $solo->lines()->attach($this->otherLine->id);

        $this->actingAs($solo)->get(route('operator.select-line'))
            ->assertRedirect(route('operator.queue', ['line' => $this->otherLine->id, 'workstation' => 'all']));
        $this->assertSame($this->otherLine->id, (int) session('selected_line_id'));
    }

    public function test_skipping_the_picker_keeps_the_bench_already_chosen_on_that_line(): void
    {
        $solo = User::factory()->create();
        $solo->assignRole('Operator');
        $solo->lines()->attach($this->line->id);
        $retired = Workstation::create(['line_id' => $this->line->id, 'code' => 'OLD-1', 'name' => 'Old', 'is_active' => false]);

        $this->actingAs($solo)
            ->withSession(['selected_line_id' => $this->line->id, 'selected_workstation_id' => $this->bench->id])
            ->get(route('operator.select-line'))
            ->assertRedirectContains("workstation={$this->bench->id}");

        // A bench that is no longer active, or belongs to another line, is dropped.
        $this->actingAs($solo)
            ->withSession(['selected_line_id' => $this->line->id, 'selected_workstation_id' => $retired->id])
            ->get(route('operator.select-line'))
            ->assertRedirect(route('operator.queue', ['line' => $this->line->id, 'workstation' => 'all']));
        $this->assertNull(session('selected_workstation_id'));
    }

    public function test_an_operator_with_several_lines_still_picks_one(): void
    {
        $this->actingAs($this->operator)->get(route('operator.select-line'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operator/SelectLine')
                ->has('lines', 2)
                ->where('operatorCanSwitchLine', true));
    }

    public function test_the_switch_line_button_is_hidden_when_there_is_only_one_line(): void
    {
        $this->operator->lines()->detach($this->otherLine->id);

        $this->actingAs($this->operator)->get("/operator/queue?line={$this->line->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('operatorCanSwitchLine', false));
    }
}
