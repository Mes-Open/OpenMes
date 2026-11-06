<?php

namespace App\Services\Erp;

use App\Enums\ChangeRequestType;
use App\Models\ApiKey;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Services\WorkOrder\ChangeRequestService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What an ERP may do to a work order on its own, and what needs a manager.
 *
 * The rule is the order's status: before production starts (PENDING, ACCEPTED) the
 * ERP owns the order and its updates and cancellations apply at once. Once the order
 * is in production, the ERP can only ask — its update or cancellation becomes a
 * change request a manager approves or rejects, and the ERP reads the decision back
 * from the API. A finished order is not touched at all.
 *
 * Both the import and the cancel endpoint decide here, under a row lock on the order,
 * so an order that starts between the check and the write is never overwritten.
 */
class ErpWorkOrderControlService
{
    /** Not started: the ERP's changes apply directly. */
    public const DIRECT_STATUSES = [
        WorkOrder::STATUS_PENDING,
        WorkOrder::STATUS_ACCEPTED,
    ];

    /** In production: the ERP's changes become change requests. */
    public const CONTROLLED_STATUSES = [
        WorkOrder::STATUS_IN_PROGRESS,
        WorkOrder::STATUS_PAUSED,
        WorkOrder::STATUS_BLOCKED,
        WorkOrder::STATUS_CHANGE_HOLD,
    ];

    /**
     * Fields the ERP payload carries that a change request may propose, mapped to
     * the order attribute they land on.
     */
    private const CHANGEABLE = [
        'line_code' => 'line_id',
        'planned_qty' => 'planned_qty',
        'due_date' => 'due_date',
        'description' => 'description',
    ];

    private const QTY_EPSILON = 0.0001;

    public function __construct(
        protected ChangeRequestService $changeRequests,
    ) {}

    public static function isDirect(WorkOrder $workOrder): bool
    {
        return in_array($workOrder->status, self::DIRECT_STATUSES, true);
    }

    public static function isControlled(WorkOrder $workOrder): bool
    {
        return in_array($workOrder->status, self::CONTROLLED_STATUSES, true);
    }

    /**
     * Cancel an order on behalf of the ERP.
     *
     * @return array{result: 'cancelled'|'change_requested'|'conflict', status: string, work_order: WorkOrder, change_request?: WorkOrderChangeRequest}
     *
     * @throws \DomainException When the cancel transition refuses the order.
     */
    public function cancel(WorkOrder $workOrder, ApiKey $apiKey, ?string $reason): array
    {
        return DB::transaction(function () use ($workOrder, $apiKey, $reason) {
            $locked = WorkOrder::whereKey($workOrder->getKey())->lockForUpdate()->firstOrFail();

            if (self::isDirect($locked)) {
                // The UI's own Cancel transition, so the status rules and the audit
                // trail (Auditable on WorkOrder) are the same as a manual cancel.
                $result = WorkOrderService::applyTransition($locked, 'cancel');

                if (! $result['ok']) {
                    throw new \DomainException($result['message']);
                }

                $locked->refresh();

                if ($reason !== null && $reason !== '') {
                    $locked->update([
                        'extra_data' => array_merge($locked->extra_data ?? [], ['cancellation_reason' => $reason]),
                    ]);
                }

                return ['result' => 'cancelled', 'status' => $locked->status, 'work_order' => $locked];
            }

            if (! self::isControlled($locked)) {
                return ['result' => 'conflict', 'status' => $locked->status, 'work_order' => $locked];
            }

            // Asking twice is asking once: an open cancellation from the ERP already
            // says everything a second one would.
            $changeRequest = $this->openIntegrationRequest($locked, ChangeRequestType::Cancellation)
                ?? $this->changeRequests->createFromIntegration($locked, [
                    'title' => __('ERP cancellation of :order', ['order' => $locked->order_no]),
                    'reason' => ($reason !== null && $reason !== '')
                        ? $reason
                        : __('Requested by ERP integration :name', ['name' => $apiKey->name]),
                ], $apiKey, ChangeRequestType::Cancellation);

            return [
                'result' => 'change_requested',
                'status' => $locked->status,
                'work_order' => $locked,
                'change_request' => $changeRequest,
            ];
        });
    }

