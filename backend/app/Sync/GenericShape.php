<?php

namespace App\Sync;

use App\Models\User;

/**
 * A shape defined inline by config (table + columns + optional where) rather
 * than a dedicated class. Lets the registry declare simple lookup-table shapes
 * for the admin CRUD sweep without a class each.
 *
 * `where` may be a string, null, or a closure(User): ?string for per-user scope.
 */
class GenericShape extends Shape
{
    public function __construct(
        private string $table,
        private array $columns,
        private mixed $where = null,
    ) {}

    public function table(): string
    {
        return $this->table;
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function where(User $user): ?string
    {
        return is_callable($this->where) ? ($this->where)($user) : $this->where;
    }
}
