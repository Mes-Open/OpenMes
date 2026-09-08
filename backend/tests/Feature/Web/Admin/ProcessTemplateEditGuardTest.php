<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AuditLog;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Editing a ProcessTemplate's steps mutates rows in place (running orders keep
 * their frozen snapshot, correctly). This guards traceability: an in-use count
 * is surfaced to warn the admin, and every step change is written to the
 * immutable audit log so the previous shape is never silently lost.
 */
class ProcessTemplateEditGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductType $productType;

    private ProcessTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));
        $this->productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id]);
    }

    private function step(int $number = 1, string $name = 'Assemble'): TemplateStep
    {
        return TemplateStep::factory()->create([
            'process_template_id' => $this->template->id,
            'step_number' => $number,
            'name' => $name,
        ]);
    }

    private function base(): string
    {
        return "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";
    }

    private function orderForTemplate(string $status): WorkOrder
    {
        return WorkOrder::factory()->create([
            'process_snapshot' => ['template_id' => $this->template->id],
            'status' => $status,
        ]);
    }

    public function test_active_work_order_count_counts_non_terminal_orders_only(): void
    {
        $this->orderForTemplate(WorkOrder::STATUS_IN_PROGRESS);
        $this->orderForTemplate(WorkOrder::STATUS_PENDING);
        $this->orderForTemplate(WorkOrder::STATUS_DONE);       // terminal — ignored
        $this->orderForTemplate(WorkOrder::STATUS_CANCELLED);  // terminal — ignored

        $this->assertSame(2, $this->template->fresh()->activeWorkOrderCount());
    }

    public function test_show_exposes_the_active_work_order_count(): void
    {
        $this->step();
        $this->orderForTemplate(WorkOrder::STATUS_IN_PROGRESS);

        $this->actingAs($this->admin)
            ->get("{$this->base()}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('admin/process-templates/Show')
                ->where('processTemplate.active_work_order_count', 1));
    }

    public function test_adding_a_step_is_recorded_in_the_audit_log(): void
    {
        $this->actingAs($this->admin)->post("{$this->base()}/steps", ['name' => 'New step'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => ProcessTemplate::class,
            'entity_id' => $this->template->id,
            'action' => 'template_step.added',
        ]);
    }

    public function test_updating_a_step_records_before_and_after(): void
    {
        $step = $this->step(1, 'Original');

        $this->actingAs($this->admin)->put("{$this->base()}/steps/{$step->id}", ['name' => 'Renamed'])
            ->assertRedirect();

        $log = AuditLog::where('action', 'template_step.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Original', $log->before_state['name']);
        $this->assertSame('Renamed', $log->after_state['name']);
    }

    public function test_deleting_a_step_is_recorded_with_the_prior_shape(): void
    {
        $step = $this->step(1, 'Doomed');

        $this->actingAs($this->admin)->delete("{$this->base()}/steps/{$step->id}")
            ->assertRedirect();

        $log = AuditLog::where('action', 'template_step.deleted')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Doomed', $log->before_state['name']);
        $this->assertNull($log->after_state);
    }

    public function test_reordering_steps_is_recorded(): void
    {
        $a = $this->step(1, 'A');
        $b = $this->step(2, 'B');

        $this->actingAs($this->admin)->post("{$this->base()}/steps/reorder", ['order' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => ProcessTemplate::class,
            'entity_id' => $this->template->id,
            'action' => 'template_steps.reordered',
        ]);
    }
}
