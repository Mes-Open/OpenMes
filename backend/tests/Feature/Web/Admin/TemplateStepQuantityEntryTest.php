<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A template step's quantity entry — good / scrap counts typed in, or one
 * large +1 per piece — set in the process template, frozen into the order's
 * snapshot and carried onto its batch steps, where the operator's order page
 * reads it.
 */
class TemplateStepQuantityEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductType $productType;

    private ProcessTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id, 'is_active' => true]);
    }

    private function stepsUrl(): string
    {
        return "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}/steps";
    }

    public function test_a_new_step_records_quantities_unless_told_otherwise(): void
    {
        $this->actingAs($this->admin)->post($this->stepsUrl(), ['name' => 'Assembly'])->assertRedirect();

        $this->assertDatabaseHas('template_steps', ['name' => 'Assembly', 'quantity_entry' => TemplateStep::QUANTITY_ENTRY_QUANTITIES]);
    }

    public function test_an_admin_sets_a_step_to_one_piece_at_a_time(): void
    {
        $this->actingAs($this->admin)->post($this->stepsUrl(), ['name' => 'Tester', 'quantity_entry' => 'piece'])->assertRedirect();
        $step = TemplateStep::where('name', 'Tester')->firstOrFail();
        $this->assertSame(TemplateStep::QUANTITY_ENTRY_PIECE, $step->quantity_entry);

        $this->actingAs($this->admin)->put($this->stepsUrl()."/{$step->id}", ['name' => 'Tester', 'quantity_entry' => 'quantities'])->assertRedirect();
        $this->assertSame(TemplateStep::QUANTITY_ENTRY_QUANTITIES, $step->fresh()->quantity_entry);
    }

    public function test_an_unknown_choice_is_rejected(): void
    {
        $this->actingAs($this->admin)->post($this->stepsUrl(), ['name' => 'Tester', 'quantity_entry' => 'keypad'])
            ->assertSessionHasErrors('quantity_entry');
        $this->assertDatabaseMissing('template_steps', ['name' => 'Tester']);

        $step = TemplateStep::factory()->create(['process_template_id' => $this->template->id]);
        $this->withHeader('Authorization', 'Bearer '.$this->admin->createToken('t')->plainTextToken)
            ->patchJson("/api/v1/template-steps/{$step->id}", ['quantity_entry' => 'keypad'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_entry');
    }

    public function test_only_an_admin_may_change_it(): void
    {
        $this->post($this->stepsUrl(), ['name' => 'Tester', 'quantity_entry' => 'piece'])->assertRedirect(route('login'));

        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $this->actingAs($operator)->post($this->stepsUrl(), ['name' => 'Tester', 'quantity_entry' => 'piece'])->assertForbidden();

        $this->assertDatabaseMissing('template_steps', ['name' => 'Tester']);
    }

    public function test_the_choice_travels_through_the_snapshot_onto_the_batch_steps_and_the_order_page(): void
    {
        TemplateStep::factory()->create(['process_template_id' => $this->template->id, 'step_number' => 1, 'name' => 'Assembly']);
        TemplateStep::factory()->create(['process_template_id' => $this->template->id, 'step_number' => 2, 'name' => 'Tester', 'quantity_entry' => 'piece']);
        $snapshot = $this->template->load('steps')->toSnapshot();

        $byNumber = collect($snapshot['steps'])->keyBy('step_number');
        $this->assertSame('quantities', $byNumber[1]['quantity_entry']);
        $this->assertSame('piece', $byNumber[2]['quantity_entry']);

        $line = Line::factory()->create();
        $order = WorkOrder::factory()->create(['line_id' => $line->id, 'process_snapshot' => $snapshot]);
        $steps = app(WorkOrderService::class)->createBatch($order, 10)->steps()->orderBy('step_number')->get();
        $this->assertSame(['quantities', 'piece'], $steps->pluck('quantity_entry')->all());

        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->lines()->attach($line);
        $this->actingAs($operator)
            ->withSession(['selected_line_id' => $line->id])
            ->get(route('operator.work-order.detail', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workOrder.batches.0.steps.0.quantity_entry', 'quantities')
                ->where('workOrder.batches.0.steps.1.quantity_entry', 'piece')
            );
    }

    public function test_an_order_snapshot_from_before_the_field_existed_still_records_quantities(): void
    {
        $order = WorkOrder::factory()->create(['process_snapshot' => ['steps' => [['step_number' => 1, 'name' => 'Legacy']]]]);

        $step = app(WorkOrderService::class)->createBatch($order, 5)->steps()->first();

        $this->assertSame(TemplateStep::QUANTITY_ENTRY_QUANTITIES, $step->quantity_entry);
    }
}
