<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SensoryEvaluation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Blind sensory session: one sample at a time, no product/customer, and only
 * the current evaluator's own scores. The standard comparison is withheld
 * until every assigned sample is submitted.
 */
class SensoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $evaluations = SensoryEvaluation::query()
            ->where('evaluator_id', $user->id)
            ->orderBy('sample_sequence')
            ->orderBy('id')
            ->get();

        $submitted = $evaluations->filter(fn (SensoryEvaluation $evaluation) => $evaluation->isSubmitted())->count();
        $pending = $evaluations->first(fn (SensoryEvaluation $evaluation) => ! $evaluation->isSubmitted());

        if ($evaluations->isEmpty()) {
            return Inertia::render('admin/sensory/Index', [
                'state' => 'empty',
                'progress' => ['current' => 0, 'total' => 0, 'submitted' => 0],
            ]);
        }

        if ($pending === null) {
            return Inertia::render('admin/sensory/Index', [
                'state' => 'completed',
                'progress' => ['current' => $evaluations->count(), 'total' => $evaluations->count(), 'submitted' => $submitted],
                'summary' => $evaluations->map(fn (SensoryEvaluation $evaluation) => $this->summaryRow($evaluation))->all(),
            ]);
        }

        return Inertia::render('admin/sensory/Index', [
            'state' => 'sample',
            'progress' => [
                'current' => $submitted + 1,
                'total' => $evaluations->count(),
                'submitted' => $submitted,
            ],
            'sample' => [
                'id' => $pending->id,
                'blind_code' => $pending->blind_code,
                'sample_sequence' => $pending->sample_sequence,
            ],
        ]);
    }

    public function submit(Request $request, SensoryEvaluation $sensoryEvaluation)
    {
        if ((int) $sensoryEvaluation->evaluator_id !== (int) $request->user()->id) {
            abort(403);
        }

        if ($sensoryEvaluation->isSubmitted()) {
            throw ValidationException::withMessages(['scores' => '这一份已经锁定，不能回改。']);
        }

        $data = $request->validate([
            'appearance_score' => ['required', 'integer', 'between:0,10'],
            'color_score' => ['required', 'integer', 'between:0,10'],
            'aroma_score' => ['required', 'integer', 'between:0,10'],
            'taste_score' => ['required', 'integer', 'between:0,10'],
            'leaf_score' => ['required', 'integer', 'between:0,10'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $sensoryEvaluation->update([
            'appearance_score' => $data['appearance_score'],
            'color_score' => $data['color_score'],
            'aroma_score' => $data['aroma_score'],
            'taste_score' => $data['taste_score'],
            'leaf_score' => $data['leaf_score'],
            'comment' => $data['comment'] ?? null,
            'status' => SensoryEvaluation::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $remaining = SensoryEvaluation::where('evaluator_id', $request->user()->id)
            ->where('status', SensoryEvaluation::STATUS_PENDING)
            ->count();

        return redirect()
            ->route('admin.sensory.index')
            ->with('success', $remaining > 0
                ? "盲码 {$sensoryEvaluation->blind_code} 已锁定，进入下一份。"
                : '全部盲样已提交；现在可以对照标准样。');
    }

    private function summaryRow(SensoryEvaluation $evaluation): array
    {
        $total = $evaluation->scoreTotal();
        $standardTotal = $evaluation->standardTotal();

        return [
            'id' => $evaluation->id,
            'blind_code' => $evaluation->blind_code,
            'sample_sequence' => $evaluation->sample_sequence,
            'scores' => [
                'appearance_score' => $evaluation->appearance_score,
                'color_score' => $evaluation->color_score,
                'aroma_score' => $evaluation->aroma_score,
                'taste_score' => $evaluation->taste_score,
                'leaf_score' => $evaluation->leaf_score,
            ],
            'total' => $total,
            'standard_sample_code' => $evaluation->standard_sample_code,
            'standard_scores' => $evaluation->standard_scores,
            'standard_total' => $standardTotal,
            'delta' => $total !== null && $standardTotal !== null ? $total - $standardTotal : null,
            'comment' => $evaluation->comment,
            'submitted_at' => $evaluation->submitted_at?->toIso8601String(),
        ];
    }
}
