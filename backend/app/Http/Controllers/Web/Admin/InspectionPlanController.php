<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\InspectionPlan;
use App\Models\Inspection;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Services\Quality\InboundInspectionService;
use App\Services\Quality\InspectionPlanVersionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Admin surface for inbound inspection plans. The API could already create and
 * version plans, but a QA lead had no screen to do it; receiving inspection
 * therefore depended on seed data or curl. This keeps the same version rules:
 * a draft is edited in place, editing a published plan forks a new draft, and
 * publishing retires the previous live version.
 */
class InspectionPlanController extends Controller
{
    public function __construct(
        private readonly InspectionPlanVersionService $versions,
        private readonly InboundInspectionService $inspections,
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $plans = InspectionPlan::query()
            ->with(['material:id,name,code', 'materialType:id,name'])
            ->withCount('inspections')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            }))
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        $arrivals = $this->arrivals();

        return Inertia::render('admin/inspection-plans/Index', [
            'rows' => $plans->map(fn (InspectionPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'scope' => $plan->material
                    ? '原料：'.$plan->material->name
                    : ($plan->materialType ? '类别：'.$plan->materialType->name : '通用计划'),
                'material_id' => $plan->material_id,
                'material_type_id' => $plan->material_type_id,
                'material_name' => $plan->material?->name,
                'material_type_name' => $plan->materialType?->name,
                'version' => $plan->version,
                'is_draft' => $plan->isDraft(),
                'is_active' => (bool) $plan->is_active,
                'criteria_count' => count(InspectionPlan::normalizeCriteria($plan->criteria)),
                'criteria_text' => $this->criteriaToText($plan->criteria),
                'inspections_count' => $plan->inspections_count,
                'published_at' => $plan->published_at?->toIso8601String(),
                'root_id' => $plan->root_id,
            ])->values(),
            'materials' => Material::query()->orderBy('name')->get(['id', 'name', 'code'])->map(fn (Material $material) => [
                'value' => (string) $material->id,
                'label' => $material->code ? "{$material->name}（{$material->code}）" : $material->name,
            ])->values(),
            'materialTypes' => MaterialType::query()->orderBy('name')->get(['id', 'name'])->map(fn (MaterialType $type) => [
                'value' => (string) $type->id,
                'label' => $type->name,
            ])->values(),
            'basePath' => '/admin/inspection-plans',
            'arrivals' => $arrivals,
            'pendingArrivals' => $arrivals->where('can_dispatch', true)->count(),
            'filters' => ['q' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $plan = InspectionPlan::create([
            ...$data,
            'version' => 1,
            'published_at' => null,
            'root_id' => null,
            'is_active' => false,
        ]);

        return back()->with('success', __('来料检验计划已保存为草稿：:name。', ['name' => $plan->name]));
    }

    public function update(Request $request, InspectionPlan $inspectionPlan)
    {
        $data = $this->validated($request);

        if ($inspectionPlan->isDraft()) {
            $inspectionPlan->update($data);

            return back()->with('success', __('草稿已更新：:name。', ['name' => $inspectionPlan->name]));
        }

        $draft = $this->versions->createNewVersion($inspectionPlan, $data);

        return back()->with('success', __('已从 V:from 派生 V:to 草稿；发布后才会生效。', [
            'from' => $inspectionPlan->version,
            'to' => $draft->version,
        ]));
    }

    public function publish(InspectionPlan $inspectionPlan)
    {
        $this->versions->publish($inspectionPlan);

        return back()->with('success', __('来料检验计划已发布并生效：:name V:version。', [
            'name' => $inspectionPlan->name,
            'version' => $inspectionPlan->version,
        ]));
    }

    public function destroy(InspectionPlan $inspectionPlan)
    {
        if ($inspectionPlan->isPublished() && $inspectionPlan->inspections()->exists()) {
            throw ValidationException::withMessages([
                'plan' => '该版本已用于实际来料检验，不能删除；请派生新版本停用旧要求。',
            ]);
        }

        $inspectionPlan->delete();

        return back()->with('success', __('来料检验计划已删除。'));
    }

    /**
     * Turn posted material-receipt lines into pending inbound inspections.
     * One receipt line can only produce one inspection (unique link), so this
     * is safe to run repeatedly as new deliveries are posted.
     */
    public function dispatch(Request $request)
    {
        $user = $request->user();
        $created = 0;
        $existing = 0;
        $withoutPlan = 0;

        foreach ($this->receiptLines(100) as $line) {
            if ($line->inspection) {
                $existing++;
                continue;
            }

            $material = $line->material;
            if (! $material) {
                continue;
            }

            $plan = $this->inspections->applicablePlan($material);
            if (! $plan) {
                $withoutPlan++;
                continue;
            }

            $lotNumber = $line->materialLot?->lot_number
                ?? $line->lot_number
                ?? $line->stockDocument?->document_no;

            try {
                $this->inspections->start(
                    $material,
                    $lotNumber,
                    (float) $line->quantity,
                    $plan,
                    $user,
                    $line->lot_number,
                    null,
                    $line->id,
                );
                $created++;
            } catch (UniqueConstraintViolationException) {
                // A concurrent dispatch won the unique link; the line is covered.
                $existing++;
            }
        }

        $message = "已生成 {$created} 个来料检验任务。";
        if ($existing > 0) {
            $message .= " {$existing} 条到货已有检验记录。";
        }
        if ($withoutPlan > 0) {
            $message .= " {$withoutPlan} 条原料没有已发布的适用计划，未派工。";
        }

        return back()->with('success', $message);
    }

    /**
     * Posted material receipts from the last 30 days, with the plan and
     * inspection already attached where they exist.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function arrivals(): \Illuminate\Support\Collection
    {
        return $this->receiptLines(100)->map(function (StockDocumentLine $line) {
            $inspection = $line->inspection;
            $lot = $inspection?->lotsTested()->first();
            $plan = $line->material ? $this->inspections->applicablePlan($line->material) : null;

            return [
                'id' => $line->id,
                'document_no' => $line->stockDocument?->document_no,
                'posted_at' => $line->stockDocument?->posted_at?->toIso8601String(),
                'material_name' => $line->material?->name,
                'material_code' => $line->material?->code,
                'lot_number' => $line->materialLot?->lot_number ?? $line->lot_number ?? $line->stockDocument?->document_no,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit_of_measure,
                'plan_label' => $plan ? $plan->name.' V'.$plan->version : null,
                'inspection_id' => $inspection?->id,
                'inspection_status' => $inspection?->status,
                'disposition' => $inspection?->disposition,
                'lot_number_actual' => $lot?->lot_number,
                'lot_status' => $lot?->status,
                'can_dispatch' => ! $inspection && $plan !== null,
            ];
        })->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, StockDocumentLine>
     */
    private function receiptLines(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        return StockDocumentLine::query()
            ->with([
                'material:id,name,code,unit_of_measure,material_type_id',
                'materialLot:id,lot_number',
                'stockDocument:id,document_no,type,status,posted_at',
                'inspection:id,stock_document_line_id,status,lot_number,disposition',
            ])
            ->whereNotNull('material_id')
            ->whereHas('stockDocument', fn ($query) => $query
                ->where('type', StockDocument::TYPE_MATERIAL_RECEIPT)
                ->where('status', StockDocument::STATUS_POSTED)
                ->whereNotNull('posted_at')
                ->where('posted_at', '>=', now()->subDays(30)))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{name:string, description:?string, material_id:?int, material_type_id:?int, criteria:list<array<string,mixed>>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'material_type_id' => ['nullable', 'integer', 'exists:material_types,id'],
            'criteria_text' => ['required', 'string', 'max:6000'],
        ]);

        if (! empty($data['material_id']) && ! empty($data['material_type_id'])) {
            throw ValidationException::withMessages([
                'criteria_text' => '适用范围只能选原料或原料类别之一，不能同时指定。',
            ]);
        }

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'material_id' => $data['material_id'] ?? null,
            'material_type_id' => $data['material_type_id'] ?? null,
            'criteria' => $this->criteriaFromText($data['criteria_text']),
        ];
    }

    /**
     * Each line: 项目|类型|单位|下限|上限|必填.
     * Type accepts visual/measurement/functional/pass_fail or 感官/测量/功能/合格.
     *
     * @return list<array<string,mixed>>
     */
    private function criteriaFromText(string $text): array
    {
        $criteria = [];
        foreach (preg_split('/\R/u', $text) as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', array_pad(explode('|', $line), 6, ''));
            $name = $parts[0];
            if ($name === '') {
                throw ValidationException::withMessages([
                    'criteria_text' => '第 '.($index + 1).' 行缺少项目名称。',
                ]);
            }

            $type = match (mb_strtolower($parts[1])) {
                '', '合格', '合格/不合格', 'pass_fail', 'pass-fail' => 'pass_fail',
                '感官', '目视', 'visual' => 'visual',
                '测量', 'measurement' => 'measurement',
                '功能', 'functional' => 'functional',
                default => throw ValidationException::withMessages([
                    'criteria_text' => '第 '.($index + 1).' 行类型无效，可用：感官 / 测量 / 功能 / 合格。',
                ]),
            };

            $specMin = $parts[3] === '' ? null : (float) $parts[3];
            $specMax = $parts[4] === '' ? null : (float) $parts[4];
            if ($type === 'measurement' && $specMin !== null && $specMax !== null && $specMin > $specMax) {
                throw ValidationException::withMessages([
                    'criteria_text' => '第 '.($index + 1).' 行下限不能大于上限。',
                ]);
            }

            $required = ! in_array(mb_strtolower($parts[5]), ['0', '否', '选填', 'no', 'false'], true);

            $criteria[] = array_filter([
                'name' => $name,
                'type' => $type,
                'unit' => $parts[2] ?: null,
                'spec_min' => $specMin,
                'spec_max' => $specMax,
                'required' => $required,
            ], fn ($value) => $value !== null);
        }

        if ($criteria === []) {
            throw ValidationException::withMessages([
                'criteria_text' => '至少填写一条检验项目。',
            ]);
        }

        return $criteria;
    }

    private function criteriaToText(?array $criteria): string
    {
        return collect(InspectionPlan::normalizeCriteria($criteria))
            ->map(fn (array $criterion) => implode('|', [
                $criterion['name'] ?? '',
                $criterion['type'] ?? 'pass_fail',
                $criterion['unit'] ?? '',
                $criterion['spec_min'] ?? '',
                $criterion['spec_max'] ?? '',
                ($criterion['required'] ?? true) ? '必填' : '选填',
            ]))
            ->implode("\n");
    }
}
