<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\ScrapReason;
use App\Models\SerialUnit;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use App\Support\ProductionFlow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What the server refuses however the request was made — the screens hide or
 * cap these, a hand-made request must not get past them: a batch over what the
 * order still needs or on a closed order, half a piece of a counted product, a
 * unit action on another line's unit, and an edit that strands a running order.
 */
class ProductionGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->line = Line::factory()->create();
        $this->operator->lines()->attach($this->line->id);
    }

    /** An order on the operator's line, one plain step, product counted in `$unit`. */
    private function order(float $planned = 6, string $unit = 'pcs', string $status = WorkOrder::STATUS_IN_PROGRESS): WorkOrder
    {
        $pt = ProductType::factory()->create(['unit_of_measure' => $unit]);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Assemble']);

        return WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $pt->id,
            'planned_qty' => $planned,
            'status' => $status,
            'process_snapshot' => $template->load('steps')->toSnapshot(),
        ]);
    }

    private function asOperator(): static
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    private function postBatch(WorkOrder $wo, float $qty)
    {
        return $this->asOperator()->from("/operator/work-order/{$wo->id}")
            ->post('/operator/batch', ['work_order_id' => $wo->id, 'target_qty' => $qty]);
    }

    private function allowOverproduction(bool $on): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'allow_overproduction'], ['value' => json_encode($on), 'updated_at' => now()]);
    }

    // ── Batch quantity and closed orders ─────────────────────────────────────

    public function test_a_batch_over_what_the_order_still_needs_is_refused(): void
    {
        $wo = $this->order(6);
        app(WorkOrderService::class)->createBatch($wo, 4);

        $this->postBatch($wo, 3)->assertSessionHas('error');
        $this->assertSame(1, $wo->batches()->count());

        $this->postBatch($wo, 2)->assertSessionHas('success');
        $this->assertSame(2, $wo->batches()->count());
    }

    public function test_a_short_finished_batch_frees_the_rest_and_a_cancelled_one_frees_all(): void
    {
        $wo = $this->order(6);
        $done = app(WorkOrderService::class)->createBatch($wo, 6);
        $done->update(['status' => Batch::STATUS_DONE, 'produced_qty' => 3]);
        $service = app(WorkOrderService::class);

        $this->assertSame(3.0, $service->remainingBatchCapacity($wo));
        $this->postBatch($wo, 4)->assertSessionHas('error');

        $open = $service->createBatch($wo, 3);
        $this->assertSame(0.0, $service->remainingBatchCapacity($wo));
        $open->update(['status' => Batch::STATUS_CANCELLED]);
        $this->assertSame(3.0, $service->remainingBatchCapacity($wo));
    }

    public function test_overproduction_setting_lifts_the_cap(): void
    {
        $wo = $this->order(3);
        $this->allowOverproduction(true);

        $this->postBatch($wo, 50)->assertSessionHas('success');
        $this->assertSame(50.0, (float) $wo->batches()->value('target_qty'));
    }

    public function test_no_batch_on_a_closed_order(): void
    {
        foreach ([WorkOrder::STATUS_CANCELLED, WorkOrder::STATUS_DONE, WorkOrder::STATUS_REJECTED] as $status) {
            $wo = $this->order(6, 'pcs', $status);
            $this->allowOverproduction(true); // not what refuses it

            $this->postBatch($wo, 1)->assertSessionHas('error');
            $this->assertSame(0, $wo->batches()->count(), $status);
        }
    }

    public function test_the_api_applies_the_same_batch_rule(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $token = $admin->createToken('t')->plainTextToken;
        $wo = $this->order(3);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/work-orders/{$wo->id}/batches", ['target_qty' => 50])->assertStatus(422);
        $wo->update(['status' => WorkOrder::STATUS_CANCELLED]);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/work-orders/{$wo->id}/batches", ['target_qty' => 1])->assertStatus(422);
        $this->assertSame(0, $wo->batches()->count());
    }

    public function test_the_create_batch_modal_gets_the_server_capacity(): void
    {
        $wo = $this->order(6);
        app(WorkOrderService::class)->createBatch($wo, 4);

        $this->asOperator()->get("/operator/work-order/{$wo->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('workOrder.batch_capacity', 2)->where('workOrder.overproduction_allowed', false)->where('workOrder.whole_pieces', true));
    }

    // ── Whole pieces ─────────────────────────────────────────────────────────

    private function runningStep(WorkOrder $wo, float $qty): BatchStep
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = app(WorkOrderService::class)->createBatch($wo, $qty);
        $step = $batch->steps()->firstOrFail();

        return app(BatchService::class)->startStep($step, $this->operator);
    }

    public function test_a_product_counted_in_pieces_is_logged_in_whole_pieces(): void
    {
        $step = $this->runningStep($this->order(4), 4);

        $this->asOperator()->postJson("/operator/batch-step/{$step->id}/quantity", ['good_qty' => 1.5])
            ->assertStatus(422)->assertJsonValidationErrors('good_qty');
        $this->asOperator()->postJson("/operator/batch-step/{$step->id}/quantity", ['scrap_qty' => 0.5])
            ->assertStatus(422)->assertJsonValidationErrors('scrap_qty');
        $this->assertSame(0.0, (float) $step->fresh()->passed_qty);

        $this->postBatch($this->order(4), 2.5)->assertSessionHas('error');
    }

    public function test_a_product_measured_by_weight_keeps_its_fractions(): void
    {
        $wo = $this->order(10, 'kg');
        $step = $this->runningStep($wo, 4);

        $this->asOperator()->post("/operator/batch-step/{$step->id}/quantity", ['good_qty' => 1.5])->assertSessionHasNoErrors();
        $this->assertSame(1.5, (float) $step->fresh()->passed_qty);
        $this->postBatch($wo, 2.5)->assertSessionHas('success');
    }

    // ── Unit actions stay on the line ────────────────────────────────────────

    public function test_an_operator_cannot_act_on_a_unit_of_another_line(): void
    {
        $other = Line::factory()->create();
        $foreign = WorkOrder::factory()->create(['line_id' => $other->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $unit = SerialUnit::create(['serial_no' => 'SN-FOREIGN', 'work_order_id' => $foreign->id]);
        $reason = ScrapReason::factory()->create(['is_active' => true]);

        $this->asOperator()->postJson(route('operator.unit-labels.block', $unit), ['scrap_reason_id' => $reason->id, 'note' => 'x'])->assertForbidden();
        $this->asOperator()->postJson(route('operator.unit-labels.scrap', $unit), ['reason' => 'cracked'])->assertForbidden();
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $unit->fresh()->status);

        // No line selected at all is no line either.
        $this->actingAs(User::factory()->create()->assignRole('Operator'))
            ->postJson(route('operator.unit-labels.scrap', $unit), ['reason' => 'cracked'])->assertForbidden();

        // The station's list holds the line's own units only.
        $mine = SerialUnit::create(['serial_no' => 'SN-MINE', 'work_order_id' => $this->order()->id]);
        $this->asOperator()->getJson(route('operator.unit-labels.units'))->assertOk()
            ->assertJsonPath('units.0.serial_no', $mine->serial_no)->assertJsonCount(1, 'units');

        // On its own line the same action goes through.
        $this->asOperator()->postJson(route('operator.unit-labels.scrap', $mine), ['reason' => 'cracked'])->assertOk();
        $this->assertSame(SerialUnit::STATUS_SCRAPPED, $mine->fresh()->status);
    }

    public function test_a_guest_cannot_act_on_a_unit(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'SN-G', 'work_order_id' => $this->order()->id]);

        $this->postJson(route('operator.unit-labels.scrap', $unit), ['reason' => 'x'])->assertUnauthorized();
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $unit->fresh()->status);
    }

    // ── Edits that would strand a running order ──────────────────────────────

    private function editPayload(WorkOrder $wo, array $overrides = []): array
    {
        return $overrides + [
            'order_no' => $wo->order_no,
            'line_id' => $wo->line_id,
            'product_type_id' => $wo->product_type_id,
            'planned_qty' => $wo->planned_qty,
            'status' => $wo->status,
        ];
    }

    public function test_admin_edit_refuses_planned_below_produced_and_a_line_move_with_an_open_batch(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $wo = $this->order(6);
        $wo->update(['produced_qty' => 3]);
        app(WorkOrderService::class)->createBatch($wo, 3);
        $other = Line::factory()->create();

        $this->actingAs($admin)->from('/x')->put("/admin/work-orders/{$wo->id}", $this->editPayload($wo, ['planned_qty' => 2]))
            ->assertSessionHasErrors('planned_qty');
        $this->actingAs($admin)->from('/x')->put("/admin/work-orders/{$wo->id}", $this->editPayload($wo, ['line_id' => $other->id]))
            ->assertSessionHasErrors('line_id');
        $this->assertSame([6.0, $this->line->id], [(float) $wo->fresh()->planned_qty, $wo->fresh()->line_id]);

        // Planned down to what was produced is fine; with no open batch the order may move.
        $this->actingAs($admin)->from('/x')->put("/admin/work-orders/{$wo->id}", $this->editPayload($wo, ['planned_qty' => 3]))
            ->assertSessionHasNoErrors();
        $wo->batches()->update(['status' => Batch::STATUS_DONE]);
        $this->actingAs($admin)->from('/x')->put("/admin/work-orders/{$wo->id}", $this->editPayload($wo->fresh(), ['line_id' => $other->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($other->id, $wo->fresh()->line_id);
    }

    public function test_supervisor_edit_refuses_a_product_change_after_production_started(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $wo = $this->order(6);
        app(WorkOrderService::class)->createBatch($wo, 6);
        $otherProduct = ProductType::factory()->create();

        $this->actingAs($supervisor)->from('/x')->put("/supervisor/work-orders/{$wo->id}", $this->editPayload($wo, ['product_type_id' => $otherProduct->id]))
            ->assertSessionHasErrors('product_type_id');
        $this->assertSame($wo->product_type_id, $wo->fresh()->product_type_id);
    }
}
