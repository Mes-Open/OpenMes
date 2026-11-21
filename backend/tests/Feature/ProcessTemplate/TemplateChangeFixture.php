<?php

namespace Tests\Feature\ProcessTemplate;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TemplateStepChecklistItem;
use App\Models\TemplateStepOutput;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * One template (Mix with a required checklist item and output, then Fill), an
 * order in progress (Mix started) and an order not started yet, both released
 * from the template.
 */
trait TemplateChangeFixture
{
    protected User $admin;

    protected User $operator;

    protected Line $line;

    protected ProductType $productType;

    protected ProcessTemplate $template;

    protected TemplateStep $mix;

    protected TemplateStep $fill;

    protected TemplateStepChecklistItem $item;

    protected TemplateStepOutput $weight;

    protected WorkOrder $running;

    protected BatchStep $runningStep;

    protected WorkOrder $notStarted;

    protected string $base;

    protected function buildTemplateChangeFixture(): void
    {

        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        Permission::findOrCreate('view work orders', 'web');
        Role::findByName('Admin', 'web')->givePermissionTo('view work orders');
        Role::findByName('Operator', 'web')->givePermissionTo('view work orders');

        $this->line = Line::factory()->create();
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));
        $this->operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));
        $this->operator->lines()->attach($this->line->id);

        $this->productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create([
            'product_type_id' => $this->productType->id, 'is_active' => true,
        ]);
        $this->mix = TemplateStep::factory()->create([
            'process_template_id' => $this->template->id, 'step_number' => 1, 'name' => 'Mix',
        ]);
        $this->fill = TemplateStep::factory()->create([
            'process_template_id' => $this->template->id, 'step_number' => 2, 'name' => 'Fill',
        ]);
        $this->item = TemplateStepChecklistItem::factory()->create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->mix->id,
            'label' => 'Line clearance', 'is_required' => true,
        ]);
        $this->weight = TemplateStepOutput::create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->mix->id,
            'key' => 'weight', 'label' => 'Weight', 'value_type' => TemplateStepOutput::TYPE_NUMBER, 'is_required' => true,
        ]);

        $this->running = $this->order('WO-RUN');
        $batch = app(WorkOrderService::class)->createBatch($this->running, 10);
        $this->runningStep = $batch->steps()->where('step_number', 1)->first();
        $this->runningStep->update(['status' => BatchStep::STATUS_IN_PROGRESS, 'started_at' => now()]);
        $batch->update(['status' => Batch::STATUS_IN_PROGRESS]);
        $this->running->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        $this->notStarted = $this->order('WO-NEW');
        app(WorkOrderService::class)->createBatch($this->notStarted, 10);

        $this->base = "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";
    }

    protected function order(string $no): WorkOrder
    {
        return app(WorkOrderService::class)->createWorkOrder([
            'order_no' => $no,
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'planned_qty' => 10,
        ]);
    }

    protected function snapshotStep(WorkOrder $order, int $number): array
    {
        return collect($order->fresh()->process_snapshot['steps'])->firstWhere('step_number', $number);
    }

    protected function asOperator()
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    protected function pendingBatchStep(WorkOrder $order, int $number): BatchStep
    {
        return $order->fresh()->batches()->first()->steps()->where('step_number', $number)->firstOrFail();
    }
}
