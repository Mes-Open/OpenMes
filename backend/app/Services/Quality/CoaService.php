<?php

namespace App\Services\Quality;

use App\Models\Batch;
use App\Models\BatchLabTest;
use App\Models\BatchReleaseCheck;
use App\Models\BatchWeighingRecord;
use App\Models\CoaDocument;
use App\Models\CoaDocumentVersion;
use App\Models\ElectronicSignature;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Batch release and COA lifecycle.
 *
 * Two gates are deliberately separate:
 *  - release gate: production and quality evidence (can the batch be released?)
 *  - issue gate:   release evidence + an approved COA (can the document go out?)
 *
 * A COA draft may exist before release, but it cannot be approved or issued
 * until the batch is released. That prevents a customer-facing document from
 * getting ahead of QA.
 */
class CoaService
{
    public function draft(Batch $batch): CoaDocument
    {
        $batch->loadMissing([
            'workOrder.customer',
            'workOrder.productType',
            'workOrder.productRevision',
            'qualityChecks.samples',
            'qualityChecks.template',
            'qualityChecks.checkedBy',
            'packagingChecklist',
            'coaDocument',
        ]);

        if ($batch->coaDocument) {
            return $batch->coaDocument;
        }

        $existing = $batch->coaDocument()->first();
        if ($existing) {
            $batch->setRelation('coaDocument', $existing);

            return $existing;
        }

        return DB::transaction(function () use ($batch) {
            $workOrder = $batch->workOrder;
            $latestCheck = $batch->qualityChecks->first();
            $customerCode = $workOrder?->customer?->code ?? 'CUSTOMER';
            $productCode = $workOrder?->productType?->code ?? 'PRODUCT';
            $lot = $batch->lot_number ?? ('B'.$batch->id);
            $coaNumber = $this->makeCoaNumber($customerCode, $productCode, $lot);

            $snapshot = $this->snapshot($batch, 1);
            $snapshot['coa_number'] = $coaNumber;

            $document = CoaDocument::create([
                'batch_id' => $batch->id,
                'coa_number' => $coaNumber,
                'status' => CoaDocument::STATUS_DRAFT,
                'current_version' => 1,
                'customer_id' => $workOrder?->customer?->id,
                'customer_code' => $customerCode,
                'customer_name' => $workOrder?->customer?->name ?? $workOrder?->customer_name,
                'product_type_id' => $workOrder?->productType?->id,
                'product_code' => $productCode,
                'product_name' => $workOrder?->productType?->name,
                'product_revision_id' => $workOrder?->productRevision?->id,
                'revision_code' => $workOrder?->productRevision?->revision_code,
                'lot_number' => $lot,
                'specification_snapshot' => $snapshot['specification'],
                'quality_snapshot' => $snapshot['quality'],
                'gate_snapshot' => $snapshot['release_gate'],
                'prepared_by_id' => auth()->id(),
                'prepared_at' => now(),
            ]);

            CoaDocumentVersion::create([
                'coa_document_id' => $document->id,
                'version' => 1,
                'status' => CoaDocument::STATUS_DRAFT,
                'snapshot' => $snapshot,
                'prepared_by_id' => auth()->id(),
                'prepared_at' => now(),
            ]);

            return $document;
        });
    }

