<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use App\Support\UnitLabelActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** What the SN label station offers at a bench: set per workstation, everything by default. */
class UnitLabelActionsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Workstation $first;

    private Workstation $labelling;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Operator', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->line = Line::factory()->create(['is_active' => true]);
        $this->first = Workstation::create(['line_id' => $this->line->id, 'code' => 'ST-1', 'name' => 'First', 'is_active' => true, 'unit_label_actions' => ['start']]);
        $this->labelling = Workstation::create(['line_id' => $this->line->id, 'code' => 'ST-9', 'name' => 'Labelling', 'is_active' => true]);
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operator->lines()->attach($this->line->id);
    }

    private function station(User $user, Workstation $bench)
    {
        return $this->actingAs($user)->get("/operator/unit-labels/station?line={$this->line->id}&workstation={$bench->id}")->assertOk();
    }

    public function test_the_bench_setting_decides_what_the_operator_sees_and_staff_see_everything(): void
    {
        $this->station($this->operator, $this->first)->assertInertia(fn (Assert $page) => $page->where('labelActions', ['start']));
        $this->station($this->operator, $this->labelling)->assertInertia(fn (Assert $page) => $page->where('labelActions', UnitLabelActions::ALL));

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->station($admin, $this->first)->assertInertia(fn (Assert $page) => $page->where('labelActions', UnitLabelActions::ALL));
    }

    public function test_the_server_holds_the_bench_setting_too(): void
    {
        // A bench pinned to "start": binding a label or a part by a hand-made request is refused.
        $atFirst = $this->actingAs($this->operator)->withSession(['selected_workstation_id' => $this->first->id]);
        $atFirst->postJson(route('operator.unit-labels.apply'), ['serial_no' => 'SN-1', 'psn' => 'P-1'])->assertForbidden();
        $atFirst->postJson(route('operator.unit-labels.component'), ['serial_no' => 'SN-1', 'identifier' => 'PART'])->assertForbidden();
        $this->assertDatabaseMissing('serial_units', ['serial_no' => 'SN-1']);

        // A bench with no setting, and staff anywhere, keep every action.
        $this->actingAs($this->operator)->withSession(['selected_workstation_id' => $this->labelling->id])
            ->postJson(route('operator.unit-labels.apply'), ['serial_no' => 'SN-2', 'psn' => 'P-2'])->assertOk();
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin)->withSession(['selected_workstation_id' => $this->first->id])
            ->postJson(route('operator.unit-labels.apply'), ['serial_no' => 'SN-3', 'psn' => 'P-3'])->assertOk();
    }

    public function test_the_workstation_form_stores_the_choice_and_validates_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $url = "/admin/lines/{$this->line->id}/workstations/{$this->labelling->id}";
        $base = ['code' => 'ST-9', 'name' => 'Labelling', 'is_active' => true];

        $this->actingAs($admin)->put($url, $base + ['unit_label_actions' => ['print-money']])->assertSessionHasErrors('unit_label_actions.0');
        $this->actingAs($admin)->put($url, $base + ['unit_label_actions' => ['label']])->assertSessionHasNoErrors();
        $this->assertSame(['label'], $this->labelling->fresh()->unit_label_actions);

        // Nothing ticked is "everything" again.
        $this->actingAs($admin)->put($url, $base + ['unit_label_actions' => []])->assertSessionHasNoErrors();
        $this->assertNull($this->labelling->fresh()->unit_label_actions);
    }
}
