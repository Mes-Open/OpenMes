<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApiKeyRequest;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;

/**
 * Admin management of ERP integration API keys. The plaintext secret is
 * returned exactly once, in the store() response; thereafter only the prefix
 * is ever exposed. Admin-only via the route's role:Admin middleware.
 */
class ApiKeyController extends Controller
{
    private function present(ApiKey $key): array
    {
        return [
            'id' => $key->id,
            'name' => $key->name,
            'key_prefix' => $key->key_prefix,
            'scopes' => $key->scopes,
            'ip_allowlist' => $key->ip_allowlist,
            'is_active' => $key->is_active,
            'integration_config_id' => $key->integration_config_id,
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'expires_at' => $key->expires_at?->toIso8601String(),
            'created_at' => $key->created_at?->toIso8601String(),
        ];
    }

    public function index(): JsonResponse
    {
        $keys = ApiKey::orderByDesc('created_at')->get()
            ->map(fn (ApiKey $k) => $this->present($k));

        return response()->json(['data' => $keys]);
    }

    public function store(ApiKeyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        [$key, $plaintext] = ApiKey::issue($data);

        // The plaintext secret is surfaced here and nowhere else — the caller
        // must store it now; only its SHA-256 hash is retained server-side.
        return response()->json([
            'data' => $this->present($key),
            'plaintext_key' => $plaintext,
        ], 201);
    }

    public function update(ApiKeyRequest $request, ApiKey $apiKey): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $apiKey->is_active);

        $apiKey->update($data);

        return response()->json(['data' => $this->present($apiKey->fresh())]);
    }

    public function toggleActive(ApiKey $apiKey): JsonResponse
    {
        $apiKey->update(['is_active' => ! $apiKey->is_active]);

        return response()->json(['data' => ['id' => $apiKey->id, 'is_active' => $apiKey->is_active]]);
    }

    public function destroy(ApiKey $apiKey): JsonResponse
    {
        $apiKey->delete();

        return response()->json(null, 204);
    }
}
