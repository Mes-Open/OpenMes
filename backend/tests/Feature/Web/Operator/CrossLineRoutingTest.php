<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Workstation routing across lines: an order of the winding line whose first
 * steps run at a bench of the insulation line. The bench's queue lists the
 * order, so its panel, order page and step actions must open it too - and only
 * for its own steps, only with routing on, and only from a bench the operator
 * may work at. Plus skipping optional steps from the operator screens.
 */
class CrossLineRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Line $insulation;

    private Line $winding;

    private Workstation $glueBench;

    private Workstation $winder;

    private User $glueAccount; // workstation account at the glue bench

    private WorkOrder $order;  // on the winding line, routed through the glue bench

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Supervisor', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->insulation = Line::factory()->create(['name' => 'Insulation']);
        $this->winding = Line::factory()->create(['name' => 'Winding']);
        $this->glueBench = Workstation::factory()->create(['line_id' => $this->insulation->id, 'name' => 'Tube gluing']);
        $this->winder = Workstation::factory()->create(['line_id' => $this->winding->id, 'name' => 'Winder 1']);

        $this->glueAccount = User::factory()->create(['account_type' => 'workstation', 'workstation_id' => $this->glueBench->id]);
        $this->glueAccount->assignRole('Operator');

        $this->order = $this->routedOrder();
        $this->setRouting(true);
    }

    /** 1 glue (glue bench), 2 inserts - optional (glue bench), 3 wind (winder), 4 fibre optics - optional (winder). */
    private function routedOrder(): WorkOrder
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Glue tube', 'workstation_id' => $this->glueBench->id]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Inserts', 'workstation_id' => $this->glueBench->id, 'is_optional' => true]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 3, 'name' => 'Wind', 'workstation_id' => $this->winder->id]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 4, 'name' => 'Fibre optics', 'workstation_id' => $this->winder->id, 'is_optional' => true]);

        return WorkOrder::factory()->create([
            'line_id' => $this->winding->id,
            'product_type_id' => $pt->id,
            'status' => WorkOrder::STATUS_ACCEPTED,
            'planned_qty' => 3,
            'process_snapshot' => $template->load('steps')->toSnapshot(),
        ]);
    }

    private function setRouting(bool $enabled): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'workstation_routing_enabled'], ['value' => json_encode($enabled)]);
    }

    private function batch(): Batch
    {
        return app(WorkOrderService::class)->createBatch($this->order, 1);
    }

    private function step(Batch $batch, int $number): BatchStep
    {
        return $batch->steps()->where('step_number', $number)->firstOrFail();
    }

    // ── cross-line routing ──────────────────────────────────────────────

    public function test_routed_bench_opens_the_panel_of_an_order_from_another_line(): void
    {
        $this->actingAs($this->glueAccount)
            ->getJson("/operator/work-order/{$this->order->id}/panel")
            ->assertOk()
            ->assertJsonPath('work_order.id', $this->order->id);
    }

    public function test_routed_bench_opens_the_order_page(): void
    {
        $this->withoutVite();

        $this->actingAs($this->glueAccount)
            ->get("/operator/work-order/{$this->order->id}")
            ->assertOk();
    }

    public function test_routed_bench_creates_a_batch_and_starts_its_own_step(): void
    {
        $this->actingAs($this->glueAccount)
            ->post('/operator/batch', ['work_order_id' => $this->order->id, 'target_qty' => 1, 'lot_number' => 'T001-GN-U'])
            ->assertRedirect(route('operator.work-order.detail', $this->order))
            ->assertSessionHasNoErrors();

        $batch = $this->order->batches()->firstOrFail();
        $this->assertSame('T001-GN-U', $batch->lot_number);

        $glue = $this->step($batch, 1);
        $this->actingAs($this->glueAccount)
            ->post("/operator/batch-step/{$glue->id}/start")
            ->assertSessionMissing('error');

        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $glue->fresh()->status);
    }

    public function test_routed_bench_cannot_act_on_steps_of_other_stations(): void
    {
        $batch = $this->batch();
        $wind = $this->step($batch, 3);

        $this->actingAs($this->glueAccount)
            ->post("/operator/batch-step/{$wind->id}/start")
            ->assertSessionHas('error', __('This step does not belong to the selected line.'));

        $this->assertNotSame(BatchStep::STATUS_IN_PROGRESS, $wind->fresh()->status);
    }

    public function test_without_routing_the_order_stays_on_its_own_line(): void
    {
        $this->setRouting(false);

        $this->actingAs($this->glueAccount)
            ->getJson("/operator/work-order/{$this->order->id}/panel")
            ->assertForbidden();

        $this->actingAs($this->glueAccount)
            ->post('/operator/batch', ['work_order_id' => $this->order->id, 'target_qty' => 1])
            ->assertSessionHas('error');
        $this->assertSame(0, $this->order->batches()->count());
    }

    public function test_an_order_not_routed_through_the_bench_is_refused(): void
    {
        $other = WorkOrder::factory()->create(['line_id' => $this->winding->id, 'status' => WorkOrder::STATUS_ACCEPTED]);

        $this->actingAs($this->glueAccount)
            ->getJson("/operator/work-order/{$other->id}/panel")
            ->assertForbidden();
    }

    public function test_picking_a_bench_of_a_line_the_operator_is_not_assigned_to_opens_nothing(): void
    {
        $elsewhere = Line::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->lines()->attach($elsewhere->id);

        $this->actingAs($operator)
            ->withSession(['selected_line_id' => $elsewhere->id, 'selected_workstation_id' => $this->glueBench->id])
            ->getJson("/operator/work-order/{$this->order->id}/panel")
            ->assertForbidden();
    }

    public function test_operator_assigned_to_the_bench_line_reaches_the_routed_order(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->lines()->attach($this->insulation->id);

        $this->actingAs($operator)
            ->withSession(['selected_line_id' => $this->insulation->id, 'selected_workstation_id' => $this->glueBench->id])
            ->getJson("/operator/work-order/{$this->order->id}/panel")
            ->assertOk();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get("/operator/work-order/{$this->order->id}/panel")->assertRedirect('/login');
    }

    // ── skipping optional steps ─────────────────────────────────────────

    public function test_panel_flags_which_steps_can_be_skipped(): void
    {
        $this->batch();

        $flags = $this->actingAs($this->glueAccount)
            ->getJson("/operator/work-order/{$this->order->id}/panel")
            ->assertOk()
            ->json('steps.*.can_skip');

        $this->assertSame([false, true, false, true], $flags);
    }

    public function test_routed_bench_skips_its_own_optional_step_with_a_reason(): void
    {
        $batch = $this->batch();
        $inserts = $this->step($batch, 2);

        $this->actingAs($this->glueAccount)
            ->post("/operator/batch-step/{$inserts->id}/skip", ['skip_reason' => 'Inserts fitted by the supplier'])
            ->assertSessionHas('success', __('Step skipped.'));

        $inserts->refresh();
        $this->assertSame(BatchStep::STATUS_SKIPPED, $inserts->status);
        $this->assertSame('Inserts fitted by the supplier', $inserts->skip_reason);
    }

    public function test_routed_bench_cannot_skip_an_optional_step_of_another_station(): void
    {
        $batch = $this->batch();
        $fibre = $this->step($batch, 4);

        $this->actingAs($this->glueAccount)
            ->post("/operator/batch-step/{$fibre->id}/skip")
            ->assertSessionHas('error');

        $this->assertNotSame(BatchStep::STATUS_SKIPPED, $fibre->fresh()->status);
    }

    public function test_skip_reason_longer_than_255_characters_is_rejected(): void
    {
        $batch = $this->batch();
        $inserts = $this->step($batch, 2);

        $this->actingAs($this->glueAccount)
            ->post("/operator/batch-step/{$inserts->id}/skip", ['skip_reason' => str_repeat('x', 256)])
            ->assertSessionHasErrors('skip_reason');

        $this->assertNotSame(BatchStep::STATUS_SKIPPED, $inserts->fresh()->status);
    }

    public function test_required_step_cannot_be_skipped(): void
    {
        $batch = $this->batch();
        $glue = $this->step($batch, 1);

        $this->actingAs($this->glueAccount)
            ->post("/operator/batch-step/{$glue->id}/skip")
            ->assertSessionHas('error', __('This step is required and cannot be skipped.'));

        $this->assertNotSame(BatchStep::STATUS_SKIPPED, $glue->fresh()->status);
    }
}
