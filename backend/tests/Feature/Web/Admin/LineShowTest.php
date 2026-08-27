<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The line configuration screen. Covers the props the redesigned page reads
 * that the old one didn't — live machine state and the operator at each
 * workstation — plus the two write paths whose URLs the page had wrong.
 */
class LineShowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $line = Line::factory()->create();

        $this->get(route('admin.lines.show', $line))->assertRedirect(route('login'));
    }

    public function test_workstation_row_carries_its_open_state_and_operator(): void
    {
        $line = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id]);
        Worker::factory()->create(['workstation_id' => $ws->id, 'name' => 'Dario Marić']);

        // A closed slice must not win over the open one, however recent it is.
        WorkstationState::create([
            'workstation_id' => $ws->id,
            'state' => WorkstationState::FAULT,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);
        WorkstationState::create([
            'workstation_id' => $ws->id,
            'state' => WorkstationState::RUNNING,
            'started_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->admin)->get(route('admin.lines.show', $line))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/lines/Show')
                ->where('effectiveWorkstations.0.state', WorkstationState::RUNNING)
                ->where('effectiveWorkstations.0.operators', ['Dario Marić'])
            );
    }

    public function test_workstation_without_a_state_row_reports_no_state(): void
    {
        $line = Line::factory()->create();
        Workstation::factory()->create(['line_id' => $line->id]);

        $this->actingAs($this->admin)->get(route('admin.lines.show', $line))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('effectiveWorkstations.0.state', null)
                ->where('effectiveWorkstations.0.operators', [])
            );
    }

    public function test_line_without_workstations_gets_the_virtual_stand_in(): void
    {
        $line = Line::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.lines.show', $line))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->count('effectiveWorkstations', 1)
                ->where('effectiveWorkstations.0.is_line_itself', true)
                ->where('effectiveWorkstations.0.state', null)
                ->where('effectiveWorkstations.0.operators', [])
            );
    }

    /**
     * The work-order panel reuses the work-order list's own columns, so the rows
     * have to carry the fields those cells read. A missing one doesn't error —
     * the meter just renders empty — so it gets asserted here.
     */
    public function test_work_order_rows_carry_the_fields_the_shared_columns_render(): void
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create(['name' => 'Beanie Hat']);
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
            'produced_qty' => 25,
        ]);

        $this->actingAs($this->admin)->get(route('admin.lines.show', $line))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('workOrders.0', fn (AssertableInertia $wo) => $wo
                    ->hasAll(['id', 'order_no', 'product_type_id', 'planned_qty', 'produced_qty', 'status', 'priority', 'due_date', 'created_at'])
                )
                ->where('productTypeNames.'.$productType->id, 'Beanie Hat')
                ->has('batchCounts')
            );
    }

    public function test_operator_can_be_unassigned_from_the_line(): void
    {
        $line = Line::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $line->users()->attach($operator->id);

        $this->actingAs($this->admin)
            ->delete(route('admin.lines.unassign-operator', [$line, $operator]))
            ->assertRedirect();

        $this->assertDatabaseMissing('line_user', [
            'line_id' => $line->id,
            'user_id' => $operator->id,
        ]);
    }

    public function test_product_type_assignment_can_be_synced(): void
    {
        $line = Line::factory()->create();
        $keep = ProductType::factory()->create();
        $drop = ProductType::factory()->create();
        $line->productTypes()->attach($drop->id);

        $this->actingAs($this->admin)
            ->post(route('admin.lines.product-types.sync', $line), ['product_type_ids' => [$keep->id]])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing([$keep->id], $line->fresh()->productTypes->pluck('id')->all());
    }
}
