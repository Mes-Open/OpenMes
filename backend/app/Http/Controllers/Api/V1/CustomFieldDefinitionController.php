<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFieldDefinitionRequest;
use App\Models\CustomFieldDefinition;
use App\Services\CustomFieldService;
use Illuminate\Http\JsonResponse;

/**
 * Custom field definitions (admin-defined extra fields per entity type) for the
 * mobile app, mirroring the web admin screen (Pages/admin/custom-fields):
 * entity, key, label, type, options, required, position, active. Full CRUD.
 */
class CustomFieldDefinitionController extends Controller
{
    public function __construct(private CustomFieldService $service) {}

    private function present(CustomFieldDefinition $d): array
    {
        $entities = $this->service->entities();

        return [
            'id' => $d->id,
            'entity_type' => $d->entity_type,
            'entity_label' => $entities[$d->entity_type]['label'] ?? $d->entity_type,
            'key' => $d->key,
            'label' => $d->label,
            'type' => $d->type->value,
            'type_label' => $d->type->label(),
            'config' => $d->config ?? [],
            'options_count' => count($d->config['options'] ?? []),
            'required' => (bool) $d->required,
            'position' => (int) $d->position,
            'is_active' => (bool) $d->is_active,
        ];
    }

    public function index(): JsonResponse
    {
        $definitions = CustomFieldDefinition::orderBy('entity_type')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomFieldDefinition $d) => $this->present($d));

        return response()->json(['data' => $definitions]);
    }

    /** Entity + field-type catalogs for the create/edit form. */
    public function formMeta(): JsonResponse
    {
        $entities = collect($this->service->entities())
            ->map(fn (array $e, string $key) => ['value' => $key, 'label' => $e['label'] ?? $key])
            ->values();

        $types = collect(CustomFieldType::cases())->map(fn (CustomFieldType $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'has_options' => in_array($t, [CustomFieldType::Select, CustomFieldType::Multiselect], true),
        ]);

        return response()->json(['data' => ['entities' => $entities, 'types' => $types]]);
    }

    public function store(CustomFieldDefinitionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['required'] = $request->boolean('required');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['position'] = $data['position'] ?? 0;

        $definition = CustomFieldDefinition::create($data);

        return response()->json(['data' => $this->present($definition)], 201);
    }

    public function update(CustomFieldDefinitionRequest $request, CustomFieldDefinition $customField): JsonResponse
    {
        $data = $request->validated();
        $data['required'] = $request->boolean('required');
        $data['is_active'] = $request->boolean('is_active', $customField->is_active);

        $customField->update($data);

        return response()->json(['data' => $this->present($customField->fresh())]);
    }

    public function toggleActive(CustomFieldDefinition $customField): JsonResponse
    {
        $customField->update(['is_active' => ! $customField->is_active]);

        return response()->json(['data' => ['id' => $customField->id, 'is_active' => $customField->is_active]]);
    }

    public function destroy(CustomFieldDefinition $customField): JsonResponse
    {
        $customField->delete();

        return response()->json(null, 204);
    }
}
