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
        // Falls back to the request-scoped TenantContext for headless (API-key)
        // requests, which carry no logged-in user but must still be tenant-scoped.
        $tenantId = auth()->hasUser()
            ? auth()->user()?->tenant_id
            : app(\App\Support\TenantContext::class)->id();

        if ($tenantId) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }
}
