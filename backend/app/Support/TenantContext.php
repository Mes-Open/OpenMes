<?php

namespace App\Support;

/**
 * Holds the current request's tenant id for headless (no logged-in user)
 * contexts — chiefly the ERP API, where requests authenticate with an API key
 * rather than a session/Sanctum user.
 *
 * The tenant-aware pieces (TenantScope global scope, HasTenant creating hook)
 * resolve the tenant from the authenticated user first and fall back to this
 * context, so every existing tenant-scoped query keeps working unchanged while
 * API-key requests get correctly scoped without fabricating a user.
 *
 * Registered as a container singleton. Under Octane the singleton is reused
 * across requests, so AuthenticateApiKey sets it fresh on every ERP request
 * (and clears it on terminate) — a stale value can never leak between tenants.
 */
class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
