<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A single create/update/delete to a synced collection, pushed to the
 * collection's private channel so the client's realtimeCollection adapter can
 * apply it.
 *
 * Channel: col.{tenantKey}.{collection}  (tenantKey = tenant_id or "g" for null,
 * mirroring the null-safe TenantScope). Broadcast NOW (synchronous) since
 * QUEUE_CONNECTION=sync.
 *
 * When a module partitions the collection (App\Extension\Contracts\ScopeProvider)
 * the same delta also goes to col.{tenantKey}@{partition}.{collection} for each
 * partition the row belongs to. `$base = false` sends it to the partitions only
 * — used to take a row out of a partition it just left.
 */
class CollectionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  string  $collection  registry name (e.g. "skills", "work_orders_active")
     * @param  string  $op  "insert" | "update" | "delete"
     * @param  array  $row  the changed row, projected to the collection's columns
     */
    public function __construct(
        public string $collection,
        public string $op,
        public array $row,
        public string|int|null $tenantKey = null,
        public array $partitions = [],
        public bool $base = true,
    ) {}

    public function broadcastOn(): array
    {
        $tenant = $this->tenantKey === null ? 'g' : $this->tenantKey;

        $channels = $this->base ? [new PrivateChannel("col.{$tenant}.{$this->collection}")] : [];

        foreach (array_unique($this->partitions) as $partition) {
            $channels[] = new PrivateChannel("col.{$tenant}@{$partition}.{$this->collection}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    public function broadcastWith(): array
    {
        return ['op' => $this->op, 'row' => $this->row];
    }
}
