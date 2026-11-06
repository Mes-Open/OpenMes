<?php

namespace App\Support;

/**
 * Catalog of domain events an outgoing webhook can subscribe to (#20).
 *
 * This is the single source of truth shared by the subscription Form Request
 * (which events are valid), the admin UI (the checklist) and the observers
 * that fire them. Adding a new event is a one-line entry here plus the code
 * that dispatches it — no schema change, since subscriptions are a JSON array
 * of these keys on the webhooks table.
 */
class WebhookEventRegistry
{
    public const WORK_ORDER_CREATED = 'work_order.created';

    public const WORK_ORDER_STATUS_CHANGED = 'work_order.status_changed';

    /** produced_qty changed — throttled per order (openmmes.webhooks.progress_throttle_seconds). */
    public const WORK_ORDER_PROGRESS = 'work_order.progress';

    public const ISSUE_CREATED = 'issue.created';

    /** An issue raised against a work order, in the ERP quality-export shape. */
    public const QUALITY_ISSUE_CREATED = 'quality_issue.created';

    public const BATCH_COMPLETED = 'batch.completed';

    public const STOCK_DOCUMENT_POSTED = 'stock_document.posted';

    /**
     * event key => English label (translated through __() for the UI).
     * Order drives the UI checklist order.
     */
    public const EVENTS = [
        self::WORK_ORDER_CREATED => 'Work order created',
        self::WORK_ORDER_STATUS_CHANGED => 'Work order status changed',
        self::WORK_ORDER_PROGRESS => 'Work order progress',
        self::ISSUE_CREATED => 'Issue created',
        self::QUALITY_ISSUE_CREATED => 'Quality issue created',
        self::BATCH_COMPLETED => 'Batch completed',
        self::STOCK_DOCUMENT_POSTED => 'Stock document posted',
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::EVENTS);
    }

    public static function exists(string $event): bool
    {
        return array_key_exists($event, self::EVENTS);
    }

    /**
     * Shape for the subscription UI: [{key, label}].
     *
     * @return array<int, array{key: string, label: string}>
     */
    public static function forForm(): array
    {
        return array_map(
            fn (string $key) => ['key' => $key, 'label' => __(self::EVENTS[$key])],
            self::keys(),
        );
    }
}
