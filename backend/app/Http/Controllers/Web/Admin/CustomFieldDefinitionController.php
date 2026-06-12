<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreCustomFieldDefinitionRequest;
use App\Http\Requests\Web\Admin\UpdateCustomFieldDefinitionRequest;
use App\Models\CustomFieldDefinition;
use App\Services\CustomFieldService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CustomFieldDefinitionController extends Controller
{
    private const FILE_DIR = 'custom-field-files';

    public function __construct(private CustomFieldService $service) {}

    public function index()
    {
        $entities = $this->service->entities();

        $definitions = CustomFieldDefinition::orderBy('entity_type')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomFieldDefinition $d) => [
                'id' => $d->id,
                'entity_type' => $d->entity_type,
                'entity_label' => $entities[$d->entity_type] ?? $d->entity_type,
                'key' => $d->key,
                'label' => $d->label,
                'type' => $d->type->value,
                'type_label' => $d->type->label(),
                'options_count' => count($d->config['options'] ?? []),
                'required' => (bool) $d->required,
                'position' => (int) $d->position,
                'is_active' => (bool) $d->is_active,
            ]);

        return Inertia::render('admin/custom-fields/Index', [
            'definitions' => $definitions,
            'entities' => $this->entityOptions(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/custom-fields/Create', [
            'entities' => $this->entityOptions(),
            'types' => CustomFieldType::options(),
        ]);
    }

    public function store(StoreCustomFieldDefinitionRequest $request)
    {
        CustomFieldDefinition::create($this->payload($request));

        return redirect()->route('admin.custom-fields.index')
            ->with('success', __('Custom field created successfully.'));
    }

    public function edit(CustomFieldDefinition $customField)
    {
        return Inertia::render('admin/custom-fields/Edit', [
            'definition' => [
                'id' => $customField->id,
                'entity_type' => $customField->entity_type,
                'key' => $customField->key,
                'label' => $customField->label,
                'type' => $customField->type->value,
                'required' => (bool) $customField->required,
                'is_active' => (bool) $customField->is_active,
                'position' => (int) $customField->position,
                'config' => $customField->config ?? ['options' => []],
            ],
            'entities' => $this->entityOptions(),
            'types' => CustomFieldType::options(),
        ]);
    }

    public function update(UpdateCustomFieldDefinitionRequest $request, CustomFieldDefinition $customField)
    {
        $customField->update($this->payload($request));

        return redirect()->route('admin.custom-fields.index')
            ->with('success', __('Custom field updated successfully.'));
    }

    public function destroy(CustomFieldDefinition $customField)
    {
        $customField->delete();

        return redirect()->route('admin.custom-fields.index')
            ->with('success', __('Custom field deleted successfully. Existing stored values are left untouched.'));
    }

    public function toggleActive(CustomFieldDefinition $customField)
    {
        $customField->update(['is_active' => ! $customField->is_active]);

        return redirect()->route('admin.custom-fields.index')
            ->with('success', $customField->is_active
                ? __('Custom field activated successfully.')
                : __('Custom field deactivated successfully.'));
    }

    /**
     * Stream a stored file/image custom-field value. The stored path is always
     * under FILE_DIR; basename() prevents path traversal.
     */
    public function downloadFile(string $file)
    {
        $path = self::FILE_DIR.'/'.basename($file);

        abort_unless(Storage::exists($path), 404);

        return Storage::download($path);
    }

    /**
     * Build the create/update payload from validated input, applying booleans
     * and dropping the options array for non-option field types.
     *
     * @return array<string, mixed>
     */
    private function payload(StoreCustomFieldDefinitionRequest $request): array
    {
        $data = $request->validated();
        $data['required'] = $request->boolean('required');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['position'] = $data['position'] ?? 0;

        $optioned = in_array($data['type'] ?? null, [
            CustomFieldType::Select->value,
            CustomFieldType::Multiselect->value,
        ], true);

        if (! $optioned && isset($data['config']['options'])) {
            unset($data['config']['options']);
        }

        return $data;
    }

    /** Entities config (key => label) as [{value, label}] for the selects. */
    private function entityOptions(): array
    {
        return collect($this->service->entities())
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
