<?php

namespace App\Services\WorkOrder;

use App\Enums\ChangeEffectivePoint;
use App\Enums\ChangeRequestStatus;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\ProductRevision;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The controlled change workflow (#182).
 *
 * A structural change to a running order — revision, quantity, line, BOM, schedule —
 * goes through a request that is reviewed and approved before it touches anything.
 * Applying it appends a new configuration version rather than editing the old one, so
 * what the shop floor was building before the change stays exactly as recorded.
 *
 * Everything already executed is off limits by construction: this service writes to
 * the work order and to not-yet-started steps, and never to completed batch steps,
 * consumed material, quality results or produced quantities.
 */
class ChangeRequestService
{
    /**
     * Statuses a change may be applied to.
     *
     * The stopped states are the point of the workflow. PENDING and ACCEPTED are here
     * because a change can legitimately be raised on an order that has not started
     * yet — there is no execution to protect, and without them such a request would
     * be approved and then permanently un-appliable (nothing can move a PENDING order
     * into a stopped state).
     */
    private const APPLICABLE_STATUSES = [
        WorkOrder::STATUS_PAUSED,
        WorkOrder::STATUS_BLOCKED,
        WorkOrder::STATUS_CHANGE_HOLD,
        WorkOrder::STATUS_PENDING,
        WorkOrder::STATUS_ACCEPTED,
    ];

    public function __construct(
        protected WorkOrderService $workOrders,
        protected WorkOrderSnapshotService $snapshots,
    ) {}

    /**
     * Raise a draft change request against an order.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \DomainException When the request proposes nothing, or the order is terminal.
     */
    public function create(WorkOrder $workOrder, array $data, User $user): WorkOrderChangeRequest
    {
        if (in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES, true)) {
            throw new \DomainException('Change requests cannot be raised against a finished work order.');
        }

        $proposed = $this->sanitizeProposed($data['proposed'] ?? []);

        if ($proposed === []) {
            throw new \DomainException('A change request must propose at least one change.');
        }

        // Two creates racing for the same sequence number: the unique index rejects
        // the loser, and it simply takes the next one. Bounded, because a persistent
        // failure is a real error and must not spin.
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->insert($workOrder, $data, $user, $proposed);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3 || ! $this->isDuplicateCode($e)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $proposed
     */
    private function insert(WorkOrder $workOrder, array $data, User $user, array $proposed): WorkOrderChangeRequest
    {
        return DB::transaction(function () use ($workOrder, $data, $user, $proposed) {
            $changeRequest = new WorkOrderChangeRequest([
                'code' => $this->generateCode($workOrder),
                'work_order_id' => $workOrder->id,
                'work_order_stop_id' => $data['work_order_stop_id'] ?? $workOrder->openStop()?->id,
                'title' => $data['title'],
                'reason' => $data['reason'],
                'status' => ChangeRequestStatus::Draft,
                'proposed' => $proposed,
                'impact' => $this->analyzeImpact($workOrder, $proposed),
                'effective_from' => $data['effective_from'] ?? ChangeEffectivePoint::NextBatch->value,
                'effective_from_batch_id' => $data['effective_from_batch_id'] ?? null,
                'produced_disposition' => $data['produced_disposition'] ?? null,
                'material_disposition' => $data['material_disposition'] ?? null,
                'requested_by_id' => $user->id,
                'tenant_id' => $workOrder->tenant_id,
            ]);

            $changeRequest->save();

            return $changeRequest->fresh();
        });
    }

    /**
     * Edit a draft. The impact is recomputed here because it is what the approver
     * will read, and a stale one would describe a change nobody is proposing.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \DomainException When the request is no longer a draft.
     */
    public function update(WorkOrderChangeRequest $changeRequest, array $data, User $user): WorkOrderChangeRequest
    {
        $this->assertEditable($changeRequest);

        $attributes = array_intersect_key($data, array_flip([
            'title', 'reason', 'effective_from', 'effective_from_batch_id',
            'produced_disposition', 'material_disposition',
        ]));

        if (array_key_exists('proposed', $data)) {
            $proposed = $this->sanitizeProposed($data['proposed']);

            if ($proposed === []) {
                throw new \DomainException('A change request must propose at least one change.');
            }

            $attributes['proposed'] = $proposed;
            $attributes['impact'] = $this->analyzeImpact($this->workOrderOf($changeRequest), $proposed);
        }

        $changeRequest->update($attributes);

        return $changeRequest->fresh();
    }

    /** Hand the request to a reviewer. */
    public function submit(WorkOrderChangeRequest $changeRequest, User $user): WorkOrderChangeRequest
    {
        $this->assertTransition($changeRequest, ChangeRequestStatus::Submitted);

        if (! $changeRequest->hasProposedChanges()) {
            throw new \DomainException('A change request must propose at least one change.');
        }

        // Refresh the impact at submission: batches ran and material moved while the
        // draft sat there, and the approver must review the current picture.
        $changeRequest->update([
            'status' => ChangeRequestStatus::Submitted,
            'impact' => $this->analyzeImpact($this->workOrderOf($changeRequest), $changeRequest->proposedChanges()),
            'submitted_at' => now(),
        ]);

        return $changeRequest->fresh();
    }

    public function approve(WorkOrderChangeRequest $changeRequest, User $user): WorkOrderChangeRequest
    {
        $this->assertTransition($changeRequest, ChangeRequestStatus::Approved);

        $changeRequest->update([
            'status' => ChangeRequestStatus::Approved,
            'approved_by_id' => $user->id,
            'approved_at' => now(),
        ]);

        return $changeRequest->fresh();
    }

    public function reject(WorkOrderChangeRequest $changeRequest, User $user, string $reason): WorkOrderChangeRequest
    {
        $this->assertTransition($changeRequest, ChangeRequestStatus::Rejected);

        $changeRequest->update([
            'status' => ChangeRequestStatus::Rejected,
            'rejected_by_id' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $changeRequest->fresh();
    }

    public function cancel(WorkOrderChangeRequest $changeRequest, User $user): WorkOrderChangeRequest
    {
        $this->assertTransition($changeRequest, ChangeRequestStatus::Cancelled);

        $changeRequest->update(['status' => ChangeRequestStatus::Cancelled]);

        return $changeRequest->fresh();
    }

    /**
     * Apply an approved change: freeze the before-state, rebuild the configuration,
     * append it as a new version and move the order onto it.
     *
     * The order must be stopped. Applying a structural change to an order that is
     * actively running would hand the shop floor a new configuration mid-step, which
     * is the failure mode this whole workflow exists to prevent.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \DomainException
     */
    public function apply(WorkOrderChangeRequest $changeRequest, User $user, array $data = []): WorkOrderChangeRequest
    {
        $this->assertTransition($changeRequest, ChangeRequestStatus::Applied);

        return DB::transaction(function () use ($changeRequest, $user, $data) {
            $workOrder = WorkOrder::whereKey($changeRequest->work_order_id)->lockForUpdate()->first();

            // Null when the order was deleted while the request sat in review. Refuse
            // as a domain rule, or the refresh() below would be a fatal error the
            // controllers cannot turn into a 422.
            if ($workOrder === null) {
                throw new \DomainException('The work order this change belongs to no longer exists.');
            }

            $workOrder->refresh();

            if (! in_array($workOrder->status, self::APPLICABLE_STATUSES, true)) {
                throw new \DomainException(
                    'A change can only be applied to a stopped work order, or to one that has not started production.'
                );
            }

            $changes = $changeRequest->proposedChanges();
            // The effective point may be confirmed or overridden at apply time;
            // without an override the request's own choice stands.
            $chosen = $data['effective_from'] ?? $changeRequest->effective_from;
            $effectiveFrom = $chosen instanceof ChangeEffectivePoint
                ? $chosen
                : ChangeEffectivePoint::from($chosen);

            $this->assertEffectivePointAllowed($workOrder, $effectiveFrom);
            $this->assertQuantityNotBelowProduced($workOrder, $changes);

            // The before-state, captured now rather than reconstructed later: this is
            // what makes the request readable as a diff for the rest of its life.
            // Point the request at the row we hold the lock on, so the before-state is
            // read from the same instance the change is about to be written to.
            $changeRequest->setRelation('workOrder', $workOrder);
            $previous = $this->capturePrevious($changeRequest, $changes);

            $snapshot = $this->buildSnapshot($workOrder, $changes);

            $this->applyToWorkOrder($workOrder, $changes);

            $version = $this->snapshots->createVersion(
                $workOrder,
                $snapshot,
                $effectiveFrom,
                $changeRequest,
                $user,
                $data['effective_from_batch_id'] ?? $changeRequest->effective_from_batch_id,
            );

            // Not-yet-started work is the only execution data a change may touch.
            $this->regenerateNotStartedSteps($workOrder, $effectiveFrom, $version->version);

            $changeRequest->update([
                'status' => ChangeRequestStatus::Applied,
                'previous_values' => $previous,
                'effective_from' => $effectiveFrom,
                'implementation_notes' => $data['implementation_notes'] ?? $changeRequest->implementation_notes,
                'applied_by_id' => $user->id,
                'applied_at' => now(),
                'resulting_snapshot_version' => $version->version,
                // Freeze what the remaining production now needs, so "requirements
                // recalculated" is an answer on the record rather than a recomputation
                // that would give a different number tomorrow.
                'impact' => array_merge($changeRequest->impact ?? [], [
                    'remaining_requirements' => $this->snapshots->remainingRequirements($workOrder, $snapshot),
                ]),
            ]);

            return $changeRequest->fresh();
        });
    }

    /**
     * What applying this request would mean for work already done — the analysis the
     * approver sees before deciding.
     *
     * @param  array<string, mixed>  $proposed
     * @return array<string, mixed>
     */
    public function analyzeImpact(WorkOrder $workOrder, array $proposed): array
    {
        $batches = $workOrder->batches()->get();
        $batchIds = $batches->pluck('id');

        $stepCounts = BatchStep::whereIn('batch_id', $batchIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $consumed = (float) $workOrder->materialAllocations()->sum('consumed_qty');
        $allocated = (float) $workOrder->materialAllocations()->sum('allocated_qty');

        $produced = (float) $workOrder->produced_qty;
        $planned = (float) $workOrder->planned_qty;

        return [
            'produced_qty' => $produced,
            'planned_qty' => $planned,
            'remaining_qty' => max(0.0, $planned - $produced),
            'batches' => [
                'completed' => $batches->where('status', Batch::STATUS_DONE)->count(),
                'active' => $batches->where('status', Batch::STATUS_IN_PROGRESS)->count(),
                'pending' => $batches->where('status', Batch::STATUS_PENDING)->count(),
            ],
            'steps' => [
                'completed' => (int) ($stepCounts[BatchStep::STATUS_DONE] ?? 0),
                'in_progress' => (int) ($stepCounts[BatchStep::STATUS_IN_PROGRESS] ?? 0),
                // Everything still ahead — the work a change is actually allowed to alter.
                'not_started' => (int) ($stepCounts[BatchStep::STATUS_PENDING] ?? 0)
                    + (int) ($stepCounts[BatchStep::STATUS_READY] ?? 0),
            ],
            'materials' => [
                'allocated_qty' => $allocated,
                'consumed_qty' => $consumed,
            ],
            'revision' => $this->revisionImpact($workOrder, $proposed),
            'documents' => $this->documentImpact($workOrder, $proposed),
            'warnings' => $this->warnings($workOrder, $proposed, $batches, $consumed),
        ];
    }

    /**
     * Conflicts between what is proposed and what has already happened. These do not
     * block on their own — some are legitimate and the approver decides — except
     * where apply() enforces them as hard rules.
     *
     * @param  array<string, mixed>  $proposed
     * @param  \Illuminate\Support\Collection<int, Batch>  $batches
     * @return array<int, string>
     */
    protected function warnings(WorkOrder $workOrder, array $proposed, $batches, float $consumed): array
    {
        $warnings = [];
        $produced = (float) $workOrder->produced_qty;

        if (array_key_exists('planned_qty', $proposed) && (float) $proposed['planned_qty'] < $produced) {
            $warnings[] = __('The proposed quantity is below the quantity already produced (:produced).', [
                'produced' => $produced,
            ]);
        }

        if (array_key_exists('product_revision_id', $proposed) && $produced > 0) {
            $warnings[] = __(':produced units were already produced under the current revision and keep it.', [
                'produced' => $produced,
            ]);
        }

        if ($batches->where('status', Batch::STATUS_IN_PROGRESS)->isNotEmpty()) {
            $warnings[] = __('A batch is still in progress and will finish on its current configuration.');
        }

        if (array_key_exists('bom_template_ids', $proposed) && $consumed > 0) {
            $warnings[] = __('Material has already been consumed; existing consumption is not recalculated.');
        }

        if (array_key_exists('line_id', $proposed) && $batches->where('status', Batch::STATUS_IN_PROGRESS)->isNotEmpty()) {
            $warnings[] = __('Moving the line while a batch is running may strand its workstation assignments.');
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $proposed
     * @return array<string, mixed>|null
     */
    protected function revisionImpact(WorkOrder $workOrder, array $proposed): ?array
    {
        if (! array_key_exists('product_revision_id', $proposed)) {
            return null;
        }

        $from = $workOrder->product_revision_id ? ProductRevision::find($workOrder->product_revision_id) : null;
        $to = $proposed['product_revision_id'] ? ProductRevision::find($proposed['product_revision_id']) : null;

        return [
            'from' => $from?->revision_code,
            'to' => $to?->revision_code,
            'from_id' => $from?->id,
            'to_id' => $to?->id,
        ];
    }

    /**
     * Which frozen engineering documents (#179) the new configuration would replace.
     *
     * @param  array<string, mixed>  $proposed
     * @return array<int, array<string, mixed>>
     */
    protected function documentImpact(WorkOrder $workOrder, array $proposed): array
    {
        if (! array_key_exists('product_revision_id', $proposed) && ! array_key_exists('bom_template_ids', $proposed)) {
            return [];
        }

        $current = collect($workOrder->process_snapshot['engineering_documents'] ?? [])->keyBy('document_id');
        $next = collect($this->buildSnapshot($workOrder, $proposed)['engineering_documents'] ?? [])->keyBy('document_id');

        return $current->keys()->merge($next->keys())->unique()
            ->map(fn ($id) => [
                'document_id' => $id,
                'from_revision' => $current->get($id)['revision'] ?? null,
                'to_revision' => $next->get($id)['revision'] ?? null,
            ])
            ->filter(fn (array $row) => $row['from_revision'] !== $row['to_revision'])
            ->values()
            ->all();
    }

    /**
     * Rebuild the frozen configuration the way release does, so a changed order ends
     * up with exactly the snapshot shape it would have had if it had been released
     * this way in the first place.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected function buildSnapshot(WorkOrder $workOrder, array $changes): array
    {
        $revisionId = array_key_exists('product_revision_id', $changes)
            ? $changes['product_revision_id']
            : $workOrder->product_revision_id;

        $templateIds = array_key_exists('bom_template_ids', $changes)
            ? (array) $changes['bom_template_ids']
            : $workOrder->bomTemplates()->pluck('process_templates.id')->all();

        // Nothing structural changed — carry the current configuration forward so the
        // new version still records the full picture (a snapshot is never a delta).
        if (! array_key_exists('product_revision_id', $changes) && ! array_key_exists('bom_template_ids', $changes)) {
            return $workOrder->process_snapshot ?? [];
        }

        $snapshot = $this->workOrders->rebuildProcessSnapshot(
            $workOrder->product_type_id,
            $templateIds,
            $revisionId,
        );

        return $snapshot ?? ($workOrder->process_snapshot ?? []);
    }

    /**
     * Write the approved fields onto the order. Only the fields a request may touch
     * reach this point — sanitizeProposed() dropped the rest.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function applyToWorkOrder(WorkOrder $workOrder, array $changes): void
    {
        $attributes = array_intersect_key($changes, array_flip([
            'product_revision_id', 'planned_qty', 'line_id', 'due_date', 'description',
        ]));

        // The order has no production_notes column; notes live in extra_data so the
        // field the issue asks for is still changeable under control.
        if (array_key_exists('production_notes', $changes)) {
            $attributes['extra_data'] = array_merge(
                $workOrder->extra_data ?? [],
                ['production_notes' => $changes['production_notes']],
            );
        }

        if ($attributes !== []) {
            $workOrder->update($attributes);
        }

        if (array_key_exists('bom_template_ids', $changes)) {
            $this->workOrders->syncBomSelection($workOrder, (array) $changes['bom_template_ids']);
        }

        $workOrder->refresh();
    }

    /**
     * Rebuild the steps of batches that have not started, from the new configuration.
     *
     * Batches with any executed step are left alone entirely: rewriting them is the
     * one thing a change request may never do. IMMEDIATE is only reachable when no
     * batch has started at all (see assertEffectivePointAllowed), so under every
     * effective point this only ever touches untouched work.
     */
    protected function regenerateNotStartedSteps(WorkOrder $workOrder, ChangeEffectivePoint $effectiveFrom, int $version): void
    {
        if ($effectiveFrom === ChangeEffectivePoint::NextBatch) {
            // Only batches created from now on carry the new configuration; the ones
            // already queued keep the version they were generated from.
            return;
        }

        $batches = $workOrder->batches()
            ->where('status', Batch::STATUS_PENDING)
            ->get()
            ->filter(fn (Batch $batch) => ! $this->hasExecution($batch));

        foreach ($batches as $batch) {
            // Per-model delete, not a builder mass-delete: BatchStep soft-deletes with
            // audit, and both the `deleted_by_id` stamp and the cascade to its
            // documents run in the model's deleted event, which a bulk UPDATE skips.
            foreach ($batch->steps as $step) {
                $step->delete();
            }

            $this->workOrders->regenerateBatchSteps($batch, $workOrder->process_snapshot ?? []);
            $batch->update(['snapshot_version' => $version]);
        }
    }

    /**
     * Whether anything on this batch has been executed, and is therefore untouchable.
     *
     * A step SKIPPED *by somebody* counts as executed: skipping records who, when and
     * why, and choosing a variant skips its siblings the same way — operator decisions
     * with their own audit trail. Such a batch can still sit at PENDING, so checking
     * the batch status alone would let regeneration erase them.
     *
     * Variant siblings skipped at generation time are NOT execution: they have no
     * `completed_by_id`, and treating them as executed would freeze every batch with a
     * variant group against any change.
     */
    protected function hasExecution(Batch $batch): bool
    {
        return (float) $batch->produced_qty > 0
            || $batch->steps()
                ->where(fn ($q) => $q
                    ->whereIn('status', [BatchStep::STATUS_IN_PROGRESS, BatchStep::STATUS_DONE])
                    ->orWhere(fn ($qq) => $qq
                        ->where('status', BatchStep::STATUS_SKIPPED)
                        ->whereNotNull('completed_by_id')
                    )
                )
                ->exists();
    }

    /**
     * IMMEDIATE rewrites the steps of open batches, so it is only allowed while
     * nothing has been executed. Anything else would hand the shop floor a
     * configuration that contradicts what it has already built.
     *
     * @throws \DomainException
     */
    protected function assertEffectivePointAllowed(WorkOrder $workOrder, ChangeEffectivePoint $effectiveFrom): void
    {
        if (! $effectiveFrom->touchesOpenBatches()) {
            return;
        }

        $executed = $workOrder->batches()
            ->whereIn('status', [Batch::STATUS_PENDING, Batch::STATUS_IN_PROGRESS])
            ->get()
            ->contains(fn (Batch $batch) => $this->hasExecution($batch));

        if ($executed) {
            throw new \DomainException(
                'IMMEDIATE is not allowed: production has already been executed under the current configuration. '
                .'Use NEXT_BATCH or REMAINING_QUANTITY.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws \DomainException
     */
    protected function assertQuantityNotBelowProduced(WorkOrder $workOrder, array $changes): void
    {
        if (! array_key_exists('planned_qty', $changes)) {
            return;
        }

        if ((float) $changes['planned_qty'] < (float) $workOrder->produced_qty) {
            throw new \DomainException(
                'The planned quantity cannot be set below the quantity already produced.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected function capturePrevious(WorkOrderChangeRequest $changeRequest, array $changes): array
    {
        $previous = [];

        // Reads through the request so the frozen before-state and the live diff can
        // never disagree about what a field's current value is.
        foreach (array_keys($changes) as $field) {
            $previous[$field] = $changeRequest->currentValueOf($field);
        }

        return $previous;
    }

    /**
     * Drop anything the request is not allowed to propose. Silent by design: the
     * allowlist is the contract, and an unknown key is a caller bug, not a change.
     *
     * @param  array<string, mixed>  $proposed
     * @return array<string, mixed>
     */
    protected function sanitizeProposed(array $proposed): array
    {
        return array_intersect_key($proposed, array_flip(WorkOrderChangeRequest::CHANGEABLE_FIELDS));
    }

    /**
     * Whether a failed insert was the change-request code colliding, and nothing else.
     *
     * Matches on the index name rather than a driver error code, so an unrelated
     * unique conflict is never silently retried as if it were a code clash.
     */
    private function isDuplicateCode(UniqueConstraintViolationException $e): bool
    {
        return str_contains($e->getMessage(), 'work_order_change_requests_code_unique');
    }

    /**
     * The order a request belongs to, refusing as a domain rule when it has been
     * deleted — otherwise a null would reach analyzeImpact() as a TypeError, which the
     * controllers cannot turn into a 422.
     *
     * @throws \DomainException
     */
    protected function workOrderOf(WorkOrderChangeRequest $changeRequest): WorkOrder
    {
        $workOrder = $changeRequest->workOrder;

        if ($workOrder === null) {
            throw new \DomainException('The work order this change belongs to no longer exists.');
        }

        return $workOrder;
    }

    /** @throws \DomainException */
    protected function assertEditable(WorkOrderChangeRequest $changeRequest): void
    {
        if (! $changeRequest->isEditable()) {
            throw new \DomainException('Only a draft change request can be edited.');
        }
    }

    /** @throws \DomainException */
    protected function assertTransition(WorkOrderChangeRequest $changeRequest, ChangeRequestStatus $next): void
    {
        if (! $changeRequest->status->canTransitionTo($next)) {
            throw new \DomainException(
                "A {$changeRequest->status->value} change request cannot become {$next->value}."
            );
        }
    }

    /**
     * Next human-facing code for the order's tenant, as CR/{year}/{sequence}.
     *
     * The sequence is derived from the highest EXISTING NUMBER, not from the highest
     * string: codes sort lexicographically, so `CR/2026/10000` sorts below
     * `CR/2026/9999` and an ordered-by-code lookup would hand out 10000 forever once
     * a plant passes four digits.
     *
     * A counter table is deliberately not used — it would serialise every change
     * request in the plant behind one row. The unique index is the real guard, and a
     * create that loses a concurrent race retries with the next number.
     */
    protected function generateCode(WorkOrder $workOrder): string
    {
        $year = now()->year;
        $prefix = "CR/{$year}/";

        $highest = WorkOrderChangeRequest::where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))
            ->max() ?? 0;

        // Four digits is the shape a plant expects; longer sequences simply grow, and
        // stay correctly ordered because the number, not the string, is authoritative.
        return $prefix.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