    /**
     * Gate used before releasing a batch to sale. A batch cannot release itself:
     * there is no "batch_released" check here.
     */
    public function releaseGate(Batch $batch): array
    {
        $batch->loadMissing([
            'workOrder.customer',
            'workOrder.productType',
            'workOrder.productRevision',
            'qualityChecks.samples',
            'qualityChecks.template',
            'qualityChecks.checkedBy',
            'packagingChecklist',
            'releaseChecks.checkedBy',
            'labTests',
            'weighingRecords.weighedBy',
        ]);

        $latestCheck = $batch->qualityChecks->first();
        $hasSteps = $batch->steps()->exists();
        $batchComplete = $batch->status === Batch::STATUS_DONE && $hasSteps && $batch->allStepsComplete();
        $specificationReady = $batch->workOrder?->productRevision !== null
            && $batch->workOrder?->productType !== null
            && $latestCheck?->template !== null;

        $checks = [
            $this->check(
                'batch_complete',
                'G01 批次已完成',
                $batchComplete,
                '批次状态与工序记录',
                $batchComplete
                    ? '批次状态为 DONE，全部工序已结束。'
                    : (! $hasSteps
                        ? '批次没有工序记录，无法确认生产完成。'
                        : ($batch->status !== Batch::STATUS_DONE
                            ? '批次尚未完成，不能放行。'
                            : '批次状态为 DONE，但仍有未完成工序。'))
            ),
            $this->weighingGate($batch),
            $this->check(
                'quality_check',
                'G03 成品检验合格',
                $latestCheck !== null && (bool) $latestCheck->all_passed,
                '质量检查记录与检验明细',
                $latestCheck === null
                    ? '尚未记录成品检验。'
                    : ($latestCheck->all_passed ? '最新一次检验全部合格。' : '最新一次检验存在不合格项。')
            ),
            $this->externalLabGate($batch, $batch->labTests->first()),
            $this->check(
                'packaging_check',
                'G05 包装检查合格',
                $batch->packagingChecklist !== null && (bool) $batch->packagingChecklist->all_passed,
                '包装检查表',
                $batch->packagingChecklist === null
                    ? '尚未记录包装检查。'
                    : ($batch->packagingChecklist->all_passed ? '包装检查全部通过。' : '包装检查存在不合格项。')
            ),
            $this->check(
                'no_open_blocking_issue',
                'G06 无未关闭阻断问题',
                ! $this->hasOpenBlockingIssue($batch),
                '工单关联问题单',
                $this->hasOpenBlockingIssue($batch)
                    ? '存在未关闭且阻断工单的问题，需先处置。'
                    : '未发现阻断性问题。'
            ),
            $this->releaseCheckGate(
                $this->latestReleaseCheck($batch, BatchReleaseCheck::TYPE_RETENTION_SAMPLE),
                BatchReleaseCheck::TYPE_RETENTION_SAMPLE,
                'retention_sample',
                'G07 留样已登记'
            ),
            $this->releaseCheckGate(
                $this->latestReleaseCheck($batch, BatchReleaseCheck::TYPE_LINE_CLEARANCE),
                BatchReleaseCheck::TYPE_LINE_CLEARANCE,
                'line_clearance',
                'G08 清线检查合格'
            ),
            $this->releaseCheckGate(
                $this->latestReleaseCheck($batch, BatchReleaseCheck::TYPE_LABEL_BALANCE),
                BatchReleaseCheck::TYPE_LABEL_BALANCE,
                'label_balance',
                'G09 标签平衡'
            ),
            $this->check(
                'specification',
                'G10 规格与配方可追溯',
                $specificationReady,
                '产品版本、产品类型与检验模板',
                $specificationReady
                    ? '产品版本、产品类型与检验模板齐全。'
                    : '缺少产品版本、产品类型或检验模板，COA 无法冻结规格。'
            ),
        ];

        return $this->gateResult($checks);
    }

    /**
     * Gate used before issuing a COA. It adds the release and approval gates
     * to the same production/quality evidence.
     */
    public function issueGate(Batch $batch, ?CoaDocument $document = null): array
    {
        $checks = $this->releaseGate($batch)['checks'];

        $checks[] = $this->check(
            'batch_released',
            'G11 批次已放行',
            $batch->released_at !== null,
            '批次放行记录与签名',
            $batch->released_at === null ? '批次尚未由 QA 放行。' : '批次已放行。'
        );

        $checks[] = $this->check(
            'coa_approved',
            'G12 COA 已批准',
            $document !== null && in_array($document->status, [CoaDocument::STATUS_APPROVED, CoaDocument::STATUS_ISSUED], true),
            'COA 审批记录',
            $document === null
                ? '尚未创建 COA 文档。'
                : (in_array($document->status, [CoaDocument::STATUS_APPROVED, CoaDocument::STATUS_ISSUED], true)
                    ? ($document->status === CoaDocument::STATUS_ISSUED ? 'COA 已签发，批准记录已存在。' : 'COA 已批准。')
                    : 'COA 尚未完成 QA 批准。')
        );

        return $this->gateResult($checks);
    }

