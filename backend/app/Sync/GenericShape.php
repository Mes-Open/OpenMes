<?php

namespace App\Sync;

use App\Models\User;

/**
 * A shape defined inline by config (table + columns + optional where) rather
 * than a dedicated class. Lets the registry declare simple lookup-table shapes
 * for the admin CRUD sweep without a class each.
 *
 * `where` may be a string, null, or a closure(User): ?string for per-user scope.
 * `tabs` / `roles` widen who may read it beyond Admin/Supervisor (see Shape::allows()).
 */
class GenericShape extends Shape
{
    public function __construct(
        private string $table,
        private array $columns,
        private mixed $where = null,
        private array $tabs = [],
        private array $roles = [],
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

    public function tabs(): array
    {
        return $this->tabs;
    }

    public function roles(): array
    {
        return $this->roles;
    }
}
