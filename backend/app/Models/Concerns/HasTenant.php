<?php

namespace App\Models\Concerns;

use App\Scopes\TenantScope;

trait HasTenant
{
    public static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! empty($model->tenant_id)) {
                return;
            }

            // Logged-in user's tenant first; fall back to the request-scoped
            // TenantContext so API-key (headless) writes are tenant-stamped too.
            $tenantId = auth()->check()
                ? auth()->user()->tenant_id
                : app(\App\Support\TenantContext::class)->id();

            if ($tenantId) {
                $model->tenant_id = $tenantId;
            }
        });
    }
}
