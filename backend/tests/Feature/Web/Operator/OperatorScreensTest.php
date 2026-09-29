<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The operator sees the tabs their step needs: a packing bench sees Packing,
 * an assembly bench of a serialised product sees the queue, the workstation
 * table and SN labels. Staff and the whole-line view see every tab.
 */
class OperatorScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    private Workstation $assembly;

    private Workstation $tester;

    private Workstation $packing;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        Role::findOrCreate('Admin', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->line = Line::factory()->create(['is_active' => true]);
        $this->operator->lines()->attach($this->line->id);

        $this->assembly = Workstation::create(['line_id' => $this->line->id, 'code' => 'ASM', 'name' => 'Assembly', 'is_active' => true]);
        $this->tester = Workstation::create(['line_id' => $this->line->id, 'code' => 'TST', 'name' => 'Tester', 'is_active' => true]);
        $this->packing = Workstation::create(['line_id' => $this->line->id, 'code' => 'PAK', 'name' => 'Packing', 'is_active' => true]);

        $product = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Assemble', 'workstation_id' => $this->assembly->id]);
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Test', 'workstation_id' => $this->tester->id]);
        TemplateStep::create([
            'process_template_id' => $template->id, 'step_number' => 3, 'name' => 'Pack', 'workstation_id' => $this->packing->id,
            'kind' => TemplateStep::KIND_PACKING, 'config' => ['unit' => 'carton', 'carton_capacity' => 2],
        ]);
        // The product numbers its units, so its production benches get SN labels.
        LotSequence::create(['name' => 'SN', 'product_type_id' => $product->id, 'purpose' => LotSequence::PURPOSE_UNIT_SERIAL, 'prefix' => '', 'pattern' => '[seq]', 'pad_size' => 4, 'next_number' => 1, 'reset_period' => 'none']);
    }

    private function screensAt(?Workstation $bench, ?User $user = null)
    {
        $query = $bench ? "?line={$this->line->id}&workstation={$bench->id}" : "?line={$this->line->id}&workstation=all";
        // A real request gets a fresh request-scoped service (Octane flushes it too);
        // requests inside one test share the app, so drop it by hand.
        $this->app->forgetScopedInstances();

        return $this->actingAs($user ?? $this->operator)->get('/operator/queue'.$query)->assertOk();
    }

    public function test_each_bench_gets_the_tabs_its_routed_steps_need(): void
    {
        $this->screensAt($this->packing)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['packing']));
        $this->screensAt($this->assembly)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation', 'unit_labels']));

        // A bench nothing is routed to still has somewhere to work.
        $idle = Workstation::create(['line_id' => $this->line->id, 'code' => 'IDLE', 'name' => 'Idle', 'is_active' => true]);
        $this->screensAt($idle)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation']));
    }

    public function test_the_whole_line_view_and_staff_see_every_tab(): void
    {
        $this->screensAt(null)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation', 'unit_labels', 'packing']));

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->screensAt($this->packing, $admin)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation', 'unit_labels', 'packing']));
    }

    public function test_an_operator_with_an_assigned_bench_still_sees_every_tab_on_the_whole_line(): void
    {
        $assigned = User::factory()->create(['workstation_id' => $this->packing->id]);
        $assigned->assignRole('Operator');
        $assigned->lines()->attach($this->line->id);

        $this->screensAt(null, $assigned)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation', 'unit_labels', 'packing']));
    }

    public function test_steps_routed_by_workstation_type_reach_every_bench_of_that_type_but_pinned_steps_do_not(): void
    {
        $type = WorkstationType::create(['code' => 'PACKER', 'name' => 'Packer', 'is_active' => true]);
        $benchA = Workstation::create(['line_id' => $this->line->id, 'code' => 'PK-A', 'name' => 'Pack A', 'is_active' => true, 'workstation_type_id' => $type->id]);
        $benchB = Workstation::create(['line_id' => $this->line->id, 'code' => 'PK-B', 'name' => 'Pack B', 'is_active' => true, 'workstation_type_id' => $type->id]);

        $product = ProductType::factory()->create();
        $product->lines()->attach($this->line->id);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        // Pinned to bench A (with its type recorded): bench B must not inherit it.
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Assemble at A', 'workstation_id' => $benchA->id, 'workstation_type_id' => $type->id]);
        $this->screensAt($benchB)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation'])); // nothing routed: the fallback

        // Open to any packer: both benches get Packing.
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Pack', 'workstation_type_id' => $type->id, 'kind' => TemplateStep::KIND_PACKING, 'config' => ['unit' => 'pallet']]);
        $this->screensAt($benchB)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['packing']));
        $this->screensAt($benchA)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation', 'packing']));
    }

    public function test_a_cancelled_batch_no_longer_gives_its_bench_a_tab(): void
    {
        $bench = Workstation::create(['line_id' => $this->line->id, 'code' => 'OLD', 'name' => 'Old packer', 'is_active' => true]);
        $wo = WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'status' => Batch::STATUS_IN_PROGRESS]);
        BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'status' => BatchStep::STATUS_PENDING, 'workstation_id' => $bench->id, 'kind' => TemplateStep::KIND_PACKING]);
        $this->screensAt($bench)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['packing']));

        $batch->update(['status' => Batch::STATUS_CANCELLED]);
        $this->screensAt($bench)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation']));
    }

    public function test_an_admin_can_pin_a_benchs_tabs(): void
    {
        $this->tester->update(['operator_screens' => ['queue', 'workstation']]);

        $this->screensAt($this->tester)->assertInertia(fn (Assert $page) => $page->where('operatorTabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === ['queue', 'workstation']));
    }

    public function test_picking_a_packing_bench_lands_on_packing(): void
    {
        $this->actingAs($this->operator)->post(route('operator.select-line.post'), ['line_id' => $this->line->id, 'workstation_id' => $this->packing->id])
            ->assertRedirect(route('operator.packaging', ['line' => $this->line->id, 'workstation' => $this->packing->id]));

        $this->actingAs($this->operator)->post(route('operator.select-line.post'), ['line_id' => $this->line->id, 'workstation_id' => $this->assembly->id])
            ->assertRedirect(route('operator.queue', ['line' => $this->line->id, 'workstation' => $this->assembly->id]));

        // A workstation account opens straight on its bench's first screen.
        $account = User::factory()->create(['workstation_id' => $this->packing->id]);
        $account->assignRole('Operator');
        $this->actingAs($account)->get(route('operator.select-line'))->assertRedirect(route('operator.packaging'));

        // ... and so does its login: not a queue it has no tab for.
        auth()->logout();
        $terminal = User::factory()->create(['username' => 'pack-terminal', 'password' => \Illuminate\Support\Facades\Hash::make('terminal-pass'),
            'account_type' => 'workstation', 'workstation_id' => $this->packing->id]);
        $terminal->assignRole('Operator');
        $this->post(route('login'), ['username' => 'pack-terminal', 'password' => 'terminal-pass'])
            ->assertRedirect(route('operator.packaging', ['line' => $this->line->id]));
    }

    public function test_the_admin_form_stores_the_choice_and_validates_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $url = "/admin/lines/{$this->line->id}/workstations/{$this->tester->id}";
        $base = ['code' => 'TST', 'name' => 'Tester', 'is_active' => true];

        // Guest first: actingAs() sticks for the rest of the test.
        $this->put($url, $base + ['operator_screens' => ['queue']])->assertRedirect('/login');
        $this->actingAs($this->operator)->put($url, $base + ['operator_screens' => ['queue']])->assertForbidden();

        $this->actingAs($admin)->put($url, $base + ['operator_screens' => ['queue', 'workstation']])->assertSessionHasNoErrors();
        $this->assertSame(['queue', 'workstation'], $this->tester->fresh()->operator_screens);

        $this->actingAs($admin)->put($url, $base + ['operator_screens' => ['cockpit']])->assertSessionHasErrors('operator_screens.0');

        // An edit that does not mention the tabs leaves them alone.
        $this->actingAs($admin)->put($url, ['code' => 'TST', 'name' => 'Tester renamed', 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame(['queue', 'workstation'], $this->tester->fresh()->operator_screens);

        // Back to "from the routing": null or an empty list.
        $this->actingAs($admin)->put($url, $base + ['operator_screens' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->tester->fresh()->operator_screens);
        $this->tester->update(['operator_screens' => ['queue']]);
        $this->actingAs($admin)->put($url, $base + ['operator_screens' => []])->assertSessionHasNoErrors();
        $this->assertNull($this->tester->fresh()->operator_screens);

        // The list page shows what the routing gives each bench.
        $this->actingAs($admin)->get("/admin/lines/{$this->line->id}/workstations")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('workstations.0.code', 'ASM')->where('workstations.0.derived_screens', ['queue', 'workstation', 'unit_labels']));
    }
}
