<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/erp/me` — tells an integration what its own key can do: name,
 * granted and missing scopes, tenant, expiry, the ERP-relevant modules and the
 * app version. Open to any valid key (no scope needed) so a misconfigured
 * integration can diagnose itself.
 *
 * The response is an explicit allowlist: the key's id, prefix, hash and IP
 * allowlist are never serialised.
 */
class KeyIntrospectionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var ApiKey $key */
        $key = $request->attributes->get('api_key');

        $scopes = array_values(array_intersect(ApiScope::values(), $key->scopes ?? []));
        $tenant = $key->tenant_id !== null ? Tenant::find($key->tenant_id, ['id', 'name']) : null;

        return response()->json([
            'data' => [
                'name' => $key->name,
                'scopes' => $scopes,
                'missing_scopes' => array_values(array_diff(ApiScope::values(), $scopes)),
                'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->name] : null,
                'expires_at' => $key->expires_at?->toIso8601String(),
                'modules' => [
                    'erp' => ModuleRegistry::isModuleEnabled('erp'),
                    'warehouse' => ModuleRegistry::isModuleEnabled('warehouse'),
                ],
                'app_version' => config('version.current'),
            ],
        ]);
    }
}
