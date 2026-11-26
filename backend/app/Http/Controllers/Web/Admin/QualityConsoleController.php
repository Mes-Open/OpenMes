<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\CoaDocument;
use App\Models\Issue;
use App\Services\Quality\CoaService;
use Inertia\Inertia;

/**
 * Quality command centre.
 *
 * It is a decision screen, not another batch list: each row answers one
 * question — what blocks this lot, and what is the next signature. The batch
 * console remains the place where the evidence is filled in.
 */
class QualityConsoleController extends Controller
{
    public function __construct(private readonly CoaService $coa)
    {
    }

    public function index()
    {
        $batches = Batch::query()
            ->with([
                'workOrder.customer',
                'workOrder.productType',
                'workOrder.productRevision',
                'coaDocument',
                'qualityChecks.template',
                'labTests',
                'packagingChecklist',
                'releaseChecks',
            ])
            ->whereHas('workOrder')
            ->where('status', '!=', Batch::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $queue = $batches
            ->map(fn (Batch $batch) => $this->queueRow($batch))
            ->reject(fn (array $row) => $row['next_action']['key'] === 'issued')
            ->sortBy('sort_key')
            ->values()
            ->take(8)
            ->all();

        $blockingIssues = Issue::query()
            ->with(['issueType', 'workOrder', 'assignedTo'])
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->where('blocks_work_order', true)
            ->orderByDesc('reported_at')
            ->limit(6)
            ->get()
            ->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'title' => $issue->title,
                'status' => $issue->status,
                'type' => $issue->issueType?->name,
                'source' => $issue->work_order_id ? '工单' : '来料 / 供应商',
                'order_no' => $issue->workOrder?->order_no,
                'assigned_to' => $issue->assignedTo?->name,
                'reported_at' => $issue->reported_at?->toIso8601String(),
                'href' => '/admin/issues',
            ])
            ->all();

        $recentIssued = CoaDocument::query()
            ->with(['batch.workOrder.customer', 'batch.workOrder.productType'])
            ->where('status', CoaDocument::STATUS_ISSUED)
            ->orderByDesc('issued_at')
            ->limit(5)
            ->get()
            ->map(fn (CoaDocument $document) => [
                'id' => $document->id,
                'coa_number' => $document->coa_number,
                'version' => $document->current_version,
                'issued_at' => $document->issued_at?->toIso8601String(),
                'batch_id' => $document->batch?->id,
                'lot_number' => $document->batch?->lot_number,
                'product' => $document->batch?->workOrder?->productType?->name,
                'customer' => $document->batch?->workOrder?->customer?->name
                    ?? $document->batch?->workOrder?->customer_name,
            ])
            ->all();

        $summary = [
            'pending_release' => Batch::query()
                ->whereHas('workOrder')
                ->where('status', Batch::STATUS_DONE)
                ->whereNull('released_at')
                ->count(),
            'qa_review' => CoaDocument::query()
                ->where('status', CoaDocument::STATUS_QA_REVIEW)
                ->count(),
            'approved' => CoaDocument::query()
                ->where('status', CoaDocument::STATUS_APPROVED)
                ->count(),
            'blocking_issues' => Issue::query()
                ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                ->where('blocks_work_order', true)
                ->count(),
            'issued_today' => CoaDocument::query()
                ->where('status', CoaDocument::STATUS_ISSUED)
                ->whereDate('issued_at', now()->toDateString())
                ->count(),
        ];