    /**
     * The fields an ERP row would change on an order that is already in production.
     *
     * Required fields are always compared. Optional fields are compared only when the
     * row carries the key — an absent key is "no opinion", not "clear it".
     *
     * @param  array<string, mixed>  $row
     * @return array{changes: array<string, mixed>, locked_field: string|null}
     *                                                                         `changes` keyed by order attribute; `locked_field` is
     *                                                                         the first ERP field that differs but cannot be changed
     *                                                                         through a request
     */
    public function diffForRow(WorkOrder $workOrder, array $row, Line $line, ProductType $productType): array
    {
        if ((int) $workOrder->product_type_id !== (int) $productType->id) {
            return ['changes' => [], 'locked_field' => 'product_type_code'];
        }

        if (array_key_exists('priority', $row) && $row['priority'] !== null
            && (int) $row['priority'] !== (int) $workOrder->priority) {
            return ['changes' => [], 'locked_field' => 'priority'];
        }

        if (array_key_exists('customer_order_no', $row) && $row['customer_order_no'] !== null
            && (string) $row['customer_order_no'] !== (string) $workOrder->customer_order_no) {
            return ['changes' => [], 'locked_field' => 'customer_order_no'];
        }

        if (array_key_exists('unit_price', $row) && $row['unit_price'] !== null
            && ($workOrder->unit_price === null || abs((float) $row['unit_price'] - (float) $workOrder->unit_price) > self::QTY_EPSILON)) {
            return ['changes' => [], 'locked_field' => 'unit_price'];
        }

        $changes = [];

        if ((int) $workOrder->line_id !== (int) $line->id) {
            $changes['line_id'] = $line->id;
        }

        if (abs((float) $row['planned_qty'] - (float) $workOrder->planned_qty) > self::QTY_EPSILON) {
            $changes['planned_qty'] = (float) $row['planned_qty'];
        }

        if (array_key_exists('due_date', $row)) {
            $wanted = $row['due_date'] !== null && $row['due_date'] !== ''
                ? Carbon::parse($row['due_date'])->toDateString()
                : null;

            if ($wanted !== $workOrder->due_date?->toDateString()) {
                $changes['due_date'] = $wanted;
            }
        }

        if (array_key_exists('description', $row)) {
            $wanted = $row['description'] === '' ? null : $row['description'];
            $current = $workOrder->description === '' ? null : $workOrder->description;

            if ($wanted !== $current) {
                $changes['description'] = $wanted;
            }
        }

        return ['changes' => $changes, 'locked_field' => null];
    }

    /**
     * Turn the ERP's desired state of an order in production into a change request —
     * creating one, revising the ERP's open one, or withdrawing it when the order
     * already matches.
     *
     * Call inside a transaction holding a lock on the order.
     *
     * @param  array<string, mixed>  $changes  keyed by order attribute (see diffForRow)
     * @return array{action: 'change_requested'|'skipped', change_request_id: int|null}
     */
    public function proposeUpdate(WorkOrder $workOrder, array $changes, ApiKey $apiKey, ?string $reason = null): array
    {
        $open = $this->openIntegrationRequest($workOrder, ChangeRequestType::Change);

        if ($changes === []) {
            // The ERP is back to the order's current values: whatever it asked for
            // before is no longer wanted.
            if ($open !== null) {
                $this->changeRequests->withdrawFromIntegration($open);
            }

            return ['action' => 'skipped', 'change_request_id' => $open?->id];
        }

        $title = __('ERP change: :fields', ['fields' => implode(', ', $this->erpFieldNames($changes))]);
        $reason = ($reason !== null && $reason !== '')
            ? $reason
            : __('Requested by ERP integration :name', ['name' => $apiKey->name]);

        if ($open !== null) {
            $revised = $this->changeRequests->reviseFromIntegration($open, $changes, $reason);
            $revised->update(['title' => $title]);

            return ['action' => 'change_requested', 'change_request_id' => $revised->id];
        }

        $created = $this->changeRequests->createFromIntegration($workOrder, [
            'title' => $title,
            'reason' => $reason,
            'proposed' => $changes,
        ], $apiKey, ChangeRequestType::Change);

        return ['action' => 'change_requested', 'change_request_id' => $created->id];
    }

    /**
     * The integration's still-open request of a type on this order, from any of the
     * tenant's keys — rotating a key must not start a second, parallel request.
     */
    public function openIntegrationRequest(WorkOrder $workOrder, ChangeRequestType $type): ?WorkOrderChangeRequest
    {
        return WorkOrderChangeRequest::where('work_order_id', $workOrder->id)
            ->where('type', $type->value)
            ->fromIntegration()
            ->open()
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return list<string>
     */
    private function erpFieldNames(array $changes): array
    {
        $byAttribute = array_flip(self::CHANGEABLE);

        return array_values(array_map(fn (string $attribute) => $byAttribute[$attribute] ?? $attribute, array_keys($changes)));
    }
}
