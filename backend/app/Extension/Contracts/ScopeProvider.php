<?php

namespace App\Extension\Contracts;

use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Lets an installed module divide one installation into scopes — for example
 * several plants — without core knowing what a scope is.
 *
 * Core asks this contract at the few places where "which part of the
 * installation is this person looking at" changes what they see and receive:
 *
 * - the header switcher (which scope is current, and which others they may pick),
 * - the synced-collection snapshot (GET /api/collections/{name}),
 * - the live delta channels those collections stream on.
 *
 * Eloquent reads are not routed through here: a module narrows those itself
 * with global scopes on the models it cares about. The snapshot endpoint and the
 * broadcaster work on plain query builders and attribute arrays, which global
 * scopes never see, so they need this seam.
 *
 * Partitions are how live deltas reach only the people allowed to see them. A
 * collection the module partitions publishes every changed row on its usual
 * channel (`col.{tenant}.{collection}`, for people seeing the whole
 * installation) AND on one channel per partition the row belongs to
 * (`col.{tenant}@{partition}.{collection}`). A person inside a scope subscribes
 * to their partition only. Partition keys must match [A-Za-z0-9_-]+.
 *
 * With no module bound, NullScopeProvider answers "one scope, everything
 * visible" and core behaves exactly as it did before this contract existed.
 */
interface ScopeProvider
{
    /**
     * The header switcher for this user, or null for none.
     *
     * Shape: [
     *   'label'   => string,                      // what a scope is called, translated
     *   'current' => string,                      // value of the selected option
     *   'options' => list<array{value: string, label: string}>,
     *   'action'  => string,                      // URL the choice is POSTed to as `scope`
     * ]
     *
     * @return array{label: string, current: string, options: list<array{value: string, label: string}>, action: string}|null
     */
    public function switcher(User $user): ?array;

    /** Narrow a collection snapshot to what this user may see in their current scope. */
    public function constrainSnapshot(string $collection, string $table, Builder $query, User $user): void;

    /**
     * The partition this user's live subscription to $collection uses, or null
     * for the collection's unpartitioned channel.
     */
    public function subscriptionPartition(string $collection, string $table, User $user): ?string;

    /**
     * Partitions a row of $collection belongs to. Empty when the collection is
     * not partitioned.
     *
     * @param  array<string, mixed>  $attributes  the row's raw attributes
     * @return list<string>
     */
    public function rowPartitions(string $collection, string $table, array $attributes): array;

    /**
     * May this user listen to $collection on $partition (null = the
     * unpartitioned channel, which carries every row of the collection)?
     */
    public function allowsPartition(User $user, string $collection, ?string $partition): bool;
}