        return Inertia::render('admin/quality/Index', [
            'summary' => $summary,
            'queue' => $queue,
            'blockingIssues' => $blockingIssues,
            'recentIssued' => $recentIssued,
        ]);
    }

    private function queueRow(Batch $batch): array
    {
        $document = $batch->coaDocument;
        $releaseGate = $this->coa->releaseGate($batch);
        $issueGate = $this->coa->issueGate($batch, $document);
        $nextAction = $this->nextAction($batch, $document, $releaseGate, $issueGate);

        return [
            'id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'lot_number' => $batch->lot_number,
            'status' => $batch->status,
            'produced_qty' => (float) $batch->produced_qty,
            'unit_of_measure' => $batch->workOrder?->productType?->unit_of_measure ?? 'kg',
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'released_at' => $batch->released_at?->toIso8601String(),
            'product' => $batch->workOrder?->productType?->name,
            'customer' => $batch->workOrder?->customer?->name ?? $batch->workOrder?->customer_name,
            'order_no' => $batch->workOrder?->order_no,
            'release_gate' => [
                'passed' => (bool) ($releaseGate['passed'] ?? false),
                'passed_count' => (int) ($releaseGate['passed_count'] ?? 0),
                'total' => (int) ($releaseGate['total'] ?? 0),
                'failed_labels' => $releaseGate['failed_labels'] ?? '',
            ],
            'issue_gate' => [
                'passed' => (bool) ($issueGate['passed'] ?? false),
                'passed_count' => (int) ($issueGate['passed_count'] ?? 0),
                'total' => (int) ($issueGate['total'] ?? 0),
                'failed_labels' => $issueGate['failed_labels'] ?? '',
            ],
            'coa' => $document ? [
                'id' => $document->id,
                'status' => $document->status,
                'version' => $document->current_version,
                'number' => $document->coa_number,
            ] : null,
            'next_action' => $nextAction,
            'sort_key' => sprintf('%02d-%06d', $nextAction['priority'], 999999 - $batch->id),
        ];
    }

    private function nextAction(Batch $batch, ?CoaDocument $document, array $releaseGate, array $issueGate): array
    {
        $href = "/admin/coa/{$batch->id}";
        $missingRelease = max(($releaseGate['total'] ?? 0) - ($releaseGate['passed_count'] ?? 0), 0);
        $missingIssue = max(($issueGate['total'] ?? 0) - ($issueGate['passed_count'] ?? 0), 0);

        if (! $batch->released_at) {
            if ($releaseGate['passed'] ?? false) {
                return $this->action('release', '确认放行', 'primary', 1, '放行门已全部通过，进入批次控制台签字。', $href);
            }

            return $this->action(
                'release_blocked',
                '补放行门',
                'warning',
                4,
                "还差 {$missingRelease} 项：".($releaseGate['failed_labels'] ?? '—'),
                $href
            );
        }

        if (! $document) {
            return $this->action('draft', '生成 COA', 'primary', 6, '批次已放行，生成草稿并冻结规格快照。', $href);
        }

        return match ($document->status) {
            CoaDocument::STATUS_DRAFT => $document->inspector_id
                ? $this->action('submit', '提交 QA 复核', 'primary', 5, '检验人已签字，提交 QA 复核。', $href)
                : $this->action('inspect', '检验人签字', 'primary', 5, '草稿还没有检验人签名。', $href),
            CoaDocument::STATUS_QA_REVIEW => $this->action('approve', '批准 COA', 'primary', 2, 'QA 复核中，批准后进入待签发。', $href),
            CoaDocument::STATUS_APPROVED => ($issueGate['passed'] ?? false)
                ? $this->action('issue', '签发 COA', 'primary', 3, '签发门已全部通过，可以对外签发。', $href)
                : $this->action(
                    'issue_blocked',
                    '补签发门',
                    'warning',
                    4,
                    "还差 {$missingIssue} 项：".($issueGate['failed_labels'] ?? '—'),
                    $href
                ),
            CoaDocument::STATUS_ISSUED => $this->action('issued', '查看 COA', 'muted', 9, '已签发。', $href),
            CoaDocument::STATUS_VOID => $this->action('reissue', '重签', 'warning', 7, '旧版本已作废，需要填写原因后创建新版本。', $href),
            default => $this->action('inspect', '打开控制台', 'muted', 8, '需要人工检查 COA 状态。', $href),
        };
    }

    private function action(string $key, string $label, string $tone, int $priority, string $hint, string $href): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'tone' => $tone,
            'priority' => $priority,
            'hint' => $hint,
            'href' => $href,
        ];
    }
}