    public function release(Batch $batch, User $user, ?string $notes = null): CoaDocument
    {
        $this->assertAnyRole($user, ['Admin', 'Supervisor'], '只有生产主管或管理员可以放行批次。');

        $gate = $this->releaseGate($batch);

        if (! $gate['passed']) {
            throw ValidationException::withMessages([
                'release' => '放行门未通过：'.$gate['failed_labels'],
            ]);
        }

        return DB::transaction(function () use ($batch, $user, $notes, $gate) {
            $batch->released_at = now();
            $batch->released_by = $user->id;
            $batch->release_type = Batch::RELEASE_FOR_SALE;
            if ($notes) {
                $batch->notes = trim(($batch->notes ? $batch->notes."\n" : '').$notes);
            }
            $batch->save();

            $this->sign($batch, ElectronicSignature::MEANING_BATCH_RELEASE, $user, [
                'gate' => $gate,
                'released_at' => $batch->released_at?->toIso8601String(),
            ]);

            return $this->draft($batch);
        });
    }

    /**
     * Independent inspection attestation. The person who actually performed the
     * finished-goods inspection signs the draft before it can go to QA review;
     * this is separate from batch release and QA approval.
     */
    public function inspectionSign(CoaDocument $document, User $user): CoaDocument
    {
        $this->assertStatus($document, [CoaDocument::STATUS_DRAFT], '只有草稿可以由检验人签字。');

        if ($document->inspector_id !== null) {
            throw ValidationException::withMessages([
                'inspect' => '该 COA 已由检验人签字，不能重复签字。',
            ]);
        }

        return DB::transaction(function () use ($document, $user) {
            $document->update([
                'inspector_id' => $user->id,
                'inspected_at' => now(),
            ]);
            $this->sign($document, ElectronicSignature::MEANING_COA_INSPECTION, $user, [
                'quality_check_id' => $document->quality_snapshot['check_id'] ?? null,
            ]);

            return $document->refresh();
        });
    }

    public function submit(CoaDocument $document, User $user): CoaDocument
    {
        $this->assertStatus($document, [CoaDocument::STATUS_DRAFT], '只有草稿可以提交 QA 复核。');

        if ($document->inspector_id === null) {
            throw ValidationException::withMessages([
                'submit' => '请先由检验人完成电子签名，再提交 QA 复核。',
            ]);
        }

        return DB::transaction(function () use ($document, $user) {
            $document->update([
                'status' => CoaDocument::STATUS_QA_REVIEW,
                'submitted_at' => now(),
            ]);
            $this->version($document)->update([
                'status' => CoaDocument::STATUS_QA_REVIEW,
            ]);

            return $document->refresh();
        });
    }

    public function approve(CoaDocument $document, User $user): CoaDocument
    {
        $this->assertAnyRole($user, ['Admin', 'QA'], '只有 QA 或管理员可以批准 COA。');
        $this->assertStatus($document, [CoaDocument::STATUS_QA_REVIEW], '只有待复核的 COA 可以批准。');

        if ($document->batch?->released_at === null) {
            throw ValidationException::withMessages([
                'approve' => '批次尚未放行，不能批准 COA。',
            ]);
        }

        $releasedBy = $document->batch?->released_by;
        if ($releasedBy !== null && (int) $releasedBy === (int) $user->id) {
            throw ValidationException::withMessages([
                'approve' => '放行人与批准人不能为同一人，双人复核未通过。',
            ]);
        }

        if ($document->inspector_id !== null && (int) $document->inspector_id === (int) $user->id) {
            throw ValidationException::withMessages([
                'approve' => '检验人与批准人不能为同一人，质量复核未通过。',
            ]);
        }

        return DB::transaction(function () use ($document, $user) {
            $document->update([
                'status' => CoaDocument::STATUS_APPROVED,
                'approved_by_id' => $user->id,
                'approved_at' => now(),
            ]);
            $this->version($document)->update([
                'status' => CoaDocument::STATUS_APPROVED,
                'approved_by_id' => $user->id,
                'approved_at' => now(),
            ]);
            $this->sign($document, ElectronicSignature::MEANING_COA_APPROVAL, $user);

            return $document->refresh();
        });
    }

