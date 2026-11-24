<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchLabTest;
use App\Models\BatchReleaseCheck;
use App\Models\CoaDocument;
use App\Services\Quality\CoaService;
use App\Support\DownloadName;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * COA & batch release workbench.
 *
 * The old controller only rendered a draft PDF. This one owns the controlled
 * document lifecycle and the release gate that must be satisfied before a lot
 * can be shipped: draft -> QA review -> approved -> issued -> void / reissue.
 */
class CoaController extends Controller
{
    public function __construct(private readonly CoaService $coa)
    {
    }

    public function index(Request $request)
    {
        $batchQuery = Batch::query()
            ->with([
                'workOrder.customer',
                'workOrder.productType',
                'workOrder.productRevision',
                'coaDocument',
                'releasedBy',
                'qualityChecks' => fn ($query) => $query->with('samples')->latest('checked_at')->limit(1),
            ])
            ->whereHas('workOrder');

        // The COA workbench is a quality queue, not a dump of every historical
        // batch. By default it only shows lots that already have quality
        // evidence or a COA document; `?all=1` opens the full list.
        if (! $request->boolean('all')) {
            $batchQuery->where(function ($query) {
                $query->whereHas('qualityChecks')->orWhereHas('coaDocument');
            });
        }

        $batches = $batchQuery
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Batch $batch) {
                $gate = $this->coa->releaseGate($batch);

                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'lot_number' => $batch->lot_number,
                    'status' => $batch->status,
                    'produced_qty' => (float) $batch->produced_qty,
                    'completed_at' => $batch->completed_at?->toIso8601String(),
                    'released_at' => $batch->released_at?->toIso8601String(),
                    'released_by' => $batch->releasedBy?->name,
                    'product' => $batch->workOrder?->productType?->name,
                    'product_code' => $batch->workOrder?->productType?->code,
                    'customer' => $batch->workOrder?->customer?->name ?? $batch->workOrder?->customer_name,
                    'order_no' => $batch->workOrder?->order_no,
                    'latest_check' => $batch->qualityChecks->first() ? [
                        'checked_at' => $batch->qualityChecks->first()->checked_at?->toIso8601String(),
                        'all_passed' => (bool) $batch->qualityChecks->first()->all_passed,
                    ] : null,
                    'release_gate' => $gate,
                    'coa' => $batch->coaDocument ? [
                        'id' => $batch->coaDocument->id,
                        'number' => $batch->coaDocument->coa_number,
                        'status' => $batch->coaDocument->status,
                        'version' => $batch->coaDocument->current_version,
                        'issued_at' => $batch->coaDocument->issued_at?->toIso8601String(),
                    ] : null,
                ];
            });

        $summaryQuery = Batch::query()
            ->whereHas('workOrder')
            ->where(function ($query) {
                $query->whereHas('qualityChecks')->orWhereHas('coaDocument');
            });

        $summary = [
            'pending_release' => (clone $summaryQuery)
                ->where('status', Batch::STATUS_DONE)
                ->whereNull('released_at')
                ->count(),
            'in_review' => CoaDocument::query()
                ->whereIn('status', [CoaDocument::STATUS_QA_REVIEW, CoaDocument::STATUS_APPROVED])
                ->count(),
            'issued' => CoaDocument::query()
                ->where('status', CoaDocument::STATUS_ISSUED)
                ->count(),
        ];

        return Inertia::render('admin/coa/Index', [
            'batches' => $batches,
            'summary' => $summary,
            'filters' => $request->only(['status', 'all']),
        ]);
    }

    public function show(Batch $batch)
    {
        $document = $batch->coaDocument;
        $batch->load([
            'workOrder.customer',
            'workOrder.productType',
            'workOrder.productRevision',
            'workOrder.line',
            'qualityChecks.samples',
            'qualityChecks.checkedBy',
            'packagingChecklist',
            'releasedBy',
            'electronicSignatures.user',
            'releaseChecks.checkedBy',
            'labTests.recordedBy',
        ]);

        if ($document) {
            $document->load(['versions', 'signatures.user', 'preparedBy', 'approvedBy', 'issuedBy', 'voidedBy']);
        }

        return Inertia::render('admin/coa/Show', [
            'batch' => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'lot_number' => $batch->lot_number,
                'status' => $batch->status,
                'produced_qty' => (float) $batch->produced_qty,
                'completed_at' => $batch->completed_at?->toIso8601String(),
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'released_at' => $batch->released_at?->toIso8601String(),
                'released_by' => $batch->releasedBy?->name,
                'order_no' => $batch->workOrder?->order_no,
                'product' => $batch->workOrder?->productType?->name,
                'product_code' => $batch->workOrder?->productType?->code,
                'customer' => $batch->workOrder?->customer?->name ?? $batch->workOrder?->customer_name,
                'line' => $batch->workOrder?->line?->name,
            ],
            'document' => $document ? [
                'id' => $document->id,
                'coa_number' => $document->coa_number,
                'status' => $document->status,
                'current_version' => $document->current_version,
                'customer_name' => $document->customer_name,
                'customer_code' => $document->customer_code,
                'product_name' => $document->product_name,
                'product_code' => $document->product_code,
                'revision_code' => $document->revision_code,
                'lot_number' => $document->lot_number,
                'prepared_at' => $document->prepared_at?->toIso8601String(),
                'prepared_by' => $document->preparedBy?->name,
                'submitted_at' => $document->submitted_at?->toIso8601String(),
                'approved_at' => $document->approved_at?->toIso8601String(),
                'approved_by' => $document->approvedBy?->name,
                'issued_at' => $document->issued_at?->toIso8601String(),
                'issued_by' => $document->issuedBy?->name,
                'voided_at' => $document->voided_at?->toIso8601String(),
                'voided_by' => $document->voidedBy?->name,
                'void_reason' => $document->void_reason,
                'specification' => $document->specification_snapshot,
                'quality' => $document->quality_snapshot,
            ] : null,
            'versions' => $document ? $document->versions->map(fn ($version) => [
                'id' => $version->id,
                'version' => $version->version,
                'status' => $version->status,
                'change_reason' => $version->change_reason,
                'prepared_at' => $version->prepared_at?->toIso8601String(),
                'approved_at' => $version->approved_at?->toIso8601String(),
                'issued_at' => $version->issued_at?->toIso8601String(),
                'voided_at' => $version->voided_at?->toIso8601String(),
                'void_reason' => $version->void_reason,
            ])->values() : [],
            'releaseGate' => $this->coa->releaseGate($batch),
            'issueGate' => $this->coa->issueGate($batch, $document),
            'separation' => $this->separation($batch, $document),
            'labTest' => $batch->labTests->first() ? $this->labTestPayload($batch->labTests->first()) : null,
            'labTestRequired' => $this->coa->requiresExternalLab($batch),
            'releaseChecks' => $batch->releaseChecks
                ->groupBy('check_type')
                ->map(fn ($items) => $items->first())
                ->map(fn (BatchReleaseCheck $check) => [
                    'type' => $check->check_type,
                    'label' => BatchReleaseCheck::label($check->check_type),
                    'status' => $check->status,
                    'checked_at' => $check->checked_at?->toIso8601String(),
                    'checked_by' => $check->checkedBy?->name,
                    'evidence' => $check->evidence,
                ]),
            'batchSignatures' => $batch->electronicSignatures->map(fn ($signature) => [
                'id' => $signature->id,
                'meaning' => $signature->meaning,
                'signed_at' => $signature->signed_at?->toIso8601String(),
                'user' => $signature->user?->name,
                'roles' => $signature->user?->getRoleNames()->all() ?? [],
                'record_hash' => $signature->record_hash,
                'verified' => $this->coa->verifySignature($signature),
            ])->values(),
            'documentSignatures' => $document ? $document->signatures->map(fn ($signature) => [
                'id' => $signature->id,
                'meaning' => $signature->meaning,
                'signed_at' => $signature->signed_at?->toIso8601String(),
                'user' => $signature->user?->name,
                'roles' => $signature->user?->getRoleNames()->all() ?? [],
                'record_hash' => $signature->record_hash,
                'verified' => $this->coa->verifySignature($signature),
            ])->values() : [],
        ]);
    }

    private function labTestPayload(BatchLabTest $labTest): array
    {
        return [
            'id' => $labTest->id,
            'lab_name' => $labTest->lab_name,
            'report_number' => $labTest->report_number,
            'sample_number' => $labTest->sample_number,
            'sampled_at' => $labTest->sampled_at?->toDateString(),
            'issued_at' => $labTest->issued_at?->toDateString(),
            'overall_conclusion' => $labTest->overall_conclusion,
            'items' => $labTest->items,
            'notes' => $labTest->notes,
            'recorded_by' => $labTest->recordedBy?->name,
            'recorded_at' => $labTest->created_at?->toIso8601String(),
            'attachment_name' => $labTest->attachment_path ? basename($labTest->attachment_path) : null,
            'attachment_url' => $labTest->attachment_path
                ? route('admin.coa.lab-tests.attachment', ['batch' => $labTest->batch_id, 'labTest' => $labTest->id])
                : null,
        ];
    }

    private function separation(Batch $batch, ?CoaDocument $document): array
    {
        $releasedBy = $batch->releasedBy;
        $approvedBy = $document?->approvedBy;
        $passed = $releasedBy !== null
            && $approvedBy !== null
            && (int) $releasedBy->id !== (int) $approvedBy->id;

        return [
            'released_by' => $releasedBy?->name,
            'released_roles' => $releasedBy?->getRoleNames()->all() ?? [],
            'approved_by' => $approvedBy?->name,
            'approved_roles' => $approvedBy?->getRoleNames()->all() ?? [],
            'passed' => $passed,
            'detail' => match (true) {
                $releasedBy === null && $approvedBy === null => '尚未放行，也未批准 COA。',
                $releasedBy === null => '尚未放行批次，不能批准 COA。',
                $approvedBy === null => '批次已放行，等待 QA 复核批准。',
                $passed => '放行人与批准人不同，双人复核成立。',
                default => '放行人同时批准了 COA，双人复核不成立。',
            },
        ];
    }

    public function draft(Batch $batch)
    {
        $this->coa->draft($batch);

        return back()->with('success', 'COA 草稿已生成，可以提交 QA 复核。');
    }

    public function release(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->coa->release($batch, $request->user(), $data['notes'] ?? null);

        return back()->with('success', '批次已放行，放行签名已记录。');
    }

    public function submit(Request $request, Batch $batch)
    {
        $document = $this->coa->draft($batch);
        $this->coa->submit($document, $request->user());

        return back()->with('success', 'COA 已提交 QA 复核。');
    }

    public function approve(Request $request, Batch $batch)
    {
        $document = $this->coa->draft($batch);
        $this->coa->approve($document, $request->user());

        return back()->with('success', 'COA 已批准，批准签名已记录。');
    }

    public function issue(Request $request, Batch $batch)
    {
        $document = $this->coa->draft($batch);
        $this->coa->issue($document, $request->user());

        return back()->with('success', 'COA 已签发，版本快照与签名已冻结。');
    }

    public function void(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $document = $this->coa->draft($batch);
        $this->coa->void($document, $request->user(), $data['reason']);

        return back()->with('success', 'COA 已作废，旧版本保留可审计。');
    }

    public function reissue(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $document = $this->coa->draft($batch);
        $this->coa->reissue($document, $request->user(), $data['reason']);

        return back()->with('success', '已创建新版本 COA 草稿，原版本保留。');
    }

    public function recordLabTest(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'lab_name' => ['required', 'string', 'max:255'],
            'report_number' => ['required', 'string', 'max:100'],
            'sample_number' => ['nullable', 'string', 'max:100'],
            'sampled_at' => ['nullable', 'date'],
            'issued_at' => ['required', 'date'],
            'overall_conclusion' => ['required', Rule::in(BatchLabTest::CONCLUSIONS)],
            'items_text' => ['required', 'string', 'max:6000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $items = collect(preg_split('/\R/u', $data['items_text']))
            ->map(function (string $line, int $index) use ($data) {
                $line = trim($line);
                if ($line === '') {
                    return null;
                }

                $parts = array_map('trim', explode('|', $line));
                if (count($parts) < 5 || $parts[0] === '' || $parts[1] === '') {
                    throw ValidationException::withMessages([
                        'items_text' => '第 '.($index + 1).' 行格式不正确，应为：类别|项目|结果|限值|判定。',
                    ]);
                }

                $conclusion = in_array(strtoupper($parts[4]), ['PASS', '合格'], true) ? '合格' : '不合格';

                return [
                    'category' => $parts[0],
                    'name' => $parts[1],
                    'result' => $parts[2],
                    'limit' => $parts[3],
                    'conclusion' => $conclusion,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            throw ValidationException::withMessages([
                'items_text' => '至少登记一条外检项目。',
            ]);
        }

        BatchLabTest::updateOrCreate(
            ['batch_id' => $batch->id, 'report_number' => $data['report_number']],
            [
                'lab_name' => $data['lab_name'],
                'sample_number' => $data['sample_number'] ?? null,
                'sampled_at' => $data['sampled_at'] ?? null,
                'issued_at' => $data['issued_at'],
                'overall_conclusion' => $data['overall_conclusion'],
                'items' => $items,
                'notes' => $data['notes'] ?? null,
                'recorded_by_id' => $request->user()?->id,
            ]
        );

        return back()->with('success', __('外检报告已登记：:no。', ['no' => $data['report_number']]));
    }

    public function uploadLabAttachment(Request $request, Batch $batch, BatchLabTest $labTest)
    {
        abort_unless((int) $labTest->batch_id === (int) $batch->id, 404);

        $request->validate([
            'attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ]);

        if ($labTest->attachment_path) {
            Storage::disk('local')->delete($labTest->attachment_path);
        }

        $file = $request->file('attachment');
        $dir = 'lab-tests/'.($batch->tenant_id ?: 'default').'/'.$batch->id;
        $name = $labTest->report_number.'-'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $name, 'local');

        $labTest->forceFill(['attachment_path' => $path])->save();

        return back()->with('success', __('外检报告附件已上传。'));
    }

    public function downloadLabAttachment(Batch $batch, BatchLabTest $labTest)
    {
        abort_unless((int) $labTest->batch_id === (int) $batch->id, 404);
        abort_unless($labTest->attachment_path && Storage::disk('local')->exists($labTest->attachment_path), 404);

        return Storage::disk('local')->download($labTest->attachment_path, basename($labTest->attachment_path));
    }

    public function recordCheck(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'check_type' => ['required', Rule::in(BatchReleaseCheck::TYPES)],
            'status' => ['required', Rule::in(BatchReleaseCheck::STATUSES)],
            'evidence' => ['nullable', 'string', 'max:1000'],
        ]);

        BatchReleaseCheck::create([
            'batch_id' => $batch->id,
            'check_type' => $data['check_type'],
            'status' => $data['status'],
            'checked_by_id' => $request->user()?->id,
            'checked_at' => now(),
            'evidence' => $data['evidence'] ?? null,
        ]);

        return back()->with('success', __('批记录确认已登记：:type。', [
            'type' => BatchReleaseCheck::label($data['check_type']),
        ]));
    }

    public function pdf(Batch $batch)
    {
        $document = $batch->coaDocument;
        if (! $document) {
            abort(404, '该批次尚未生成 COA，请先在 COA 工作台生成草稿。');
        }
        $document->load(['versions', 'approvedBy', 'issuedBy', 'voidedBy']);

        $batch->load([
            'workOrder.customer',
            'workOrder.productType',
            'workOrder.productRevision',
            'workOrder.line',
            'qualityChecks.samples',
            'qualityChecks.checkedBy',
            'releasedBy',
        ]);

        $latestVersion = $document->versions
            ->where('version', $document->current_version)
            ->first();
        $snapshot = $latestVersion?->snapshot ?? [];
        $quality = $snapshot['quality'] ?? $document->quality_snapshot;
        $specification = $snapshot['specification'] ?? $document->specification_snapshot;
        $labTest = $snapshot['external_lab'] ?? null;
        $externalLabRequired = (bool) ($snapshot['external_lab_required'] ?? false);

        $latestCheck = $batch->qualityChecks->first();
        $samples = collect($quality['samples'] ?? [])
            ->map(fn (array $sample) => (object) $sample)
            ->values();
        $coaNo = $document->coa_number;
        $coaVersion = 'V'.str_pad((string) $document->current_version, 2, '0', STR_PAD_LEFT);
        $isReleased = $batch->released_at !== null;
        $isIssued = $document->status === CoaDocument::STATUS_ISSUED;
        $isVoid = $document->status === CoaDocument::STATUS_VOID;
        $allPassed = $quality['all_passed'] ?? $latestCheck?->all_passed;
        $generatedAt = $document->issued_at ?? $document->approved_at ?? $document->prepared_at ?? now();
        $changeReason = $latestVersion?->change_reason;

        $pdf = Pdf::loadView('admin.reports.coa-pdf', [
            'batch' => $batch,
            'workOrder' => $batch->workOrder,
            'latestCheck' => $latestCheck,
            'samples' => $samples,
            'coaNo' => $coaNo,
            'coaVersion' => $coaVersion,
            'allPassed' => $allPassed,
            'isReleased' => $isReleased,
            'isIssued' => $isIssued,
            'isVoid' => $isVoid,
            'document' => $document,
            'specification' => $specification,
            'qualitySnapshot' => $quality,
            'labTest' => $labTest,
            'externalLabRequired' => $externalLabRequired,
            'generatedAt' => $generatedAt,
            'changeReason' => $changeReason,
        ])->setPaper('a4', 'portrait');

        // Embed only the glyphs actually used; the bundled CJK font is large.
        $pdf->setOption('enable_font_subsetting', true);
        $this->registerCjkFont($pdf);

        return $pdf->download('COA-'.DownloadName::safe($batch->lot_number ?? 'B'.$batch->id).'.pdf');
    }

    /**
     * Register the bundled CJK font before render.
     *
     * dompdf's protocol whitelist deliberately excludes file://, so a CSS
     * @font-face pointing at a local .ttf is rejected. Registering the font
     * through FontMetrics is the supported local path and keeps the render
     * pipeline free of remote URLs.
     */
    private function registerCjkFont(\Barryvdh\DomPDF\PDF $pdf): void
    {
        $regular = public_path('fonts/NotoSansSC-Regular.ttf');
        $bold = public_path('fonts/NotoSansSC-Bold.ttf');
        if (! is_file($regular)) {
            return;
        }

        $metrics = $pdf->getDomPDF()->getFontMetrics();
        $metrics->registerFont(['family' => 'CoaNotoSans', 'style' => 'normal', 'weight' => 'normal'], $regular);
        if (is_file($bold)) {
            $metrics->registerFont(['family' => 'CoaNotoSans', 'style' => 'normal', 'weight' => 'bold'], $bold);
        }
    }
}
