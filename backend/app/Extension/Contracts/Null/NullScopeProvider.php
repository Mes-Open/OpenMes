<?php

namespace App\Extension\Contracts\Null;

use App\Extension\Contracts\ScopeProvider;
use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * One scope, and everything in it visible — what an installation without a
 * scoping module has always been. No switcher, no narrowing, no partitions.
 */
class NullScopeProvider implements ScopeProvider
{
    public function switcher(User $user): ?array
    {
        return null;
    }

    public function constrainSnapshot(string $collection, string $table, Builder $query, User $user): void {}

    public function subscriptionPartition(string $collection, string $table, User $user): ?string
    {
        return null;
    }

    public function rowPartitions(string $collection, string $table, array $attributes): array
    {
        return [];
    }

    public function allowsPartition(User $user, string $collection, ?string $partition): bool
    {
        return $partition === null;
    }
}
