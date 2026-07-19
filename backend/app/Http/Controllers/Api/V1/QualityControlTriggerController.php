<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQualityControlTriggerRequest;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\QualityCheckTemplate;
use App\Models\QualityControlTrigger;
use App\Models\Workstation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Quality-control triggers (#105) — rules that spawn QC tasks during production.
 * Mirrors the web admin screen (Pages/admin/quality-control-triggers): name,
 * trigger type, the QC template it runs, its scope (line / workstation / product
 * type), threshold, blocking + active flags. Full CRUD.
 */
class QualityControlTriggerController extends Controller
{
    private function present(QualityControlTrigger $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'trigger_type' => $t->trigger_type,
            'quality_check_template_id' => $t->quality_check_template_id,
            'template_name' => $t->template?->name,
            'line_id' => $t->line_id,
            'workstation_id' => $t->workstation_id,
            'product_type_id' => $t->product_type_id,
            'scope' => $t->line?->name ?? $t->workstation?->name ?? $t->productType?->name,
            'threshold_n' => $t->threshold_n,
            'downtime_min_minutes' => $t->downtime_min_minutes,
            'is_blocking' => $t->is_blocking,
            'is_active' => $t->is_active,
        ];
    }

    public function index(): JsonResponse
    {
        $triggers = QualityControlTrigger::with([
            'template:id,name',
            'line:id,name',
            'workstation:id,name',
            'productType:id,name',
        ])
            ->orderBy('name')
            ->get()
            ->map(fn (QualityControlTrigger $t) => $this->present($t));

        return response()->json(['data' => $triggers]);
    }

    /** Catalogs for the create/edit form: trigger types + scope option lists. */
    public function formMeta(): JsonResponse
    {
        $types = collect(QualityControlTrigger::TYPES)->map(fn (string $type) => [
            'value' => $type,
            'label' => Str::of($type)->replace('_', ' ')->title()->toString(),
            'needs_threshold' => in_array($type, QualityControlTrigger::FREQUENCY_TYPES, true),
        ]);

        return response()->json(['data' => [
            'types' => $types,
            'templates' => QualityCheckTemplate::orderBy('name')->get(['id', 'name']),
            'lines' => Line::orderBy('name')->get(['id', 'name']),
            'workstations' => Workstation::orderBy('name')->get(['id', 'name']),
            'product_types' => ProductType::orderBy('name')->get(['id', 'name']),
        ]]);
    }

    public function store(StoreQualityControlTriggerRequest $request): JsonResponse
    {
        $trigger = QualityControlTrigger::create($request->validated());

        return response()->json(['data' => $this->present($trigger->fresh(['template', 'line', 'workstation', 'productType']))], 201);
    }

    public function update(StoreQualityControlTriggerRequest $request, QualityControlTrigger $qualityControlTrigger): JsonResponse
    {
        $qualityControlTrigger->update($request->validated());

        return response()->json(['data' => $this->present($qualityControlTrigger->fresh(['template', 'line', 'workstation', 'productType']))]);
    }

    public function toggleActive(QualityControlTrigger $qualityControlTrigger): JsonResponse
    {
        $qualityControlTrigger->update(['is_active' => ! $qualityControlTrigger->is_active]);

        return response()->json(['data' => ['id' => $qualityControlTrigger->id, 'is_active' => $qualityControlTrigger->is_active]]);
    }

    public function destroy(QualityControlTrigger $qualityControlTrigger): JsonResponse
    {
        $qualityControlTrigger->delete();

        return response()->json(null, 204);
    }
}
