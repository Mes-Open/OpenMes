<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlendFormula;
use App\Models\BlendFormulaComponent;
use App\Models\TeaBase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Blend formula version workbench.
 *
 * Stage one is deliberately read-only: compare the formula, the standard
 * sample and the approval trail, freeze a version, or duplicate it into a new
 * draft. Editing ratios and submitting samples arrive in the next stage; until
 * then the page must not imply that a frozen formula can be changed in place.
 */
class BlendFormulaController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = in_array($status, [...array_keys(BlendFormula::STATUSES), 'IN_REVIEW'], true) ? $status : null;
        $statusFilter = $status === 'IN_REVIEW'
            ? [BlendFormula::STATUS_SAMPLE, BlendFormula::STATUS_LARGE_SAMPLE]
            : ($status ? [$status] : null);

        $teaBaseId = $request->integer('tea_base');
        $sourceTeaBase = $teaBaseId
            ? TeaBase::whereKey($teaBaseId)->first(['id', 'code', 'name'])
            : null;

        $baseQuery = BlendFormula::query()
            ->when($teaBaseId, fn ($query) => $query->whereHas('components', fn ($components) => $components->where('tea_base_id', $teaBaseId)));

        $summaryRows = (clone $baseQuery)->pluck('status');
        $summary = [
            'total' => $summaryRows->count(),
            'draft' => $summaryRows->filter(fn ($value) => $value === BlendFormula::STATUS_DRAFT)->count(),
            'pending' => $summaryRows->filter(fn ($value) => in_array($value, [BlendFormula::STATUS_SAMPLE, BlendFormula::STATUS_LARGE_SAMPLE], true))->count(),
            'approved' => $summaryRows->filter(fn ($value) => $value === BlendFormula::STATUS_APPROVED)->count(),
            'frozen' => $summaryRows->filter(fn ($value) => $value === BlendFormula::STATUS_FROZEN)->count(),
        ];

        $formulas = $baseQuery
            ->with(['productType', 'customer', 'components.teaBase', 'approvedBy'])
            ->when($statusFilter, fn ($query) => $query->whereIn('status', $statusFilter))
            ->orderBy('product_type_id')
            ->orderByDesc('version')
            ->get();

        return Inertia::render('admin/blend-formulas/Index', [
            'formulas' => $formulas->map(fn (BlendFormula $formula) => $this->formulaRow($formula))->all(),
            'summary' => $summary,
            'filters' => ['status' => $status],
            'sourceTeaBase' => $sourceTeaBase ? [
                'id' => $sourceTeaBase->id,
                'code' => $sourceTeaBase->code,
                'name' => $sourceTeaBase->name,
            ] : null,
        ]);
    }

    public function show(BlendFormula $blendFormula)
    {
        $blendFormula->load([
            'components.teaBase',
            'productType',
            'customer',
            'submittedBy',
            'reviewedBy',
            'approvedBy',
            'frozenBy',
        ]);

        $total = (float) $blendFormula->components->sum('standard_ratio');
        $formulaComplete = abs($total - 100.0) <= 0.01;

        $components = $blendFormula->components
            ->map(fn (BlendFormulaComponent $component) => [
                'id' => $component->id,
                'component_name' => $component->component_name,
                'tea_base' => $component->teaBase ? [
                    'id' => $component->teaBase->id,
                    'code' => $component->teaBase->code,
                    'name' => $component->teaBase->name,
                    'origin' => $component->teaBase->origin,
                    'variety' => $component->teaBase->variety,
                    'grade' => $component->teaBase->grade,
                ] : null,
                'standard_ratio' => (float) $component->standard_ratio,
                'tolerance' => (float) $component->tolerance,
                'small_actual' => $component->small_actual !== null ? (float) $component->small_actual : null,
                'large_actual' => $component->large_actual !== null ? (float) $component->large_actual : null,
                'small_deviation' => $component->smallDeviation(),
                'large_deviation' => $component->largeDeviation(),
                'verdict' => $component->verdict(),
                'out_of_tolerance' => $component->verdict() === BlendFormula::RESULT_FAIL,
            ])
            ->all();

        $blockReason = $this->approvalBlockReason($blendFormula, $formulaComplete);

        return Inertia::render('admin/blend-formulas/Show', [
            'formula' => [
                'id' => $blendFormula->id,
                'code' => $blendFormula->code,
                'version' => $blendFormula->version,
                'status' => $blendFormula->status,
                'status_label' => $blendFormula->statusLabel(),
                'product' => $blendFormula->productType?->name,
                'product_code' => $blendFormula->productType?->code,
                'customer' => $blendFormula->customer?->name,
                'customer_code' => $blendFormula->customer?->code,
                'effective_date' => $blendFormula->effective_date?->toDateString(),
                'approved_by' => $blendFormula->approvedBy?->name,
                'approved_at' => $blendFormula->approved_at?->toIso8601String(),
                'standard_sample_code' => $blendFormula->standard_sample_code,
                'standard_sample_approved_at' => $blendFormula->standard_sample_approved_at?->toIso8601String(),
                'standard_sample_summary' => $blendFormula->standard_sample_summary,
                'standard_sample_blind_result' => $blendFormula->standard_sample_blind_result !== null ? (float) $blendFormula->standard_sample_blind_result : null,
                'small_sample_result' => $blendFormula->small_sample_result,
                'large_sample_result' => $blendFormula->large_sample_result,
                'formula_total' => $total,
                'formula_complete' => $formulaComplete,
                'has_standard_sample' => $blendFormula->hasStandardSample(),
                'is_frozen' => $blendFormula->isFrozen(),
                'can_freeze' => $blendFormula->canFreeze(),
                'freeze_reason' => $blendFormula->freeze_reason,
                'frozen_by' => $blendFormula->frozenBy?->name,
                'frozen_at' => $blendFormula->frozen_at?->toIso8601String(),
                'notes' => $blendFormula->notes,
                'approval_block_reason' => $blockReason,
                'timeline' => $this->timeline($blendFormula),
            ],
            'components' => $components,
        ]);
    }

    public function duplicate(BlendFormula $blendFormula)
    {
        $blendFormula->load(['components']);

        $nextVersion = $this->nextVersion($blendFormula);
        $baseCode = preg_replace('/-V\d+$/i', '', $blendFormula->code) ?: $blendFormula->code;
        $code = $baseCode.'-'.$nextVersion;

        if (BlendFormula::where('code', $code)->exists()) {
            $code = $baseCode.'-'.$nextVersion.'-'.now()->format('His');
        }

        $copy = BlendFormula::create([
            'code' => $code,
            'product_type_id' => $blendFormula->product_type_id,
            'customer_id' => $blendFormula->customer_id,
            'version' => $nextVersion,
            'status' => BlendFormula::STATUS_DRAFT,
            'standard_sample_code' => $blendFormula->standard_sample_code,
            'standard_sample_approved_at' => $blendFormula->standard_sample_approved_at,
            'standard_sample_summary' => $blendFormula->standard_sample_summary,
            'standard_sample_blind_result' => $blendFormula->standard_sample_blind_result,
            'small_sample_result' => BlendFormula::RESULT_PENDING,
            'large_sample_result' => BlendFormula::RESULT_PENDING,
            'notes' => '从 '.$blendFormula->code.' 复制，等待小样。',
        ]);

        $blendFormula->components->each(function (BlendFormulaComponent $component) use ($copy) {
            $copy->components()->create([
                'tea_base_id' => $component->tea_base_id,
                'component_name' => $component->component_name,
                'standard_ratio' => $component->standard_ratio,
                'tolerance' => $component->tolerance,
                'small_actual' => null,
                'large_actual' => null,
                'sort_order' => $component->sort_order,
            ]);
        });

        return redirect()
            ->route('admin.blend-formulas.show', $copy)
            ->with('success', "已从 {$blendFormula->code} 复制出 {$copy->code}。");
    }

    public function freeze(Request $request, BlendFormula $blendFormula)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($blendFormula->isFrozen()) {
            throw ValidationException::withMessages(['reason' => '这一版已经冻结，只能复制新版本。']);
        }

        if (! $blendFormula->canFreeze()) {
            throw ValidationException::withMessages(['reason' => '草稿还不能冻结；先完成小样和大样评审。']);
        }

        $blendFormula->update([
            'status' => BlendFormula::STATUS_FROZEN,
            'freeze_reason' => $data['reason'],
            'frozen_by_id' => $request->user()?->id,
            'frozen_at' => now(),
        ]);

        return back()->with('success', "拼配版本 {$blendFormula->code} 已冻结，比例为只读。");
    }

    private function formulaRow(BlendFormula $formula): array
    {
        $components = $formula->components;
        $total = (float) $components->sum('standard_ratio');
        $failed = $components->filter(fn (BlendFormulaComponent $component) => $component->verdict() === BlendFormula::RESULT_FAIL)->count();

        return [
            'id' => $formula->id,
            'code' => $formula->code,
            'version' => $formula->version,
            'status' => $formula->status,
            'status_label' => $formula->statusLabel(),
            'product' => $formula->productType?->name,
            'product_code' => $formula->productType?->code,
            'customer' => $formula->customer?->name,
            'customer_code' => $formula->customer?->code,
            'standard_sample_code' => $formula->standard_sample_code,
            'has_standard_sample' => $formula->hasStandardSample(),
            'small_sample_result' => $formula->small_sample_result,
            'large_sample_result' => $formula->large_sample_result,
            'effective_date' => $formula->effective_date?->toDateString(),
            'approved_by' => $formula->approvedBy?->name,
            'approved_at' => $formula->approved_at?->toIso8601String(),
            'component_count' => $components->count(),
            'formula_total' => $total,
            'formula_complete' => abs($total - 100.0) <= 0.01,
            'out_of_tolerance_count' => $failed,
            'is_frozen' => $formula->isFrozen(),
            'notes' => $formula->notes,
        ];
    }

    private function timeline(BlendFormula $formula): array
    {
        return [
            [
                'key' => 'submit',
                'label' => '提交小样',
                'done' => $formula->submitted_at !== null,
                'actor' => $formula->submittedBy?->name,
                'at' => $formula->submitted_at?->toIso8601String(),
                'note' => $formula->submitted_at ? '配方与小样送评' : '等待提交',
            ],
            [
                'key' => 'review',
                'label' => '小样评审',
                'done' => $formula->reviewed_at !== null,
                'actor' => $formula->reviewedBy?->name,
                'at' => $formula->reviewed_at?->toIso8601String(),
                'note' => $formula->reviewed_at ? '评茶师完成评审' : '等待评审',
            ],
            [
                'key' => 'approve',
                'label' => '批准',
                'done' => $formula->approved_at !== null,
                'actor' => $formula->approvedBy?->name,
                'at' => $formula->approved_at?->toIso8601String(),
                'note' => $formula->approved_at ? 'QA 批准放大' : '未批准',
            ],
            [
                'key' => 'freeze',
                'label' => '冻结',
                'done' => $formula->frozen_at !== null,
                'actor' => $formula->frozenBy?->name,
                'at' => $formula->frozen_at?->toIso8601String(),
                'note' => $formula->frozen_at ? '比例锁定，只能复制新版本' : '未冻结',
            ],
        ];
    }

    private function approvalBlockReason(BlendFormula $formula, bool $formulaComplete): ?string
    {
        if (in_array($formula->status, [BlendFormula::STATUS_APPROVED, BlendFormula::STATUS_FROZEN], true)) {
            return null;
        }

        if (! $formulaComplete) {
            return '配方合计不是 100%，不能进入小样。';
        }

        if (! $formula->hasStandardSample()) {
            return '先指定并批准标准样；没有标准样不能批准。';
        }

        if ($formula->submitted_at === null) {
            return '先提交小样；当前还是草稿。';
        }

        if ($formula->small_sample_result !== BlendFormula::RESULT_PASS) {
            return $formula->small_sample_result === BlendFormula::RESULT_FAIL
                ? '小样超差，先调整配方再提交。'
                : '小样还没判定合格。';
        }

        if ($formula->large_sample_result !== BlendFormula::RESULT_PASS) {
            return $formula->large_sample_result === BlendFormula::RESULT_FAIL
                ? '大样超差，不能批准。'
                : '大样还没做；批准前要完成放大对照。';
        }

        return null;
    }

    private function nextVersion(BlendFormula $formula): string
    {
        $siblings = BlendFormula::where('product_type_id', $formula->product_type_id)
            ->where('customer_id', $formula->customer_id)
            ->pluck('version');

        $max = $siblings
            ->map(fn ($version) => (int) preg_replace('/\D+/', '', (string) $version))
            ->max() ?? 0;

        return 'V'.str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }
}
