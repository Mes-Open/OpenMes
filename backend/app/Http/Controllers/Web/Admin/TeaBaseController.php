<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLot;
use App\Models\TeaBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Tea base dossier.
 *
 * Read-only at this stage: the list is the dense identity table QA and blenders
 * scan before a formula, the detail is the fingerprint plus the physical lots
 * that can actually be issued. Freeze/unfreeze is the only write action.
 */
class TeaBaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status', 'origin', 'variety']);

        $query = TeaBase::query()->with('material');

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('origin', 'like', "%{$term}%")
                    ->orWhere('variety', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['origin'])) {
            $query->where('origin', $filters['origin']);
        }

        if (! empty($filters['variety'])) {
            $query->where('variety', $filters['variety']);
        }

        $teaBases = $query
            ->orderByRaw("CASE status WHEN 'AVAILABLE' THEN 0 WHEN 'PENDING' THEN 1 WHEN 'FROZEN' THEN 2 ELSE 3 END")
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $availableCounts = $this->availableLotsByMaterial(
            $teaBases->getCollection()
                ->where('status', TeaBase::STATUS_AVAILABLE)
                ->pluck('material_id')
                ->filter()
                ->unique()
                ->values()
                ->all()
        );

        $teaBases->through(fn (TeaBase $teaBase) => [
            'id' => $teaBase->id,
            'code' => $teaBase->code,
            'name' => $teaBase->name,
            'material_name' => $teaBase->material?->name,
            'origin' => $teaBase->origin,
            'variety' => $teaBase->variety,
            'grade' => $teaBase->grade,
            'season' => $teaBase->season,
            'screen_size' => $teaBase->screen_size,
            'moisture_percent' => $teaBase->moisture_percent,
            'pesticide_status' => $teaBase->pesticide_status,
            'heavy_metal_status' => $teaBase->heavy_metal_status,
            'sensory_status' => $teaBase->sensory_status,
            'quality_overall' => $teaBase->qualityOverall(),
            'status' => $teaBase->status,
            'supplier_name' => $teaBase->supplier_name,
            'last_inspected_at' => $teaBase->last_inspected_at?->toIso8601String(),
            'available_lot_count' => $teaBase->status === TeaBase::STATUS_AVAILABLE
                ? (int) ($availableCounts[$teaBase->material_id] ?? 0)
                : 0,
        ]);

        $statusCounts = TeaBase::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('admin/tea-bases/Index', [
            'teaBases' => $teaBases,
            'filters' => $filters,
            'origins' => TeaBase::query()->whereNotNull('origin')->distinct()->orderBy('origin')->pluck('origin'),
            'varieties' => TeaBase::query()->whereNotNull('variety')->distinct()->orderBy('variety')->pluck('variety'),
            'summary' => [
                'total' => (int) $statusCounts->sum(),
                'available' => (int) ($statusCounts[TeaBase::STATUS_AVAILABLE] ?? 0),
                'pending' => (int) ($statusCounts[TeaBase::STATUS_PENDING] ?? 0),
                'frozen' => (int) ($statusCounts[TeaBase::STATUS_FROZEN] ?? 0),
                'retired' => (int) ($statusCounts[TeaBase::STATUS_RETIRED] ?? 0),
            ],
        ]);
    }

    public function show(TeaBase $teaBase)
    {
        $teaBase->load(['material', 'frozenBy']);

        $lots = MaterialLot::query()
            ->with('inspection')
            ->where('material_id', $teaBase->material_id)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        $availableLots = $lots
            ->where('status', MaterialLot::STATUS_RELEASED)
            ->where('quantity_available', '>', 0)
            ->values();

        $blendableLots = $teaBase->status === TeaBase::STATUS_AVAILABLE
            ? $availableLots
            : collect();

        $canBlend = $blendableLots->isNotEmpty();

        return Inertia::render('admin/tea-bases/Show', [
            'teaBase' => [
                'id' => $teaBase->id,
                'code' => $teaBase->code,
                'name' => $teaBase->name,
                'material_name' => $teaBase->material?->name,
                'origin' => $teaBase->origin,
                'variety' => $teaBase->variety,
                'grade' => $teaBase->grade,
                'season' => $teaBase->season,
                'screen_size' => $teaBase->screen_size,
                'supplier_name' => $teaBase->supplier_name,
                'status' => $teaBase->status,
                'quality_overall' => $teaBase->qualityOverall(),
                'last_inspected_at' => $teaBase->last_inspected_at?->toIso8601String(),
                'freeze_reason' => $teaBase->freeze_reason,
                'frozen_at' => $teaBase->frozen_at?->toIso8601String(),
                'frozen_by' => $teaBase->frozenBy?->name,
                'notes' => $teaBase->notes,
                'fingerprint' => $this->fingerprint($teaBase),
            ],
            'lots' => $lots->map(fn (MaterialLot $lot) => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'received_at' => $lot->received_at?->toDateString(),
                'quantity_received' => (float) $lot->quantity_received,
                'quantity_available' => (float) $lot->quantity_available,
                'unit_of_measure' => $lot->unit_of_measure,
                'status' => $lot->status,
                'expiry_date' => $lot->expiry_date?->toDateString(),
                'inspection' => $lot->inspection ? [
                    'id' => $lot->inspection->id,
                    'status' => $lot->inspection->status,
                    'completed_at' => $lot->inspection->completed_at?->toDateString(),
                ] : null,
            ])->all(),
            'available_lot_count' => $blendableLots->count(),
            'can_blend' => $canBlend,
        ]);
    }

    public function freeze(Request $request, TeaBase $teaBase)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($teaBase->status === TeaBase::STATUS_RETIRED) {
            throw ValidationException::withMessages(['reason' => '停用的茶底不能再冻结。']);
        }

        if ($teaBase->status === TeaBase::STATUS_FROZEN) {
            throw ValidationException::withMessages(['reason' => '这份茶底已经冻结。']);
        }

        $teaBase->update([
            'status' => TeaBase::STATUS_FROZEN,
            'freeze_reason' => $data['reason'],
            'frozen_at' => now(),
            'frozen_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', "茶底 {$teaBase->code} 已冻结，拼配入口同时关闭。");
    }

    public function unfreeze(Request $request, TeaBase $teaBase)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($teaBase->status !== TeaBase::STATUS_FROZEN) {
            throw ValidationException::withMessages(['reason' => '只有冻结中的茶底可以解除冻结。']);
        }

        if ($teaBase->qualityOverall() === TeaBase::QUALITY_FAIL) {
            throw ValidationException::withMessages([
                'reason' => '质量指纹仍有不合格项，先完成复检或处置，不能直接解除冻结。',
            ]);
        }

        $note = '解除冻结：'.$data['reason'].'（'.now()->format('Y-m-d H:i').'，'.($request->user()?->name ?? '系统').'）';

        $teaBase->update([
            'status' => $teaBase->qualityOverall() === TeaBase::QUALITY_PASS
                ? TeaBase::STATUS_AVAILABLE
                : TeaBase::STATUS_PENDING,
            'freeze_reason' => null,
            'frozen_at' => null,
            'frozen_by_id' => null,
            'notes' => trim(($teaBase->notes ? $teaBase->notes."\n" : '').$note),
        ]);

        return back()->with('success', "茶底 {$teaBase->code} 已解除冻结，当前状态：{$teaBase->statusLabel()}。");
    }

    private function availableLotsByMaterial(array $materialIds): array
    {
        if ($materialIds === []) {
            return [];
        }

        return MaterialLot::query()
            ->whereIn('material_id', $materialIds)
            ->where('status', MaterialLot::STATUS_RELEASED)
            ->where('quantity_available', '>', 0)
            ->selectRaw('material_id, count(*) as total')
            ->groupBy('material_id')
            ->pluck('total', 'material_id')
            ->all();
    }

    private function fingerprint(TeaBase $teaBase): array
    {
        $moisture = $teaBase->moisture_percent === null
            ? TeaBase::QUALITY_PENDING
            : ((float) $teaBase->moisture_percent < (float) $teaBase->moisture_min
                || (float) $teaBase->moisture_percent > (float) $teaBase->moisture_max
                ? TeaBase::QUALITY_FAIL
                : TeaBase::QUALITY_PASS);

        return [
            [
                'key' => 'moisture',
                'label' => '含水率',
                'value' => $teaBase->moisture_percent !== null ? (float) $teaBase->moisture_percent : null,
                'unit' => '%',
                'min' => (float) $teaBase->moisture_min,
                'max' => (float) $teaBase->moisture_max,
                'limit' => null,
                'status' => $moisture,
                'caption' => '标准 '.$teaBase->moisture_min.'–'.$teaBase->moisture_max.'%',
            ],
            [
                'key' => 'pesticide',
                'label' => '农残',
                'value' => $teaBase->pesticide_value !== null ? (float) $teaBase->pesticide_value : null,
                'unit' => 'mg/kg',
                'min' => null,
                'max' => $teaBase->pesticide_limit !== null ? (float) $teaBase->pesticide_limit : null,
                'limit' => $teaBase->pesticide_limit !== null ? (float) $teaBase->pesticide_limit : null,
                'status' => $teaBase->pesticide_status,
                'caption' => $teaBase->pesticide_limit !== null ? '限量 ≤ '.$teaBase->pesticide_limit.' mg/kg' : '限量未设置',
            ],
            [
                'key' => 'heavy_metal',
                'label' => '重金属',
                'value' => $teaBase->heavy_metal_value !== null ? (float) $teaBase->heavy_metal_value : null,
                'unit' => 'mg/kg',
                'min' => null,
                'max' => $teaBase->heavy_metal_limit !== null ? (float) $teaBase->heavy_metal_limit : null,
                'limit' => $teaBase->heavy_metal_limit !== null ? (float) $teaBase->heavy_metal_limit : null,
                'status' => $teaBase->heavy_metal_status,
                'caption' => $teaBase->heavy_metal_limit !== null ? '限量 ≤ '.$teaBase->heavy_metal_limit.' mg/kg' : '限量未设置',
            ],
            [
                'key' => 'sensory',
                'label' => '感官',
                'value' => $teaBase->sensory_score !== null ? (float) $teaBase->sensory_score : null,
                'unit' => '分',
                'min' => (float) $teaBase->sensory_standard,
                'max' => 100.0,
                'limit' => (float) $teaBase->sensory_standard,
                'status' => $teaBase->sensory_status,
                'caption' => '标准 ≥ '.$teaBase->sensory_standard.' 分',
            ],
        ];
    }
}
