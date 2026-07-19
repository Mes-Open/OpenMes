<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IntegrationConfigRequest;
use App\Models\IntegrationConfig;
use Illuminate\Http\JsonResponse;

/**
 * External-system integrations (ERP/WMS connectors) for the mobile app,
 * mirroring the web admin integrations screen (Pages/admin/integrations): system
 * type, name and active state. Full CRUD; the encrypted credential blob
 * (`api_config`) is managed elsewhere and never exposed here.
 */
class IntegrationConfigController extends Controller
{
    private function present(IntegrationConfig $c): array
    {
        return [
            'id' => $c->id,
            'system_type' => $c->system_type,
            'system_name' => $c->system_name,
            'is_active' => $c->is_active,
            'material_sources_count' => $c->material_sources_count ?? $c->materialSources()->count(),
        ];
    }

    public function index(): JsonResponse
    {
        $integrations = IntegrationConfig::withCount('materialSources')
            ->orderBy('system_name')
            ->get()
            ->map(fn (IntegrationConfig $c) => $this->present($c));

        return response()->json(['data' => $integrations]);
    }

    public function store(IntegrationConfigRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $integration = IntegrationConfig::create($data);

        return response()->json(['data' => $this->present($integration)], 201);
    }

    public function update(IntegrationConfigRequest $request, IntegrationConfig $integration): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $integration->is_active);

        $integration->update($data);

        return response()->json(['data' => $this->present($integration->fresh())]);
    }

    public function toggleActive(IntegrationConfig $integration): JsonResponse
    {
        $integration->update(['is_active' => ! $integration->is_active]);

        return response()->json(['data' => ['id' => $integration->id, 'is_active' => $integration->is_active]]);
    }

    public function destroy(IntegrationConfig $integration): JsonResponse
    {
        if ($integration->materialSources()->exists()) {
            return response()->json([
                'message' => __('Cannot delete integration with linked materials. Deactivate it instead.'),
            ], 422);
        }

        $integration->delete();

        return response()->json(null, 204);
    }
}
