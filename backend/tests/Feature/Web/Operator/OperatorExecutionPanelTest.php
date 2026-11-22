<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepChecklistCompletion;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Shift;
use App\Models\TemplateStep;
use App\Models\TemplateStepChecklistItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The operator shell's execution panel (the selected order's steps, current
 * step instructions and checklist, read as JSON beside the queue and
 * workstation lists) and the shift strip's figures.
 */
class OperatorExecutionPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    private ProcessTemplate $template;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->line = Line::factory()->create();
        $productType = ProductType::factory()->create(['name' => 'Printed Pillow Cover']);
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $productType->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_qty' => 20,
            'produced_qty' => 8,
            'process_snapshot' => ['template_id' => $this->template->id],
        ]);
    }

    private function asOperator()
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    public function test_panel_returns_the_batch_steps_with_instructions_and_checklist_state(): void
    {
        $templateStep = TemplateStep::factory()->create(['process_template_id' => $this->template->id, 'step_number' => 2, 'name' => 'Heat press']);
        $ticked = TemplateStepChecklistItem::factory()->create(['process_template_id' => $this->template->id, 'template_step_id' => $templateStep->id, 'label' => 'Transfer on jig marks']);
        TemplateStepChecklistItem::factory()->create(['process_template_id' => $this->template->id, 'template_step_id' => $templateStep->id, 'label' => 'Teflon sheet in place']);

        $batch = Batch::factory()->create(['work_order_id' => $this->workOrder->id, 'batch_number' => 2, 'status' => Batch::STATUS_IN_PROGRESS]);
        BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'name' => 'Artwork check', 'status' => BatchStep::STATUS_DONE]);
        $current = BatchStep::factory()->create([
            'batch_id' => $batch->id, 'step_number' => 2, 'name' => 'Heat press',
            'instruction' => 'Press 45 s at 180 °C.', 'requires_confirmation' => true,
            'status' => BatchStep::STATUS_IN_PROGRESS,
        ]);
        BatchStepChecklistCompletion::create(['batch_step_id' => $current->id, 'checklist_item_id' => $ticked->id, 'checked_by_id' => $this->operator->id, 'checked_at' => now()]);

        $this->asOperator()
            ->getJson(route('operator.work-order.panel', $this->workOrder))
            ->assertOk()
            ->assertJsonPath('work_order.order_no', $this->workOrder->order_no)
            ->assertJsonPath('work_order.product', 'Printed Pillow Cover')
            ->assertJsonPath('batch.batch_number', 2)
            ->assertJsonCount(2, 'steps')
            ->assertJsonPath('steps.0.status', BatchStep::STATUS_DONE)
            ->assertJsonPath('steps.1.instruction', 'Press 45 s at 180 °C.')
            ->assertJsonPath('steps.1.requires_confirmation', true)
            ->assertJsonPath('steps.1.confirmed_at', null)
            ->assertJsonCount(2, 'steps.1.checklist')
            ->assertJsonPath('steps.1.checklist.0.label', 'Transfer on jig marks')
            ->assertJsonPath('steps.1.checklist.0.done', true)
            ->assertJsonPath('steps.1.checklist.1.done', false);
    }

    public function test_panel_prefers_the_batch_open_at_the_selected_workstation(): void
    {
        $press = Workstation::factory()->create(['line_id' => $this->line->id]);
        $other = Workstation::factory()->create(['line_id' => $this->line->id]);
        $first = Batch::factory()->create(['work_order_id' => $this->workOrder->id, 'batch_number' => 1, 'status' => Batch::STATUS_IN_PROGRESS]);
        BatchStep::factory()->create(['batch_id' => $first->id, 'step_number' => 1, 'workstation_id' => $other->id, 'status' => BatchStep::STATUS_IN_PROGRESS]);
        $second = Batch::factory()->create(['work_order_id' => $this->workOrder->id, 'batch_number' => 2, 'status' => Batch::STATUS_IN_PROGRESS]);
        BatchStep::factory()->create(['batch_id' => $second->id, 'step_number' => 1, 'workstation_id' => $press->id, 'status' => BatchStep::STATUS_READY]);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id, 'selected_workstation_id' => $press->id])
            ->getJson(route('operator.work-order.panel', $this->workOrder))
            ->assertOk()
            ->assertJsonPath('batch.batch_number', 2)
            ->assertJsonPath('steps.0.workstation.id', $press->id);
    }

    public function test_panel_of_an_order_without_batches_has_no_steps(): void
    {
        $this->asOperator()
            ->getJson(route('operator.work-order.panel', $this->workOrder))
            ->assertOk()
            ->assertJsonPath('batch', null)
            ->assertJsonCount(0, 'steps')
            ->assertJsonPath('work_order.batch_count', 0);
    }

    public function test_panel_refuses_an_order_on_another_line(): void
    {
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => Line::factory()->create()->id])
            ->getJson(route('operator.work-order.panel', $this->workOrder))
            ->assertForbidden();
    }

    public function test_guest_cannot_read_the_panel(): void
    {
        $this->get(route('operator.work-order.panel', $this->workOrder))->assertRedirect(route('login'));
    }

    public function test_user_without_an_operator_role_cannot_read_the_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['selected_line_id' => $this->line->id])
            ->getJson(route('operator.work-order.panel', $this->workOrder))
            ->assertForbidden();
    }

    public function test_queue_and_workstation_carry_the_shift_strip_figures(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
        $shift = Shift::create(['name' => 'Morning', 'code' => 'S1', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'line_id' => $this->line->id, 'is_active' => true, 'sort_order' => 1]);
        WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_BLOCKED]);
        WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_PENDING, 'due_date' => '2026-03-10 15:00:00']);
        Workstation::factory()->count(3)->create(['line_id' => $this->line->id, 'is_active' => true]);
        WorkOrderShiftEntry::create(['work_order_id' => $this->workOrder->id, 'shift_id' => $shift->id, 'quantity' => 14, 'user_id' => $this->operator->id, 'production_date' => '2026-03-10']);
        // Another day's entry for the same shift stays out of this shift's count.
        WorkOrderShiftEntry::create(['work_order_id' => $this->workOrder->id, 'shift_id' => $shift->id, 'quantity' => 50, 'user_id' => $this->operator->id, 'production_date' => '2026-03-09']);

        foreach (['operator.queue' => 'operator/Queue', 'operator.workstation' => 'operator/Workstation'] as $route => $component) {
            $this->asOperator()
                ->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('shiftSummary.shift.code', 'S1')
                    ->where('shiftSummary.in_progress', 1)
                    ->where('shiftSummary.blocked', 1)
                    ->where('shiftSummary.due_today', 1)
                    ->where('shiftSummary.workstations', 3)
                    ->where('shiftSummary.made_this_shift', fn ($v) => (float) $v === 14.0)
                );
        }

        Carbon::setTestNow();
    }

    public function test_shift_strip_has_no_made_count_without_a_configured_shift(): void
    {
        $this->asOperator()
            ->get(route('operator.queue'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shiftSummary.shift.code', null)
                ->where('shiftSummary.made_this_shift', null)
            );
    }
}
