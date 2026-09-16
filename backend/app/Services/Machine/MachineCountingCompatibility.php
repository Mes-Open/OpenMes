<?php

namespace App\Services\Machine;

use App\Models\MachineTag;
use App\Models\TopicMapping;
use App\Models\WorkOrder;

/** Global flow setting, per-channel migration: legacy channels cannot write a transfer ledger. */
class MachineCountingCompatibility
{
    public function legacySources(?int $lineId = null): array
    {
        $tags = MachineTag::where('is_active', true)
            ->whereIn('signal_type', ['good_count', 'reject_count', 'cycle_complete'])
            ->whereHas('connection', fn ($q) => $q->where('is_active', true))
            ->whereDoesntHave('counter', fn ($q) => $q->whereNotNull('configured_at'))
            ->when($lineId, fn ($q) => $q->whereHas('workstation', fn ($w) => $w->where('line_id', $lineId)))
            ->get()->map(fn ($tag) => 'Tag #'.$tag->id.' '.$tag->name)->all();

        // Legacy MQTT payloads may override connection line/order hints. Conservatively include
        // these channels until migrated; the global flow switch affects every production line.
        $mappings = TopicMapping::where('is_active', true)
            ->whereIn('action_type', [TopicMapping::ACTION_COUNT_STEP, TopicMapping::ACTION_UPDATE_WORK_ORDER_QTY])
            ->whereHas('topic', fn ($q) => $q->where('is_active', true)->whereHas('machineConnection', fn ($c) => $c->where('is_active', true)))
            ->whereDoesntHave('counter', fn ($q) => $q->whereNotNull('configured_at'))
            ->get()->map(fn ($mapping) => 'MQTT #'.$mapping->id.' '.$mapping->description)->all();

        return array_merge($tags, $mappings);
    }

    public function transitionBlockers(string $mode): array
    {
        if (! in_array($mode, \App\Support\ProductionFlow::MODES, true)) {
            return [__('Invalid production flow mode.')];
        }
        if ($mode === \App\Support\ProductionFlow::TRANSFER) {
            return $this->transferBlockers();
        }
        if (! \App\Support\ProductionFlow::isTransfer()) {
            return [];
        }

        // Completed history does not prevent future whole-batch work. Open routed
        // work must close first, including batches that have not logged output yet.
        return WorkOrder::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->get()->filter(fn ($order) => $order->usesStepLedger())
            ->map(fn ($order) => __('Finish routed order #:id before switching to whole-batch flow.', ['id' => $order->id]))->values()->all();
    }

    public function transferBlockers(): array
    {
        // A system setting affects all tenants, even when the administrator has a tenant scope.
        $orders = WorkOrder::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->whereIn('counting_source', ['machine', 'both'])
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)->get();
        if ($orders->isEmpty()) {
            return [];
        }
        // Report only source IDs for this system-wide compatibility check.
        $sources = [];
        foreach ($orders as $order) {
            // Explicit unscoped queries below are preferable to switching a logged-in user's scope.
            $sources = array_merge($sources, $this->legacySourcesForTenant($order->tenant_id, $order->line_id));
            if (! \App\Support\ProductionFlow::isTransfer() && (float) $order->produced_qty > 0) {
                $ledgerOutput = $order->batches()->where('status', '!=', \App\Models\Batch::STATUS_CANCELLED)->get()
                    ->sum(fn ($batch) => (float) ($batch->lastEffectiveStep()?->passed_qty ?? 0));
                if (abs($ledgerOutput - (float) $order->produced_qty) > 0.001) {
                    $sources[] = __('Finish or reconcile machine order #:id before switching its output to the step ledger.', ['id' => $order->id]);
                }
            }
        }

        return array_values(array_unique($sources));
    }

    private function legacySourcesForTenant(?int $tenantId, ?int $lineId): array
    {
        // Existing tenant scope is respected for normal UI reads; the system-wide switch must
        // also detect sources belonging to other tenants without exposing their names.
        $ids = \App\Models\MachineConnection::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)->where('is_active', true)->pluck('id');
        $tags = $lineId === null ? [] : MachineTag::whereIn('machine_connection_id', $ids)->where('is_active', true)
            ->whereIn('signal_type', ['good_count', 'reject_count', 'cycle_complete'])
            ->whereHas('workstation', fn ($q) => $q->where('line_id', $lineId))
            ->whereDoesntHave('counter', fn ($q) => $q->whereNotNull('configured_at'))->pluck('id')->map(fn ($id) => 'Tag #'.$id)->all();
        $mappings = TopicMapping::where('is_active', true)->whereIn('action_type', [TopicMapping::ACTION_COUNT_STEP, TopicMapping::ACTION_UPDATE_WORK_ORDER_QTY])
            ->whereHas('topic', fn ($q) => $q->where('is_active', true)->whereIn('machine_connection_id', $ids))
            ->whereDoesntHave('counter', fn ($q) => $q->whereNotNull('configured_at'))->pluck('id')->map(fn ($id) => 'MQTT #'.$id)->all();

        return array_merge($tags, $mappings);
    }
}
