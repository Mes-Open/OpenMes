<?php

namespace App\Services\WorkOrder;

use App\Models\BatchStep;
use App\Models\WorkOrder;
use App\Models\WorkOrderComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComponentWorkOrderService
{
    public function generate(WorkOrder $order, array $options = []): WorkOrder
    {
        return DB::transaction(function () use ($order, $options) {
            $root = WorkOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($root->component_plan !== null) {
                return $root; // Repeat import/generation uses the frozen plan.
            }
            if ($root->parent_work_order_id || $root->batches()->exists() || ! in_array($root->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED], true)) {
                throw ValidationException::withMessages(['generate_components' => __('Components can only be generated for an unstarted root order.')]);
            }
            $plan = app(ComponentPlanService::class)->plan($root->process_snapshot ?? [], (float) $root->planned_qty);
            $plan = app(ComponentStockService::class)->net($plan, array_merge($options, ['planned_start_at' => $root->planned_start_at]), true);
            if (! empty($options['component_preview_token']) && ! hash_equals($plan['preview_token'], $options['component_preview_token'])) {
                throw ValidationException::withMessages(['component_preview_token' => __('Component availability changed. Refresh the preview before creating the order.')]);
            }
            $root->component_plan = $plan;
            $root->process_snapshot = $this->executionSnapshot($root->process_snapshot);
            $root->save();
            $sequence = 0;
            $this->persist($root, $root, $plan['components'], $sequence);

            return $root->fresh();
        });
    }

    /** Shared update boundary for imports, admin and API; pending quantity changes retain old plans. */
    public function update(WorkOrder $order, array $data): WorkOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $locked = WorkOrder::whereKey($order->root_work_order_id ?: $order->id)->lockForUpdate()->firstOrFail();
            $order->refresh();
            if ($order->component_plan && isset($data['planned_qty']) && (float) $data['planned_qty'] !== (float) $order->planned_qty) {
                $family = WorkOrder::where(fn ($q) => $q->whereKey($order->id)->orWhere('root_work_order_id', $order->id))->get();
                if ($family->contains(fn ($member) => $member->batches()->exists() || (float) $member->produced_qty > 0)
                    || ! in_array($order->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED], true)) {
                    throw ValidationException::withMessages(['planned_qty' => __('Component quantities cannot change after batches have been created. Create a replacement order for additional demand.')]);
                }
                // Structural changes require a new order; a quantity amendment reuses the frozen BOM.
                foreach (['product_type_id', 'process_snapshot'] as $field) {
                    if (array_key_exists($field, $data) && $data[$field] != $order->$field) {
                        throw ValidationException::withMessages([$field => __('Create a replacement order to change the component structure.')]);
                    }
                }
                app(ComponentStockService::class)->release($order, true);
                $plan = app(ComponentPlanService::class)->resize($order->component_plan, (float) $data['planned_qty']);
                $plan = app(ComponentStockService::class)->net($plan, array_merge($order->component_plan['stock_options'] ?? [], ['planned_start_at' => $data['planned_start_at'] ?? $order->planned_start_at]), true);
                $family->where('id', '!=', $order->id)->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
                    ->each(fn (WorkOrder $child) => $child->update(['status' => WorkOrder::STATUS_CANCELLED]));
                $order->revisingComponentPlan = true;
                try {
                    $order->update(array_merge($data, ['component_plan' => $plan]));
                } finally {
                    $order->revisingComponentPlan = false;
                }
                $sequence = 0;
                $this->persist($order, $order, $plan['components'], $sequence);
            } else {
                $order->update($data);
            }

            return $order->fresh();
        });
    }

    /** Raw inputs belong to the consuming job; generated parts are dedicated WIP, not stock withdrawals. */
    private function executionSnapshot(array $snapshot): array
    {
        $snapshot['bom'] = array_values(array_filter($snapshot['bom'] ?? [], fn ($line) => ($line['component_kind'] ?? 'material') !== 'product_type' && ! ($line['is_manufactured'] ?? false)));
        $snapshot['component_execution'] = true;

        return $snapshot;
    }

    private function persist(WorkOrder $root, WorkOrder $parent, array $nodes, int &$sequence): void
    {
        foreach ($nodes as $node) {
            $child = null;
            if ($node['required_qty'] <= 0) {
                continue;
            }
            if ($node['snapshot'] !== null && $node['planned_qty'] > 0) {
                $spec = $node['specification'];
                $child = WorkOrder::create([
                    'order_no' => 'COMP-'.$root->id.'-V'.$root->component_plan['version'].'-'.str_pad((string) ++$sequence, 3, '0', STR_PAD_LEFT),
                    'parent_work_order_id' => $parent->id,
                    'root_work_order_id' => $root->id,
                    'customer_order_no' => $root->customer_order_no,
                    'product_type_id' => $node['snapshot']['product_type_id'],
                    'line_id' => $this->processLine($node['snapshot']),
                    'planned_qty' => $node['planned_qty'],
                    'produced_qty' => 0,
                    'priority' => $root->priority,
                    'due_date' => $parent->planned_start_at ?? $root->due_date,
                    'status' => WorkOrder::STATUS_PENDING,
                    'counting_source' => WorkOrder::COUNTING_OPERATOR,
                    'process_snapshot' => $this->executionSnapshot($node['snapshot']),
                    'description' => ($spec['material_code'] ?? '').' — '.($spec['material_name'] ?? ''),
                    'extra_data' => ['component_specification' => $spec],
                    'tenant_id' => $root->tenant_id,
                ]);
            }
            $component = WorkOrderComponent::create([
                'root_work_order_id' => $root->id,
                'parent_work_order_id' => $parent->id,
                'child_work_order_id' => $child?->id,
                'plan_version' => $root->component_plan['version'],
                'path' => $node['path'],
                'specification' => $node['specification'],
                'required_qty' => $node['required_qty'],
                'planned_qty' => $node['planned_qty'],
                'stock_qty' => $node['stock_qty'] ?? 0,
                'consuming_step_number' => $node['consuming_step_number'],
            ]);
            app(ComponentStockService::class)->reserve($component, $node, $parent);
            if ($child) {
                $this->persist($root, $child, $node['children'], $sequence);
            }
        }
    }

    private function processLine(array $snapshot): ?int
    {
        $stationIds = array_filter(array_column($snapshot['steps'], 'workstation_id'));
        $lines = \App\Models\Workstation::whereIn('id', $stationIds)->pluck('line_id')->filter()->unique();

        return $lines->count() === 1 ? (int) $lines->first() : null;
    }

    public function refreshParentReadiness(?WorkOrder $child): void
    {
        if ($child?->root_work_order_id) {
            foreach ($child->parentWorkOrder?->batches ?? [] as $batch) {
                $batch->promoteReadySteps();
            }
        }
    }

    public function ready(WorkOrder $order, ?int $stepNumber = null): bool
    {
        if (! $order->component_plan && ! $order->root_work_order_id) {
            return true;
        }
        if ($stepNumber !== null && in_array($order->status, array_merge(WorkOrder::TERMINAL_STATUSES, WorkOrder::HELD_STATUSES), true)) {
            return false;
        }
        if ($order->root_work_order_id) {
            $parent = $order->parentWorkOrder;
            while ($parent) {
                if (in_array($parent->status, array_merge(WorkOrder::TERMINAL_STATUSES, WorkOrder::HELD_STATUSES), true)) {
                    return false;
                }
                $parent = $parent->parentWorkOrder;
            }
        }
        $version = $order->component_plan['version'] ?? WorkOrder::find($order->root_work_order_id)?->component_plan['version'] ?? 1;
        $components = $order->components()->where('plan_version', $version)->where(fn ($q) => $q->whereNotNull('child_work_order_id')->orWhere('stock_qty', '>', 0))
            ->when($stepNumber !== null, fn ($q) => $q->where('consuming_step_number', '<=', $stepNumber))
            ->with('childWorkOrder')->get();

        return $components->every(fn (WorkOrderComponent $component) => $component->goodQuantity() + app(ComponentStockService::class)->covered($component) >= (float) $component->required_qty);
    }

    public function summary(WorkOrder $order): array
    {
        if (! $order->component_plan && ! $order->root_work_order_id) {
            return [];
        }

        return WorkOrderComponent::where('root_work_order_id', $order->root_work_order_id ?: $order->id)
            ->where('plan_version', $order->component_plan['version'] ?? WorkOrder::find($order->root_work_order_id)?->component_plan['version'] ?? 1)
            ->with(['childWorkOrder.batches.steps', 'parentWorkOrder'])->orderBy('id')->get()
            ->filter(fn ($component) => ! auth()->user() || auth()->user()->can('view', $component->childWorkOrder ?? $component->parentWorkOrder))
            ->map(function (WorkOrderComponent $component) {
                $child = $component->childWorkOrder;
                $good = $component->goodQuantity();
                $stock = app(ComponentStockService::class)->covered($component);
                $needed = $component->parentWorkOrder?->planned_start_at;
                $late = $child && $needed && $child->planned_end_at && $child->planned_end_at->gt($needed);

                return [
                    'id' => $component->id,
                    'path' => $component->path,
                    'parent_work_order_id' => $component->parent_work_order_id,
                    'child_work_order_id' => $component->child_work_order_id,
                    'order_no' => $child?->order_no,
                    'specification' => $component->specification,
                    'required_qty' => (float) $component->required_qty,
                    'planned_qty' => (float) $component->planned_qty,
                    'good_qty' => $good,
                    'stock_qty' => (float) $component->stock_qty,
                    'covered_stock_qty' => $stock,
                    'stock_reservations' => $component->reservations()->with('stock.warehouse')->get()->map(fn ($reservation) => [
                        'id' => $reservation->id, 'quantity' => (float) $reservation->quantity, 'status' => $reservation->status,
                        'warehouse_code' => $reservation->stock?->warehouse?->code, 'stock_document_id' => $reservation->stock_document_id,
                    ])->all(),
                    'needed_at' => $needed?->toIso8601String(),
                    'planned_completion_at' => $child?->planned_end_at?->toIso8601String(),
                    'schedule_status' => $late ? 'late' : (! $needed || ($child && ! $child->planned_end_at) ? 'unscheduled' : 'on_time'),
                    'scrap_qty' => $component->scrapQuantity(),
                    'remaining_qty' => max(0, (float) $component->required_qty - $good - $stock),
                    'status' => $child?->status,
                    'ready' => ($child || $component->stock_qty > 0) ? $good + $stock >= (float) $component->required_qty : null,
                    'operation' => $child?->batches->flatMap->steps->firstWhere('status', BatchStep::STATUS_IN_PROGRESS)?->name,
                ];
            })->values()->all();
    }
}
