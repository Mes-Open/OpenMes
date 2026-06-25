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
    public const WORK_ORDER_STATUS_CHANGED = 'work_order.status_changed';

    public const ISSUE_CREATED = 'issue.created';

    public const BATCH_COMPLETED = 'batch.completed';

    /** event key => human label. Order drives the UI checklist order. */
    public const EVENTS = [
        self::WORK_ORDER_STATUS_CHANGED => 'Work order status changed',
        self::ISSUE_CREATED => 'Issue created',
        self::BATCH_COMPLETED => 'Batch completed',
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
            fn (string $key) => ['key' => $key, 'label' => self::EVENTS[$key]],
            self::keys(),
        );
    }
}
