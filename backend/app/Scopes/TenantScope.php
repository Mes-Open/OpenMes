<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // hasUser() checks internal guard state without triggering user loading from session,
        // avoiding infinite recursion when the scope is applied during authentication.
        if (auth()->hasUser() && auth()->user()?->tenant_id) {
            $builder->where($model->getTable() . '.tenant_id', auth()->user()->tenant_id);
        }
    }
}