    public function issue(CoaDocument $document, User $user): CoaDocument
    {
        $this->assertAnyRole($user, ['Admin', 'QA'], '只有 QA 或管理员可以签发 COA。');
        $this->assertStatus($document, [CoaDocument::STATUS_APPROVED], '只有已批准的 COA 可以签发。');

        $batch = $document->batch;
        if (! $batch) {
            throw ValidationException::withMessages(['issue' => 'COA 没有关联批次。']);
        }

        $gate = $this->issueGate($batch, $document);
        if (! $gate['passed']) {
            throw ValidationException::withMessages([
                'issue' => '签发门未通过：'.$gate['failed_labels'],
            ]);
        }

        return DB::transaction(function () use ($document, $batch, $user, $gate) {
            $snapshot = $this->snapshot($batch, $document->current_version);
            $snapshot['issue_gate'] = $gate;
            $snapshot['issued_at'] = now()->toIso8601String();

            $document->update([
                'status' => CoaDocument::STATUS_ISSUED,
                'issued_by_id' => $user->id,
                'issued_at' => now(),
                'gate_snapshot' => $gate,
            ]);
            $this->version($document)->update([
                'status' => CoaDocument::STATUS_ISSUED,
                'issued_by_id' => $user->id,
                'issued_at' => now(),
                'snapshot' => $snapshot,
            ]);
            $this->sign($document, ElectronicSignature::MEANING_COA_ISSUE, $user, [
                'gate' => $gate,
                'version' => $document->current_version,
            ]);

            return $document->refresh();
        });
    }

    public function void(CoaDocument $document, User $user, string $reason): CoaDocument
    {
        $this->assertAnyRole($user, ['Admin', 'QA'], '只有 QA 或管理员可以作废 COA。');
        $this->assertStatus($document, [CoaDocument::STATUS_ISSUED], '只有已签发的 COA 可以作废。');

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['void_reason' => '作废原因不能为空。']);
        }

        return DB::transaction(function () use ($document, $user, $reason) {
            $document->update([
                'status' => CoaDocument::STATUS_VOID,
                'voided_by_id' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);
            $this->version($document)->update([
                'status' => CoaDocument::STATUS_VOID,
                'voided_by_id' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);
            $this->sign($document, ElectronicSignature::MEANING_COA_VOID, $user, [
                'reason' => $reason,
                'version' => $document->current_version,
            ]);

            return $document->refresh();
        });
    }

    public function reissue(CoaDocument $document, User $user, string $reason): CoaDocument
    {
        $this->assertAnyRole($user, ['Admin', 'QA'], '只有 QA 或管理员可以重签 COA。');
        $this->assertStatus($document, [CoaDocument::STATUS_VOID], '只有已作废的 COA 可以重签。');

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['void_reason' => '重签原因不能为空。']);
        }

        return DB::transaction(function () use ($document, $user, $reason) {
            $versionNumber = $document->current_version + 1;
            $snapshot = $this->snapshot($document->batch, $versionNumber);
            $snapshot['reissue_reason'] = $reason;

            $document->update([
                'status' => CoaDocument::STATUS_DRAFT,
                'current_version' => $versionNumber,
                'specification_snapshot' => $snapshot['specification'],
                'quality_snapshot' => $snapshot['quality'],
                'gate_snapshot' => $snapshot['release_gate'],
                'prepared_by_id' => $user->id,
                'prepared_at' => now(),
                'submitted_at' => null,
                'approved_by_id' => null,
                'approved_at' => null,
                'issued_by_id' => null,
                'issued_at' => null,
                'voided_by_id' => null,
                'voided_at' => null,
                'void_reason' => null,
            ]);

            CoaDocumentVersion::create([
                'coa_document_id' => $document->id,
                'version' => $versionNumber,
                'status' => CoaDocument::STATUS_DRAFT,
                'snapshot' => $snapshot,
                'change_reason' => $reason,
                'prepared_by_id' => $user->id,
                'prepared_at' => now(),
            ]);

            return $document->refresh();
        });
    }

    public function snapshot(Batch $batch, int $version): array
    {
        $batch->loadMissing([
            'workOrder.customer',
            'workOrder.productType',
            'workOrder.productRevision',
            'qualityChecks.samples',
            'qualityChecks.template',
            'qualityChecks.checkedBy',
            'packagingChecklist',
            'labTests',
            'weighingRecords.weighedBy',
        ]);

        $workOrder = $batch->workOrder;
        $latestCheck = $batch->qualityChecks->first();
        $standard = app(CustomerFlavorStandardResolver::class)->resolve(
            $workOrder?->customer_id,
            $workOrder?->product_type_id,
            $batch->completed_at ?? now(),
        );
        $standardReference = $standard
            ? trim('客户标准 '.$standard->code.' '.$standard->version)
            : '客户标准 / 产品企业标准（以客户批准版本为准）';

        return [
            'coa_number' => $batch->coaDocument?->coa_number,
            'version' => $version,
            'generated_at' => now()->toIso8601String(),
            'batch' => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'lot_number' => $batch->lot_number,
                'status' => $batch->status,
                'produced_qty' => (float) $batch->produced_qty,
                'started_at' => $batch->started_at?->toIso8601String(),
                'completed_at' => $batch->completed_at?->toIso8601String(),
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'released_at' => $batch->released_at?->toIso8601String(),
                'released_by' => $batch->releasedBy?->name,
            ],
            'work_order' => [
                'id' => $workOrder?->id,
                'order_no' => $workOrder?->order_no,
                'customer' => $workOrder?->customer?->name ?? $workOrder?->customer_name,
                'product' => $workOrder?->productType?->name,
                'line' => $workOrder?->line?->name,
            ],
            'specification' => [
                'customer_code' => $workOrder?->customer?->code,
                'customer_name' => $workOrder?->customer?->name ?? $workOrder?->customer_name,
                'product_code' => $workOrder?->productType?->code,
                'product_name' => $workOrder?->productType?->name,
                'revision_code' => $workOrder?->productRevision?->revision_code,
                'revision_description' => $workOrder?->productRevision?->description,
                'external_ref' => $workOrder?->productRevision?->external_ref,
                'standard_code' => $standard?->code,
                'standard_name' => $standard?->name,
                'standard_version' => $standard?->version,
                'standard_reference' => $standardReference,
                'sensory_profile' => $standard?->sensorySummary(),
                'packaging_requirement' => $standard?->packaging_requirement,
                'required_lab_items' => $standard?->requiredLabItems() ?? [],
                'parameters' => $standard?->parameters() ?: ($latestCheck?->template?->parameters ?? []),
            ],
            'quality' => $latestCheck ? [
                'check_id' => $latestCheck->id,
                'checked_at' => $latestCheck->checked_at?->toIso8601String(),
                'checked_by' => $latestCheck->checkedBy?->name,
                'all_passed' => (bool) $latestCheck->all_passed,
                'production_quantity' => $latestCheck->production_quantity,
                'samples' => $latestCheck->samples->map(fn ($sample) => [
                    'sample_number' => $sample->sample_number,
                    'parameter_name' => $sample->parameter_name,
                    'parameter_type' => $sample->parameter_type,
                    'value_numeric' => $sample->value_numeric,
                    'value_boolean' => $sample->value_boolean,
                    'is_passed' => $sample->is_passed,
                ])->values()->all(),
            ] : null,
            'packaging' => $batch->packagingChecklist ? [
                'checked_at' => $batch->packagingChecklist->checked_at?->toIso8601String(),
                'checked_by' => $batch->packagingChecklist->checkedBy?->name,
                'all_passed' => (bool) $batch->packagingChecklist->all_passed,
            ] : null,
            'external_lab' => $batch->labTests->first() ? [
                'lab_name' => $batch->labTests->first()->lab_name,
                'report_number' => $batch->labTests->first()->report_number,
                'sample_number' => $batch->labTests->first()->sample_number,
                'sampled_at' => $batch->labTests->first()->sampled_at?->toDateString(),
                'issued_at' => $batch->labTests->first()->issued_at?->toDateString(),
                'overall_conclusion' => $batch->labTests->first()->overall_conclusion,
                'items' => $batch->labTests->first()->items,
                'notes' => $batch->labTests->first()->notes,
            ] : null,
            'external_lab_required' => $this->requiresExternalLab($batch),
            'weighing' => $this->weighingSummary($batch),
            'release_gate' => $this->releaseGate($batch),
        ];
    }

    public function requiresExternalLab(Batch $batch): bool
    {
        $batch->loadMissing(['workOrder.customer', 'workOrder.productType']);
        $workOrder = $batch->workOrder;
        $standard = app(CustomerFlavorStandardResolver::class)->resolve(
            $workOrder?->customer_id,
            $workOrder?->product_type_id,
            $batch->completed_at ?? now(),
        );

        return (bool) $standard?->requires_external_lab;
    }

    private function externalLabGate(Batch $batch, ?BatchLabTest $labTest): array
    {
        $batch->loadMissing(['workOrder.customer', 'workOrder.productType']);
        $standard = app(CustomerFlavorStandardResolver::class)->resolve(
            $batch->workOrder?->customer_id,
            $batch->workOrder?->product_type_id,
            $batch->completed_at ?? now(),
        );
        $required = (bool) $standard?->requires_external_lab;
        $requiredItems = $standard?->requiredLabItems() ?? [];
        $availableItems = collect($labTest?->items ?? [])
            ->flatMap(fn ($item) => [$item['category'] ?? '', $item['name'] ?? ''])
            ->filter()
            ->values();
        $missingItems = collect($requiredItems)
            ->filter(fn ($requiredItem) => ! $availableItems->contains(
                fn ($available) => mb_stripos((string) $available, (string) $requiredItem) !== false
            ))
            ->values()
            ->all();
        $passed = ! $required || ($labTest !== null && $labTest->allPassed() && $missingItems === []);
        $detail = match (true) {
            ! $required => '客户标准未要求外检，本项不适用。',
            $labTest === null => '客户标准要求外检（'.($requiredItems ? implode('、', $requiredItems) : '农残 / 重金属 / 微生物').'），尚未登记第三方报告。',
            ! $labTest->allPassed() => "最新外检报告 {$labTest->report_number} 结论为不合格，不能放行。",
            $missingItems !== [] => "最新外检报告 {$labTest->report_number} 缺少必检类别：".implode('、', $missingItems).'。',
            default => "外检报告 {$labTest->report_number}（{$labTest->lab_name}）结论合格，共 "
                .count($labTest->items ?? []).' 项。',
        };

        return $this->check(
            'external_lab',
            'G04 外检报告合格',
            $passed,
            '第三方检测报告：农残 / 重金属 / 微生物',
            $detail
        );
    }

    /**
     * Raw-material BOM lines that must be weighed before release. Packaging is
     * excluded: bag/carton/label counts are covered by label balance and the
     * packaging checklist, not by gram-level blending tolerance.
     *
     * @return list<array{material_id:?int, material_code:?string, material_name:string, unit_of_measure:string, target_qty:float, tolerance_pct:float, step_number:?int}>
     */
    public function weighingRequirements(Batch $batch): array
    {
        $batch->loadMissing('workOrder');
        $bom = $batch->workOrder?->process_snapshot['bom'] ?? [];
        $targetQty = (float) $batch->target_qty;
        $out = [];

        foreach ($bom as $item) {
            if (($item['component_kind'] ?? 'material') !== 'material') {
                continue;
            }
            if (($item['material_type'] ?? null) === 'packaging') {
                continue;
            }

            $base = (float) ($item['quantity_per_unit'] ?? 0) * $targetQty;
            $target = round($base * (1 + ((float) ($item['scrap_percentage'] ?? 0) / 100)), 4);
            if ($target <= 0) {
                continue;
            }

            $key = ($item['material_id'] ?? 'x').'@'.($item['step_number'] ?? '-');
            if (isset($out[$key])) {
                continue;
            }

            $out[$key] = [
                'material_id' => isset($item['material_id']) ? (int) $item['material_id'] : null,
                'material_code' => $item['material_code'] ?? null,
                'material_name' => $item['material_name'] ?? '未知原料',
                'unit_of_measure' => $item['unit_of_measure'] ?? 'kg',
                'target_qty' => $target,
                'tolerance_pct' => BatchWeighingRecord::DEFAULT_TOLERANCE_PCT,
                'step_number' => isset($item['step_number']) ? (int) $item['step_number'] : null,
            ];
        }

        return array_values($out);
    }

    /** Latest record per required material/step. */
    public function weighingSummary(Batch $batch): array
    {
        $batch->loadMissing('weighingRecords.weighedBy');
        $records = $batch->weighingRecords;
        $rows = [];

        foreach ($this->weighingRequirements($batch) as $requirement) {
            $record = $records->first(fn (BatchWeighingRecord $candidate) => (int) $candidate->material_id === (int) $requirement['material_id']
                && (int) $candidate->step_number === (int) $requirement['step_number']);

            $rows[] = $requirement + [
                'record' => $record ? [
                    'id' => $record->id,
                    'actual_qty' => (float) $record->actual_qty,
                    'tolerance_pct' => (float) $record->tolerance_pct,
                    'variance_qty' => (float) $record->variance_qty,
                    'variance_pct' => (float) $record->variance_pct,
                    'status' => $record->status,
                    'scale_code' => $record->scale_code,
                    'weighed_by' => $record->weighedBy?->name,
                    'weighed_at' => $record->weighed_at?->toIso8601String(),
                    'notes' => $record->notes,
                ] : null,
            ];
        }

        $passed = collect($rows)->filter(fn ($row) => $row['record'] && $row['record']['status'] === BatchWeighingRecord::STATUS_PASS)->count();

        return [
            'passed' => $passed,
            'total' => count($rows),
            'all_passed' => count($rows) === $passed,
            'rows' => $rows,
        ];
    }

    private function weighingGate(Batch $batch): array
    {
        $summary = $this->weighingSummary($batch);

        if ($summary['total'] === 0) {
            return $this->check('weighing', 'G02 投料称重合格', true, 'BOM 原料与称重记录', 'BOM 无需要称重的原料，本项不适用。');
        }

        $missing = collect($summary['rows'])->filter(fn ($row) => $row['record'] === null)->pluck('material_name')->all();
        $failed = collect($summary['rows'])->filter(fn ($row) => $row['record'] && $row['record']['status'] !== BatchWeighingRecord::STATUS_PASS)->pluck('material_name')->all();

        $passed = $summary['all_passed'];
        $detail = match (true) {
            $passed => "共 {$summary['total']} 种原料，全部在公差内。",
            $missing !== [] && $failed !== [] => '缺少称重记录：'.implode('、', $missing).'；超差：'.implode('、', $failed).'。',
            $missing !== [] => '尚未登记的原料：'.implode('、', $missing).'。',
            default => '超差原料：'.implode('、', $failed).'，需复称或走偏差处理。',
        };

        return $this->check('weighing', 'G02 投料称重合格', $passed, 'BOM 原料与称重记录', $detail);
    }

    private function latestReleaseCheck(Batch $batch, string $type): ?BatchReleaseCheck
    {
        return $batch->releaseChecks->firstWhere('check_type', $type);
    }

    private function releaseCheckGate(?BatchReleaseCheck $check, string $type, string $key, string $label): array
    {
        $passed = $check !== null && $check->status === BatchReleaseCheck::STATUS_PASS;
        $evidence = '批记录确认：'.BatchReleaseCheck::label($type);
        $detail = match (true) {
            $check === null => "尚未登记{$label}，放行前需由现场负责人确认并留下证据。",
            $check->status !== BatchReleaseCheck::STATUS_PASS => "最近一次{$label}未通过"
                .($check->evidence ? "：{$check->evidence}" : '。'),
            default => '已确认通过'
                .($check->checkedBy ? "；确认人 {$check->checkedBy->name}" : '')
                .($check->checked_at ? '，'.$check->checked_at->format('m-d H:i') : '')
                .($check->evidence ? "；{$check->evidence}" : '。'),
        };

        return $this->check($key, $label, $passed, $evidence, $detail);
    }

    private function makeCoaNumber(string $customerCode, string $productCode, string $lot): string
    {
        $clean = fn (string $value) => Str::upper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '', '-'));

        return Str::limit('COA-'.$clean($customerCode).'-'.$clean($productCode).'-'.$clean($lot), 120, '');
    }

    private function hasOpenBlockingIssue(Batch $batch): bool
    {
        return Issue::query()
            ->where('work_order_id', $batch->work_order_id)
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->where('blocks_work_order', true)
            ->exists();
    }

    private function check(string $key, string $label, bool $passed, string $evidence, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'required' => true,
            'evidence' => $evidence,
            'detail' => $detail,
        ];
    }

    private function gateResult(array $checks): array
    {
        $passed = collect($checks)->every(fn (array $check) => $check['passed']);
        $failed = collect($checks)->reject(fn (array $check) => $check['passed']);

        return [
            'passed' => $passed,
            'passed_count' => collect($checks)->where('passed', true)->count(),
            'total' => count($checks),
            'failed_labels' => $failed->pluck('label')->implode('、'),
            'checks' => $checks,
        ];
    }

    private function version(CoaDocument $document): CoaDocumentVersion
    {
        return $document->versions()
            ->where('version', $document->current_version)
            ->firstOrFail();
    }

    private function assertStatus(CoaDocument $document, array $allowed, string $message): void
    {
        if (! in_array($document->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function assertAnyRole(User $user, array $roles, string $message): void
    {
        if (! $user->hasAnyRole($roles)) {
            throw ValidationException::withMessages(['role' => $message]);
        }
    }

    private function sign(Model $record, string $meaning, User $user, array $metadata = []): ElectronicSignature
    {
        $signedAt = now();
        $metadata['signer_roles'] = $user->getRoleNames()->all();
        $payload = [
            'signable_type' => get_class($record),
            'signable_id' => $record->getKey(),
            'user_id' => $user->id,
            'meaning' => $meaning,
            'signed_at' => $signedAt->toIso8601String(),
            'metadata' => $metadata,
        ];

        return ElectronicSignature::create([
            'signable_type' => get_class($record),
            'signable_id' => $record->getKey(),
            'user_id' => $user->id,
            'meaning' => $meaning,
            'signed_at' => $signedAt,
            'record_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Recompute the signature payload and compare it to the stored hash.
     *
     * This is the cheap integrity check shown on the COA detail page: it
     * proves the stored signer/meaning/time/metadata still hash to the value
     * recorded at signing, without needing the original private key material.
     */
    public function verifySignature(ElectronicSignature $signature): bool
    {
        if (! $signature->signed_at) {
            return false;
        }

        $payload = [
            'signable_type' => $signature->signable_type,
            'signable_id' => $signature->signable_id,
            'user_id' => $signature->user_id,
            'meaning' => $signature->meaning,
            'signed_at' => $signature->signed_at->toIso8601String(),
            'metadata' => $signature->metadata ?? [],
        ];

        return hash_equals(
            (string) $signature->record_hash,
            hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        );
    }
}
