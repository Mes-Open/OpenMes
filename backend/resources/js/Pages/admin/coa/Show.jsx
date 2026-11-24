import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime, formatNumber } from '../../../lib/i18n';

const STATUS_LABEL = {
    DRAFT: '草稿',
    QA_REVIEW: 'QA 复核中',
    APPROVED: '已批准',
    ISSUED: '已签发',
    VOID: '已作废',
};

const SIGNATURE_LABEL = {
    BATCH_RELEASE: '批次放行',
    COA_INSPECTION: '检验人确认',
    COA_APPROVAL: 'COA 批准',
    COA_ISSUE: 'COA 签发',
    COA_VOID: 'COA 作废',
};

const BATCH_STATUS_LABEL = {
    PENDING: '待开始',
    IN_PROGRESS: '生产中',
    DONE: '已完成',
    CANCELLED: '已取消',
};

const GATE_HELP = {
    batch_complete: '批次状态为 DONE，并且全部工序已经结束；没有工序记录的批次不能放行。',
    weighing: 'BOM 原料的称重记录全部存在，实际重量在公差内；超差必须有处置和复核。',
    quality_check: '最近一次成品检验全部合格；不合格项必须先关闭或走偏差。',
    external_lab: '客户标准要求的外检报告已上传，项目覆盖完整，结论合格。',
    packaging_check: '包装检查表全部通过；净含量、密封、标签等不能有未关闭项。',
    no_open_blocking_issue: '工单没有未关闭且阻断放行的问题单；有阻断问题时先处置。',
    retention_sample: '留样已登记，样品、数量、位置和保管人可追溯。',
    line_clearance: '清线检查合格，上一批的物料、标签和半成品没有留在线上。',
    label_balance: '标签领用、消耗和报废数量平衡；差异未查清不能放行。',
    specification: '产品版本、产品类型和检验模板齐全，COA 才能冻结规格。',
    batch_released: '批次已由 QA 放行并写入放行签名；放行人和 COA 批准人不能是同一人。',
    coa_approved: 'COA 已完成 QA 复核并批准；未批准的文档不能签发。',
};

const GATE_TAB = {
    weighing: 'evidence',
    quality_check: 'inspection',
    external_lab: 'inspection',
    packaging_check: 'inspection',
    retention_sample: 'record',
    line_clearance: 'record',
    label_balance: 'record',
    specification: 'inspection',
};

const TABS = [
    { key: 'evidence', label: '称重与证据' },
    { key: 'inspection', label: '规格与检验' },
    { key: 'record', label: '文档与签名' },
];

const gateCode = (check, index) => {
    const match = String(check.label ?? '').match(/^G\d+/);
    return match ? match[0] : `G${String(index + 1).padStart(2, '0')}`;
};

const gateName = (check) => String(check.label ?? '').replace(/^G\d+\s*/, '');

export default function CoaShow() {
    const { batch, document, versions = [], releaseGate, issueGate, separation, weighing, labTest, labTestRequired = false, releaseChecks = {}, batchSignatures = [], documentSignatures = [] } = usePage().props;
    const form = useForm({ notes: '', reason: '' });
    const [gateMode, setGateMode] = useState(batch.released_at ? 'issue' : 'release');
    const [tab, setTab] = useState('evidence');
    const [evidence, setEvidence] = useState(null);

    const post = (path) => form.post(path, { preserveScroll: true });

    if (!document) {
        return <NoCoa batch={batch} releaseGate={releaseGate} weighing={weighing} labTest={labTest} labTestRequired={labTestRequired} releaseChecks={releaseChecks} form={form} post={post} />;
    }

    const failedRelease = Math.max((releaseGate?.total ?? 0) - (releaseGate?.passed_count ?? 0), 0);
    const failedIssue = Math.max((issueGate?.total ?? 0) - (issueGate?.passed_count ?? 0), 0);

    const actions = [];
    if (!batch.released_at) {
        actions.push({
            key: 'release',
            label: '确认放行',
            path: 'release',
            tone: 'primary',
            disabled: !releaseGate?.passed,
        });
    }
    if (document.status === 'DRAFT') {
        if (!document.inspector) {
            actions.push({
                key: 'inspect',
                label: '检验人签字',
                path: 'inspect-sign',
                tone: batch.released_at ? 'primary' : 'secondary',
                disabled: false,
            });
        } else {
            actions.push({
                key: 'submit',
                label: '提交 QA 复核',
                path: 'submit',
                tone: 'primary',
                disabled: false,
            });
        }
    }
    if (document.status === 'QA_REVIEW') {
        actions.push({
            key: 'approve',
            label: '批准 COA',
            path: 'approve',
            tone: 'primary',
            disabled: !batch.released_at,
        });
    }
    if (document.status === 'APPROVED') {
        actions.push({
            key: 'issue',
            label: '签发 COA',
            path: 'issue',
            tone: 'primary',
            disabled: !issueGate?.passed,
        });
    }
    // 作废 / 重签必须填写原因，统一放在 COA 面板的版本操作区，避免头部按钮无原因提交。

    let primaryHint = '';
    if (!batch.released_at) {
        primaryHint = releaseGate?.passed
            ? '放行门 10/10 已通过，确认后写入放行签名。'
            : `放行门还差 ${failedRelease} 项：${releaseGate?.failed_labels ?? '—'}`;
    } else if (document.status === 'DRAFT' && !document.inspector) {
        primaryHint = '先由检验人完成电子签名，再提交 QA 复核。';
    } else if (document.status === 'APPROVED' && !issueGate?.passed) {
        primaryHint = `签发门还差 ${failedIssue} 项：${issueGate?.failed_labels ?? '—'}`;
    }

    return (
        <>
            <Head title={`${batch.lot_number ?? `B${batch.batch_number}`} — 批次控制台`} />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <ConsoleHeader
                    batch={batch}
                    document={document}
                    actions={actions}
                    form={form}
                    post={post}
                    hint={primaryHint}
                    showPdf
                />

                {Object.keys(form.errors).length > 0 && (
                    <div className="rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-4 py-3 text-sm text-om-blocked">
                        {Object.values(form.errors).map((error) => <div key={error}>{error}</div>)}
                    </div>
                )}

                <SummaryStrip batch={batch} document={document} />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.95fr)]">
                    <GateRail
                        releaseGate={releaseGate}
                        issueGate={issueGate}
                        mode={gateMode}
                        onModeChange={setGateMode}
                        onSelect={(check, mode) => setEvidence({ check, mode })}
                    />

                    <div className="min-w-0 space-y-4 xl:sticky xl:top-4 xl:self-start">
                        <CoaPanel
                            batch={batch}
                            document={document}
                            issueGate={issueGate}
                            signatures={[...batchSignatures, ...documentSignatures]}
                            form={form}
                            post={post}
                            onShowIssueGate={() => setGateMode('issue')}
                            onShowSignatures={() => setTab('record')}
                        />
                    </div>
                </div>

                <DetailTabs tab={tab} onChange={setTab} />

                {tab === 'evidence' && (
                    <div className="space-y-4">
                        <WeighingPanel batch={batch} weighing={weighing} />
                        <LabTestPanel batch={batch} labTest={labTest} labTestRequired={labTestRequired} />
                        <ReleaseCheckPanel batch={batch} releaseChecks={releaseChecks} />
                    </div>
                )}

                {tab === 'inspection' && (
                    <div className="space-y-4">
                        <SpecSnapshotSection document={document} />
                        <QualitySnapshotSection document={document} />
                    </div>
                )}

                {tab === 'record' && (
                    <RecordTab
                        batch={batch}
                        document={document}
                        versions={versions}
                        separation={separation}
                        batchSignatures={batchSignatures}
                        documentSignatures={documentSignatures}
                    />
                )}
            </div>

            <EvidenceDrawer
                evidence={evidence}
                onClose={() => setEvidence(null)}
                onGoFix={(target) => {
                    setTab(target);
                    setEvidence(null);
                }}
            />
        </>
    );
}

function NoCoa({ batch, releaseGate, weighing, labTest, labTestRequired = false, releaseChecks = {}, form, post }) {
    const [tab, setTab] = useState('evidence');
    const [evidence, setEvidence] = useState(null);
    const missing = Math.max((releaseGate?.total ?? 0) - (releaseGate?.passed_count ?? 0), 0);
    const tabs = TABS.filter((item) => item.key !== 'inspection');

    const actions = [
        { key: 'draft', label: '生成 COA 草稿', path: 'draft', tone: 'primary', disabled: false },
    ];
    if (!batch.released_at && releaseGate?.passed) {
        actions.push({ key: 'release', label: '确认放行', path: 'release', tone: 'secondary', disabled: false });
    }

    return (
        <>
            <Head title={`${batch.lot_number ?? `B${batch.batch_number}`} — 批次控制台`} />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <ConsoleHeader batch={batch} document={null} actions={actions} form={form} post={post} showPdf={false}
                    hint={releaseGate?.passed ? '放行门 10/10 已通过。草稿会冻结规格，但只有批准后才能签发。' : `放行门还差 ${missing} 项：${releaseGate?.failed_labels ?? '—'}`} />

                {Object.keys(form.errors).length > 0 && (
                    <div className="rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-4 py-3 text-sm text-om-blocked">
                        {Object.values(form.errors).map((error) => <div key={error}>{error}</div>)}
                    </div>
                )}

                <SummaryStrip batch={batch} document={null} />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.95fr)]">
                    <GateRail
                        releaseGate={releaseGate}
                        issueGate={null}
                        mode="release"
                        onModeChange={() => {}}
                        onSelect={(check, mode) => setEvidence({ check, mode })}
                    />
                    <DraftPanel batch={batch} releaseGate={releaseGate} form={form} post={post} />
                </div>

                <DetailTabs tab={tab} onChange={setTab} tabs={tabs} />

                {tab === 'evidence' && (
                    <div className="space-y-4">
                        <WeighingPanel batch={batch} weighing={weighing} />
                        <LabTestPanel batch={batch} labTest={labTest} labTestRequired={labTestRequired} />
                        <ReleaseCheckPanel batch={batch} releaseChecks={releaseChecks} />
                    </div>
                )}

                {tab === 'record' && <ProductBatchSection batch={batch} document={null} />}
            </div>

            <EvidenceDrawer
                evidence={evidence}
                onClose={() => setEvidence(null)}
                onGoFix={(target) => {
                    setTab(target);
                    setEvidence(null);
                }}
            />
        </>
    );
}

function ConsoleHeader({ batch, document, actions, form, post, hint, showPdf }) {
    const lot = batch.lot_number ?? `B${batch.batch_number}`;
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0">
                <Link href="/admin/coa" className="inline-flex items-center gap-1 text-xs font-bold text-om-accent hover:underline">
                    ← COA 工作台
                </Link>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <h1 className="break-all font-mono text-[18px] font-bold tracking-tight text-om-ink sm:text-[22px]">{lot}</h1>
                    <BatchStatusBadge status={batch.status} />
                    {document && <StatusBadge status={document.status} />}
                </div>
                <div className="mt-1 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-xs text-om-muted">
                    <span className="max-w-[520px] truncate font-mono text-om-faint" title={document?.coa_number ?? 'COA 未生成'}>
                        {document?.coa_number ?? 'COA 未生成'}
                    </span>
                    <span>·</span>
                    <span>{batch.product ?? '—'}</span>
                    <span>·</span>
                    <span>{batch.customer ?? '—'}</span>
                </div>
                {hint && <p className={`mt-2 text-xs ${hint.includes('还差') ? 'text-om-downtime' : 'text-om-muted'}`}>{hint}</p>}
            </div>
            <div className="flex max-w-full flex-wrap items-center gap-2">
                {showPdf && (
                    <a href={`/admin/coa/${batch.id}/pdf`} className="btn-touch btn-secondary text-sm">
                        下载 PDF
                    </a>
                )}
                {document?.verification?.url && (
                    <a href={document.verification.url} target="_blank" rel="noreferrer" className="btn-touch btn-secondary text-sm">
                        验真链接
                    </a>
                )}
                {actions.map((action) => (
                    <button
                        key={action.key}
                        type="button"
                        disabled={action.disabled || form.processing}
                        onClick={() => post(`/admin/coa/${batch.id}/${action.path}`)}
                        className={`btn-touch text-sm disabled:cursor-not-allowed disabled:opacity-40 ${action.tone === 'danger' ? 'btn-danger' : action.tone === 'secondary' ? 'btn-secondary' : 'btn-primary'}`}
                    >
                        {form.processing ? '处理中…' : action.label}
                    </button>
                ))}
            </div>
        </div>
    );
}

function SummaryStrip({ batch, document }) {
    const metrics = [
        { label: '成品数量', value: `${formatNumber(batch.produced_qty)} ${batch.unit_of_measure || 'kg'}`, sub: batch.order_no ?? '—' },
        { label: '生产完成', value: batch.completed_at ? formatDateTime(batch.completed_at) : '未完成', sub: batch.expiry_date ? `保质期至 ${batch.expiry_date}` : '—' },
        { label: '批次放行', value: batch.released_at ? '已放行' : '未放行', sub: batch.released_at ? `${batch.released_by ?? '—'} · ${formatDateTime(batch.released_at)}` : '等待放行门通过' },
        { label: 'COA 版本', value: document ? `V${String(document.current_version).padStart(2, '0')}` : '未生成', sub: document ? STATUS_LABEL[document.status] : '先过放行门' },
    ];

    return (
        <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-4">
            {metrics.map((metric, index) => (
                <div
                    key={metric.label}
                    className={`min-w-0 px-4 py-3 ${index < metrics.length - 1 ? 'border-b border-om-line2 sm:border-b-0 sm:border-r' : ''}`}
                >
                    <div className="text-[11px] font-bold tracking-wide text-om-faint">{metric.label}</div>
                    <div className={`mt-1 truncate text-sm font-bold ${metric.label === '批次放行' && batch.released_at ? 'text-om-running' : 'text-om-ink'}`} title={metric.value}>
                        {metric.value}
                    </div>
                    <div className="mt-0.5 truncate text-[11px] text-om-muted" title={metric.sub}>{metric.sub}</div>
                </div>
            ))}
        </div>
    );
}

function GateRail({ releaseGate, issueGate, mode, onModeChange, onSelect }) {
    const active = mode === 'issue' && issueGate ? issueGate : releaseGate;
    const checks = active?.checks ?? [];
    const passed = active?.passed_count ?? 0;
    const total = active?.total ?? 0;

    return (
        <div className="min-w-0 overflow-hidden rounded-om-sm border border-om-line bg-om-card">
            <div className="border-b border-om-line2 px-4 py-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-bold text-om-ink">放行脊柱</h2>
                        <p className="mt-0.5 text-xs text-om-muted">点任一门查看证据、判定口径和责任人。</p>
                    </div>
                    {issueGate ? (
                        <div className="inline-flex rounded-om-sm border border-om-line bg-om-bg p-0.5">
                            <button
                                type="button"
                                onClick={() => onModeChange('release')}
                                aria-pressed={mode === 'release'}
                                className={`rounded-[6px] px-2.5 py-1 text-[11px] font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-om-accent/40 ${mode === 'release' ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'}`}
                            >
                                放行门 {releaseGate?.passed_count ?? 0}/{releaseGate?.total ?? 0}
                            </button>
                            <button
                                type="button"
                                onClick={() => onModeChange('issue')}
                                aria-pressed={mode === 'issue'}
                                className={`rounded-[6px] px-2.5 py-1 text-[11px] font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-om-accent/40 ${mode === 'issue' ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'}`}
                            >
                                签发门 {issueGate.passed_count}/{issueGate.total}
                            </button>
                        </div>
                    ) : (
                        <span className="font-mono text-sm font-bold text-om-ink">{passed}/{total}</span>
                    )}
                </div>
                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-om-chip">
                    <div
                        className={`h-full rounded-full transition-[width] duration-200 ${passed === total ? 'bg-om-running' : 'bg-om-downtime'}`}
                        style={{ width: `${Math.round((passed / Math.max(total, 1)) * 100)}%` }}
                    />
                </div>
            </div>

            <div className="divide-y divide-om-line2">
                {checks.length === 0 ? (
                    <p className="px-4 py-8 text-center text-sm text-om-faint">没有可显示的门禁记录。</p>
                ) : checks.map((check, index) => (
                    <button
                        key={check.key}
                        type="button"
                        onClick={() => onSelect(check, mode)}
                        className="group flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-om-chip/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-om-accent/40"
                    >
                        <GateIcon passed={check.passed} />
                        <span className="min-w-0 flex-1">
                            <span className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span className="font-mono text-[10px] font-bold text-om-faint">{gateCode(check, index)}</span>
                                <span className="text-sm font-semibold text-om-ink">{gateName(check)}</span>
                            </span>
                            <span className="mt-0.5 block truncate text-xs text-om-muted" title={check.detail}>{check.detail}</span>
                        </span>
                        <span className="shrink-0 text-[11px] font-bold text-om-faint group-hover:text-om-accent">证据</span>
                    </button>
                ))}
            </div>
        </div>
    );
}

function GateIcon({ passed }) {
    return (
        <span className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${passed ? 'bg-om-running-bg text-om-running' : 'bg-om-blocked-bg text-om-blocked'}`}>
            {passed ? (
                <svg viewBox="0 0 20 20" aria-hidden="true" className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M4 10.5 8 14.5 16 5.5" />
                </svg>
            ) : (
                <svg viewBox="0 0 20 20" aria-hidden="true" className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round">
                    <path d="M10 4.5v7M10 15.5h.01" />
                </svg>
            )}
        </span>
    );
}

function EvidenceDrawer({ evidence, onClose, onGoFix }) {
    useEffect(() => {
        if (!evidence) return undefined;
        const onKey = (event) => {
            if (event.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [evidence, onClose]);

    if (!evidence) return null;

    const { check, mode } = evidence;
    const code = gateCode(check, 0);
    const targetTab = GATE_TAB[check.key];

    return (
        <div className="fixed inset-0 z-50">
            <div className="absolute inset-0 bg-om-ink/15" onClick={onClose} />
            <aside
                role="dialog"
                aria-modal="true"
                aria-labelledby="gate-evidence-title"
                className="absolute inset-y-0 right-0 flex w-full max-w-md flex-col overflow-y-auto border-l border-om-line bg-om-card shadow-2xl"
            >
                <div className="flex items-start justify-between gap-4 border-b border-om-line2 px-5 py-4">
                    <div className="min-w-0">
                        <div className="font-mono text-[11px] font-bold text-om-faint">{code} · {mode === 'issue' ? '签发门' : '放行门'}</div>
                        <h2 id="gate-evidence-title" className="mt-1 text-base font-bold text-om-ink">{gateName(check)}</h2>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="关闭证据抽屉"
                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-om-muted transition-colors hover:bg-om-chip hover:text-om-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-om-accent/40"
                    >
                        <svg viewBox="0 0 20 20" aria-hidden="true" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
                            <path d="m5 5 10 10M15 5 5 15" />
                        </svg>
                    </button>
                </div>

                <div className="space-y-4 px-5 py-5">
                    <div className="flex items-center gap-2">
                        <GateIcon passed={check.passed} />
                        <span className={`text-sm font-bold ${check.passed ? 'text-om-running' : 'text-om-blocked'}`}>{check.passed ? '已通过' : '未通过'}</span>
                    </div>

                    <div>
                        <div className="text-[11px] font-bold tracking-wide text-om-faint">当前说明</div>
                        <p className="mt-1 text-sm leading-6 text-om-ink">{check.detail || '—'}</p>
                    </div>

                    <div>
                        <div className="text-[11px] font-bold tracking-wide text-om-faint">判定依据</div>
                        <p className="mt-1 text-sm leading-6 text-om-ink">{check.evidence || '—'}</p>
                    </div>

                    <div className="rounded-om-sm bg-om-chip px-3 py-3">
                        <div className="text-[11px] font-bold tracking-wide text-om-faint">这条门要求什么</div>
                        <p className="mt-1 text-xs leading-5 text-om-muted">{GATE_HELP[check.key] ?? '用于判断批次或 COA 是否满足放行、签发条件。'}</p>
                    </div>

                    {!check.passed && targetTab && (
                        <button type="button" onClick={() => onGoFix(targetTab)} className="btn-touch btn-primary w-full text-sm">
                            去补这项证据
                        </button>
                    )}
                </div>
            </aside>
        </div>
    );
}

function CoaPanel({ batch, document, issueGate, signatures = [], form, post, onShowIssueGate, onShowSignatures }) {
    const signatureRows = [...signatures]
        .sort((a, b) => String(a.signed_at ?? '').localeCompare(String(b.signed_at ?? '')))
        .slice(-4)
        .reverse();

    return (
        <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
            <div className="flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                <div>
                    <h2 className="text-sm font-bold text-om-ink">COA 文档</h2>
                    <p className="mt-0.5 text-xs text-om-muted">受控版本、签名与签发门</p>
                </div>
                <StatusBadge status={document.status} />
            </div>

            <div className="space-y-4 p-4">
                <LifecycleStepper document={document} />

                <div className="rounded-om-sm bg-om-chip/60 px-3 py-2">
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-[11px] font-bold text-om-muted">当前版本</span>
                        <span className="font-mono text-xs font-bold text-om-ink">V{String(document.current_version).padStart(2, '0')}</span>
                    </div>
                    <div className="mt-1 break-all font-mono text-[10px] leading-4 text-om-faint">{document.coa_number}</div>
                </div>

                <div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-[11px] font-bold tracking-wide text-om-faint">对外签发门</span>
                        <span className={`font-mono text-xs font-bold ${issueGate?.passed ? 'text-om-running' : 'text-om-blocked'}`}>
                            {issueGate?.passed_count ?? 0}/{issueGate?.total ?? 0}
                        </span>
                    </div>
                    {issueGate?.passed ? (
                        <p className="mt-1 text-xs text-om-running">已满足签发条件。</p>
                    ) : (
                        <>
                            <p className="mt-1 text-xs leading-5 text-om-muted">还差 {Math.max((issueGate?.total ?? 0) - (issueGate?.passed_count ?? 0), 0)} 项：{issueGate?.failed_labels ?? '—'}</p>
                            <button type="button" onClick={onShowIssueGate} className="mt-2 text-[11px] font-bold text-om-accent hover:underline">
                                查看签发门 →
                            </button>
                        </>
                    )}
                </div>

                <div className="border-t border-om-line2 pt-3">
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-[11px] font-bold tracking-wide text-om-faint">最近签名</span>
                        <button type="button" onClick={onShowSignatures} className="text-[11px] font-bold text-om-accent hover:underline">全部</button>
                    </div>
                    {signatureRows.length === 0 ? (
                        <p className="mt-2 text-xs text-om-faint">还没有签名记录。</p>
                    ) : (
                        <div className="mt-1 divide-y divide-om-line2">
                            {signatureRows.map((signature) => (
                                <SignatureLine key={`${signature.meaning}-${signature.id}`} signature={signature} compact />
                            ))}
                        </div>
                    )}
                </div>

                {!batch.released_at && (
                    <div className="border-t border-om-line2 pt-3">
                        <label className="text-[11px] font-bold tracking-wide text-om-faint" htmlFor="notes">放行备注（可选）</label>
                        <input
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) => form.setData('notes', event.target.value)}
                            placeholder="例如：首件确认通过，留样已入库"
                            className="mt-1 w-full rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/15"
                        />
                        <p className="mt-1 text-[11px] text-om-muted">放行时一并写入批次备注，不影响门禁判定。</p>
                    </div>
                )}

                {(document.status === 'ISSUED' || document.status === 'VOID') && (
                    <VersionOperation
                        document={document}
                        form={form}
                        post={post}
                        batchId={batch.id}
                    />
                )}
            </div>
        </div>
    );
}

function DraftPanel({ batch, releaseGate, form, post }) {
    const missing = Math.max((releaseGate?.total ?? 0) - (releaseGate?.passed_count ?? 0), 0);
    return (
        <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
            <div className="border-b border-om-line2 px-4 py-3">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-bold text-om-ink">COA 尚未生成</h2>
                        <p className="mt-0.5 text-xs text-om-muted">先冻结规格，再做 QA 复核</p>
                    </div>
                    <span className="rounded bg-om-chip px-2 py-0.5 text-[11px] font-bold text-om-muted">未生成</span>
                </div>
            </div>
            <div className="space-y-4 p-4">
                <p className="text-sm leading-6 text-om-muted">
                    草稿会冻结客户、产品、配方、检验结果和包装检查的快照。草稿可以在放行前生成，但只有放行门 10/10 后才能放行，签发门 12/12 后才能签发。
                </p>
                <div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-[11px] font-bold tracking-wide text-om-faint">放行门</span>
                        <span className={`font-mono text-xs font-bold ${releaseGate?.passed ? 'text-om-running' : 'text-om-downtime'}`}>
                            {releaseGate?.passed_count ?? 0}/{releaseGate?.total ?? 0}
                        </span>
                    </div>
                    {missing > 0 && <p className="mt-1 text-xs leading-5 text-om-muted">还差 {missing} 项：{releaseGate?.failed_labels ?? '—'}</p>}
                </div>
                {!batch.released_at && (
                    <div>
                        <label className="text-[11px] font-bold tracking-wide text-om-faint" htmlFor="notes">放行备注（可选）</label>
                        <input
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) => form.setData('notes', event.target.value)}
                            placeholder="例如：首件确认通过，留样已入库"
                            className="mt-1 w-full rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/15"
                        />
                    </div>
                )}
                <button
                    type="button"
                    disabled={form.processing}
                    onClick={() => post(`/admin/coa/${batch.id}/draft`)}
                    className="btn-touch btn-primary w-full text-sm disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {form.processing ? '处理中…' : '生成 COA 草稿'}
                </button>
            </div>
        </div>
    );
}

function LifecycleStepper({ document }) {
    const steps = [
        { key: 'DRAFT', label: '草稿', value: document.prepared_at },
        { key: 'QA_REVIEW', label: 'QA 复核', value: document.submitted_at },
        { key: 'APPROVED', label: '批准', value: document.approved_at },
        { key: 'ISSUED', label: '签发', value: document.issued_at },
    ];
    const current = document.status === 'VOID' ? 4 : Math.max(steps.findIndex((step) => step.key === document.status), 0);

    return (
        <div>
            <div className="grid grid-cols-4 gap-2">
                {steps.map((step, index) => {
                    const done = index < current;
                    const active = index === current && document.status !== 'VOID';
                    return (
                        <div key={step.key} className="min-w-0">
                            <div className="flex items-center">
                                <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${done ? 'bg-om-running' : active ? 'bg-om-accent' : 'bg-om-faintest'}`} />
                                {index < steps.length - 1 && <span className={`ml-2 h-px flex-1 ${done ? 'bg-om-running/30' : 'bg-om-line2'}`} />}
                            </div>
                            <div className={`mt-2 truncate text-[11px] font-bold ${done ? 'text-om-running' : active ? 'text-om-accent' : 'text-om-faint'}`}>{step.label}</div>
                            <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint" title={step.value ? formatDateTime(step.value) : '—'}>
                                {step.value ? formatDateTime(step.value) : active ? '进行中' : '—'}
                            </div>
                        </div>
                    );
                })}
            </div>
            {document.status === 'VOID' && (
                <div className="mt-3 rounded bg-om-blocked-bg px-3 py-2 text-xs text-om-blocked">
                    已作废{document.voided_at ? ` · ${formatDateTime(document.voided_at)}` : ''}{document.void_reason ? ` · ${document.void_reason}` : ''}
                </div>
            )}
        </div>
    );
}

function SignatureLine({ signature, compact = false }) {
    return (
        <div className={`flex items-start gap-3 ${compact ? 'py-2' : 'py-3'}`}>
            <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${signature.verified ? 'bg-om-running' : 'bg-om-blocked'}`} />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
                    <span className="text-sm font-semibold text-om-ink">{SIGNATURE_LABEL[signature.meaning] ?? signature.meaning}</span>
                    <span className="font-mono text-[10px] text-om-faint">{signature.signed_at ? formatDateTime(signature.signed_at) : '—'}</span>
                </div>
                <div className="mt-0.5 truncate text-[11px] text-om-muted">
                    {signature.user ?? '—'}
                    {signature.roles?.length ? ` · ${signature.roles.join(' / ')}` : ''}
                    {!signature.verified && <span className="ml-2 text-om-blocked">哈希校验失败</span>}
                </div>
                {!compact && <div className="mt-1 truncate font-mono text-[10px] text-om-faint">{signature.record_hash}</div>}
            </div>
        </div>
    );
}

function VersionOperation({ document, form, post, batchId }) {
    const isVoid = document.status === 'ISSUED';
    return (
        <div className="border-t border-om-line2 pt-3">
            <label className="text-[11px] font-bold tracking-wide text-om-faint" htmlFor="reason">
                {isVoid ? '作废原因' : '重签原因'}
            </label>
            <p className="mt-1 text-[11px] text-om-muted">写入版本记录并生成签名，旧版本不会被覆盖。</p>
            <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                <input
                    id="reason"
                    value={form.data.reason}
                    onChange={(event) => form.setData('reason', event.target.value)}
                    placeholder="例如：客户规格变更 / 标签数量不符"
                    className="min-w-0 flex-1 rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/15"
                />
                <button
                    type="button"
                    disabled={!form.data.reason.trim() || form.processing}
                    onClick={() => post(`/admin/coa/${batchId}/${isVoid ? 'void' : 'reissue'}`)}
                    className={`btn-touch text-sm disabled:cursor-not-allowed disabled:opacity-40 ${isVoid ? 'btn-danger' : 'btn-primary'}`}
                >
                    {isVoid ? '确认作废' : '创建新版本'}
                </button>
            </div>
        </div>
    );
}

function DetailTabs({ tab, onChange, tabs = TABS }) {
    return (
        <div className="overflow-x-auto border-b border-om-line">
            <div className="flex min-w-max items-center gap-4">
                {tabs.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => onChange(item.key)}
                        aria-selected={tab === item.key}
                        className={`whitespace-nowrap border-b-2 px-1 pb-2 pt-1 text-xs font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-om-accent/40 ${tab === item.key ? 'border-om-ink text-om-ink' : 'border-transparent text-om-muted hover:text-om-ink'}`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>
        </div>
    );
}

function ProductBatchSection({ batch, document }) {
    return (
        <Section title="产品与批次" subtitle="COA 文档头的冻结字段">
            <InfoGrid rows={[
                ['客户', batch.customer ?? '—'],
                ['客户编码', document?.customer_code ?? '—'],
                ['产品', batch.product ?? '—'],
                ['产品编码', batch.product_code ?? '—'],
                ['配方 / 版本', document?.revision_code ?? '—'],
                ['生产工单', batch.order_no ?? '—'],
                ['生产批次', batch.lot_number ?? `B${batch.batch_number}`],
                ['产线', batch.line ?? '—'],
                ['成品数量', `${formatNumber(batch.produced_qty)} ${batch.unit_of_measure || 'kg'}`],
                ['生产完成', batch.completed_at ? formatDateTime(batch.completed_at) : '—'],
                ['保质期至', batch.expiry_date ?? '—'],
                ['放行', batch.released_at ? `${batch.released_by ?? '—'} · ${formatDateTime(batch.released_at)}` : '未放行'],
            ]} />
        </Section>
    );
}

function DocumentStatusSection({ document }) {
    return (
        <Section title="文档状态" subtitle="受控 COA 生命周期">
            <div className="space-y-3 text-sm">
                <TimelineRow label="创建草稿" value={document.prepared_at} by={document.prepared_by} />
                <TimelineRow label="检验人确认" value={document.inspected_at} by={document.inspector} emptyLabel="未签字" />
                <TimelineRow label="提交复核" value={document.submitted_at} />
                <TimelineRow label="QA 批准" value={document.approved_at} by={document.approved_by} />
                <TimelineRow label="签发" value={document.issued_at} by={document.issued_by} />
                <TimelineRow label="作废" value={document.voided_at} by={document.voided_by} danger emptyLabel="未作废" />
                {document.void_reason && (
                    <div className="rounded bg-om-blocked-bg px-3 py-2 text-xs text-om-blocked">{document.void_reason}</div>
                )}
            </div>
        </Section>
    );
}

function VersionHistorySection({ versions }) {
    return (
        <Section title="版本历史" subtitle="每次重签新增版本，旧版本保留">
            {versions.length === 0 ? (
                <p className="text-sm text-om-faint">还没有版本记录。</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full table-fixed text-sm">
                        <thead>
                            <tr className="border-b border-om-line2 text-left text-xs font-bold text-om-muted">
                                <th className="py-2 pr-3">版本</th>
                                <th className="py-2 pr-3">状态</th>
                                <th className="py-2 pr-3">原因</th>
                                <th className="py-2">签发时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            {versions.map((version) => (
                                <tr key={version.id} className="border-b border-om-line2 last:border-0">
                                    <td className="py-2 pr-3 font-mono font-bold text-om-ink">V{String(version.version).padStart(2, '0')}</td>
                                    <td className="py-2 pr-3"><StatusBadge status={version.status} /></td>
                                    <td className="py-2 pr-3 text-om-muted">{version.change_reason ?? '—'}</td>
                                    <td className="py-2 font-mono text-xs text-om-muted">{version.issued_at ? formatDateTime(version.issued_at) : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Section>
    );
}

function SignaturesSection({ signatures }) {
    return (
        <Section title="电子签名" subtitle="姓名、时间、含义与记录哈希">
            {signatures.length === 0 ? (
                <p className="text-sm text-om-faint">还没有签名记录。</p>
            ) : (
                <div className="divide-y divide-om-line2">
                    {signatures.map((signature) => (
                        <SignatureLine key={`${signature.meaning}-${signature.id}`} signature={signature} />
                    ))}
                </div>
            )}
        </Section>
    );
}

function RecordTab({ batch, document, versions, separation, batchSignatures, documentSignatures }) {
    if (!document) {
        return <ProductBatchSection batch={batch} document={null} />;
    }

    return (
        <div className="space-y-4">
            <div className="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
                <ProductBatchSection batch={batch} document={document} />
                <DocumentStatusSection document={document} />
            </div>
            <VersionHistorySection versions={versions} />
            <SignaturesSection signatures={[...batchSignatures, ...documentSignatures]} />
            <SeparationPanel separation={separation} />
        </div>
    );
}

function SpecSnapshotSection({ document }) {
    return (
        <Section title="COA 规格快照" subtitle="批准 / 签发时冻结，之后不随主数据变化">
            {(document.specification?.standard_code || document.specification?.sensory_profile) && (
                <div className="mb-3 rounded bg-om-chip px-3 py-2 text-xs text-om-muted">
                    {document.specification?.standard_code && (
                        <span className="font-medium text-om-ink">客户标准：{document.specification.standard_code} {document.specification.standard_version}</span>
                    )}
                    {document.specification?.sensory_profile && (
                        <span className="ml-2">感官：{document.specification.sensory_profile}</span>
                    )}
                </div>
            )}
            {document.specification?.parameters?.length ? (
                <div className="overflow-x-auto">
                    <table className="w-full table-fixed text-sm">
                        <thead>
                            <tr className="border-b border-om-line2 text-left text-xs font-bold text-om-muted">
                                <th className="py-2 pr-4">检验项目</th>
                                <th className="py-2 pr-4">类型</th>
                                <th className="py-2 pr-4">限值</th>
                                <th className="py-2">单位</th>
                            </tr>
                        </thead>
                        <tbody>
                            {document.specification.parameters.map((parameter, index) => (
                                <tr key={index} className="border-b border-om-line2 last:border-0">
                                    <td className="py-2 pr-4 font-medium text-om-ink">{parameter.name ?? parameter['名称'] ?? `项目 ${index + 1}`}</td>
                                    <td className="py-2 pr-4 text-om-muted">{parameter.type === 'measurement' ? '测量值' : '合格 / 不合格'}</td>
                                    <td className="py-2 pr-4 font-mono text-om-ink">
                                        {parameter.type === 'measurement'
                                            ? `${parameter.min ?? '—'} – ${parameter.max ?? '—'}`
                                            : '符合 / 不符合'}
                                    </td>
                                    <td className="py-2 text-om-muted">{parameter.unit ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <p className="text-sm text-om-faint">尚未冻结检验规格。请先完成检验模板与成品检验。</p>
            )}
        </Section>
    );
}

function QualitySnapshotSection({ document }) {
    return (
        <Section title="检验结果快照" subtitle="来自最近一次批次质量检查">
            {document.quality?.samples?.length ? (
                <div className="overflow-x-auto">
                    <table className="w-full table-fixed text-sm">
                        <thead>
                            <tr className="border-b border-om-line2 text-left text-xs font-bold text-om-muted">
                                <th className="py-2 pr-4">样品</th>
                                <th className="py-2 pr-4">项目</th>
                                <th className="py-2 pr-4">实测</th>
                                <th className="py-2">判定</th>
                            </tr>
                        </thead>
                        <tbody>
                            {document.quality.samples.map((sample, index) => (
                                <tr key={index} className="border-b border-om-line2 last:border-0">
                                    <td className="py-2 pr-4 font-mono text-om-muted">{sample.sample_number}</td>
                                    <td className="py-2 pr-4 font-medium text-om-ink">{sample.parameter_name}</td>
                                    <td className="py-2 pr-4 font-mono text-om-ink">
                                        {sample.parameter_type === 'measurement'
                                            ? sample.value_numeric ?? '—'
                                            : sample.value_boolean === null ? '—' : sample.value_boolean ? '是' : '否'}
                                    </td>
                                    <td className="py-2"><PassBadge pass={sample.is_passed} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="mt-3 text-xs text-om-muted">
                        检验时间 {document.quality.checked_at ? formatDateTime(document.quality.checked_at) : '—'} · 检验人 {document.quality.checked_by ?? '—'}
                    </p>
                </div>
            ) : (
                <p className="text-sm text-om-faint">尚无检验结果快照。</p>
            )}
        </Section>
    );
}

function BatchStatusBadge({ status }) {
    const styles = {
        PENDING: 'bg-om-chip text-om-muted',
        IN_PROGRESS: 'bg-om-accepted-bg text-om-accepted',
        DONE: 'bg-om-done-bg text-om-done',
        CANCELLED: 'bg-om-blocked-bg text-om-blocked',
    };
    return (
        <span className={`inline-block rounded px-2 py-0.5 text-xs font-bold ${styles[status] ?? styles.PENDING}`}>
            {BATCH_STATUS_LABEL[status] ?? status}
        </span>
    );
}

const LAB_ITEM_EXAMPLE = `农残|草甘膦|0.08 mg/kg|≤ 1.0 mg/kg|合格
农残|吡虫啉|0.12 mg/kg|≤ 0.5 mg/kg|合格
农残|联苯菊酯|0.05 mg/kg|≤ 0.5 mg/kg|合格
重金属|铅|0.32 mg/kg|≤ 5.0 mg/kg|合格
重金属|镉|0.08 mg/kg|≤ 1.0 mg/kg|合格
重金属|砷|0.15 mg/kg|≤ 2.0 mg/kg|合格
微生物|菌落总数|120 CFU/g|≤ 1000 CFU/g|合格
微生物|大肠菌群|< 10 MPN/100g|≤ 10 MPN/100g|合格
微生物|霉菌|< 10 CFU/g|≤ 100 CFU/g|合格`;

function WeighingPanel({ batch, weighing }) {
    const { errors = {} } = usePage().props;
    const [openKey, setOpenKey] = useState(null);
    const [form, setForm] = useState({ actual_qty: '', tolerance_pct: '0.5', scale_code: '', notes: '' });
    const rows = weighing?.rows ?? [];
    const allPassed = Boolean(weighing?.all_passed);
    const missing = rows.filter((row) => !row.record).length;
    const failed = rows.filter((row) => row.record && row.record.status !== 'PASS').length;

    const openForm = (row) => {
        setOpenKey(`${row.material_id}-${row.step_number}`);
        setForm({
            actual_qty: row.record?.actual_qty ?? row.target_qty,
            tolerance_pct: String(row.record?.tolerance_pct ?? row.tolerance_pct ?? 0.5),
            scale_code: row.record?.scale_code ?? '',
            notes: row.record?.notes ?? '',
        });
    };

    const submit = (row) => {
        router.post(`/admin/coa/${batch.id}/weighings`, {
            material_id: row.material_id,
            step_number: row.step_number,
            actual_qty: form.actual_qty,
            tolerance_pct: form.tolerance_pct,
            scale_code: form.scale_code,
            notes: form.notes,
        }, { preserveScroll: true, onSuccess: () => setOpenKey(null) });
    };

    return (
        <Section title="投料称重" subtitle="BOM 原料目标量 vs 电子秤实际读数；超差或缺失会卡住放行门">
            <div className={`rounded border px-4 py-3 ${allPassed ? 'border-om-running/40 bg-om-running-bg' : 'border-om-blocked/40 bg-om-blocked-bg'}`}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="text-sm font-semibold text-om-ink">
                        {weighing?.passed ?? 0}/{weighing?.total ?? 0} 种原料在公差内
                    </div>
                    <div className="text-xs text-om-muted">
                        {missing > 0 ? `缺 ${missing} 项` : ''}{missing > 0 && failed > 0 ? ' · ' : ''}{failed > 0 ? `超差 ${failed} 项` : ''}
                        {missing === 0 && failed === 0 ? '全部有称重记录' : ''}
                    </div>
                </div>

                <div className="mt-3 overflow-x-auto">
                    <table className="w-full table-fixed text-xs">
                        <thead>
                            <tr className="border-b border-om-line2 text-left text-om-muted">
                                <th className="w-[34%] py-1 pr-2 font-semibold">原料</th>
                                <th className="w-[17%] py-1 pr-2 font-semibold">目标量</th>
                                <th className="w-[17%] py-1 pr-2 font-semibold">实际量</th>
                                <th className="hidden w-[16%] py-1 pr-2 font-semibold lg:table-cell">偏差</th>
                                <th className="hidden w-[12%] py-1 pr-2 font-semibold xl:table-cell">公差</th>
                                <th className="hidden w-[18%] py-1 pr-2 font-semibold xl:table-cell">秤 / 操作人</th>
                                <th className="w-[18%] py-1 font-semibold">判定</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const key = `${row.material_id}-${row.step_number}`;
                                const record = row.record;
                                const isOpen = openKey === key;
                                return (
                                    <React.Fragment key={key}>
                                        <tr className="border-b border-om-line2/60">
                                            <td className="py-1 pr-2">
                                                <div className="font-semibold text-om-ink">{row.material_name}</div>
                                                <div className="text-[11px] text-om-muted">{row.material_code || '—'} · 工序 {row.step_number ?? '—'}</div>
                                            </td>
                                            <td className="py-1 pr-2 text-om-ink">{formatNumber(row.target_qty, 2)} {row.unit_of_measure}</td>
                                            <td className="py-1 pr-2 text-om-ink">{record ? `${formatNumber(record.actual_qty, 2)} ${row.unit_of_measure}` : '—'}</td>
                                            <td className="hidden py-1 pr-2 text-om-muted lg:table-cell">{record ? `${record.variance_qty > 0 ? '+' : ''}${formatNumber(record.variance_qty, 2)} / ${record.variance_pct > 0 ? '+' : ''}${formatNumber(record.variance_pct, 2)}%` : '—'}</td>
                                            <td className="hidden py-1 pr-2 text-om-muted xl:table-cell">±{formatNumber(record?.tolerance_pct ?? row.tolerance_pct, 2)}%</td>
                                            <td className="hidden py-1 pr-2 text-om-muted xl:table-cell">{record ? `${record.scale_code || '—'} · ${record.weighed_by || '—'}` : '—'}</td>
                                            <td className="py-1">
                                                {record ? (
                                                    <span className={`font-semibold ${record.status === 'PASS' ? 'text-om-running' : 'text-om-blocked'}`}>
                                                        {record.status === 'PASS' ? '合格' : '超差'}
                                                    </span>
                                                ) : (
                                                    <span className="font-semibold text-om-blocked">未登记</span>
                                                )}
                                                <button type="button" onClick={() => openForm(row)} className="ml-2 text-[11px] font-bold text-om-accent hover:underline">
                                                    {record ? '复称' : '登记'}
                                                </button>
                                            </td>
                                        </tr>
                                        {isOpen && (
                                            <tr className="border-b border-om-line2/60 bg-om-card">
                                                <td colSpan={7} className="p-2">
                                                    <div className="grid gap-2 sm:grid-cols-5">
                                                        <label className="text-[11px] text-om-muted">实际读数（{row.unit_of_measure}）
                                                            <input type="number" step="0.001" value={form.actual_qty} onChange={(e) => setForm({ ...form, actual_qty: e.target.value })} className="mt-1 w-full rounded border border-om-line bg-om-card px-2 py-1 text-sm text-om-ink outline-none focus:border-om-accent" />
                                                        </label>
                                                        <label className="text-[11px] text-om-muted">公差（%）
                                                            <input type="number" step="0.001" value={form.tolerance_pct} onChange={(e) => setForm({ ...form, tolerance_pct: e.target.value })} className="mt-1 w-full rounded border border-om-line bg-om-card px-2 py-1 text-sm text-om-ink outline-none focus:border-om-accent" />
                                                        </label>
                                                        <label className="text-[11px] text-om-muted">秤编号
                                                            <input value={form.scale_code} onChange={(e) => setForm({ ...form, scale_code: e.target.value })} placeholder="SCALE-01" className="mt-1 w-full rounded border border-om-line bg-om-card px-2 py-1 text-sm text-om-ink outline-none focus:border-om-accent" />
                                                        </label>
                                                        <label className="text-[11px] text-om-muted sm:col-span-2">备注
                                                            <input value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} placeholder="复称原因 / 偏差处理" className="mt-1 w-full rounded border border-om-line bg-om-card px-2 py-1 text-sm text-om-ink outline-none focus:border-om-accent" />
                                                        </label>
                                                    </div>
                                                    {errors.actual_qty && <p className="mt-1 text-xs font-semibold text-om-blocked">{errors.actual_qty}</p>}
                                                    <div className="mt-2 flex justify-end gap-2">
                                                        <button type="button" onClick={() => setOpenKey(null)} className="btn-touch btn-secondary text-xs">取消</button>
                                                        <button type="button" onClick={() => submit(row)} className="btn-touch btn-primary text-xs">保存称重</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </React.Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                {rows.length === 0 && <p className="mt-2 text-sm text-om-muted">当前 BOM 没有需要称重的原料。</p>}
            </div>
        </Section>
    );
}

function LabTestPanel({ batch, labTest, labTestRequired = false }) {
    const { errors = {} } = usePage().props;
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        lab_name: '',
        report_number: '',
        sample_number: '',
        sampled_at: '',
        issued_at: new Date().toISOString().slice(0, 10),
        overall_conclusion: 'PASS',
        items_text: LAB_ITEM_EXAMPLE,
        notes: '',
    });

    const setField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
    const submit = () => {
        router.post(`/admin/coa/${batch.id}/lab-tests`, form, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const state = !labTest
        ? (labTestRequired ? 'missing' : 'not-required')
        : (labTest.overall_conclusion === 'PASS' ? 'pass' : 'fail');
    const tone = state === 'pass'
        ? 'border-om-running/40 bg-om-running-bg'
        : state === 'fail' || state === 'missing'
            ? 'border-om-blocked/40 bg-om-blocked-bg'
            : 'border-om-line bg-om-card';

    return (
        <Section title="外检报告" subtitle="农残、重金属、微生物；客户标准要求外检时，报告合格才开放行门">
            <div className={`rounded border px-4 py-3 ${tone}`}>
                {labTest ? (
                    <>
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div className="text-sm font-bold text-om-ink">{labTest.report_number}</div>
                                <div className="mt-0.5 text-xs text-om-muted">{labTest.lab_name}</div>
                            </div>
                            <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${labTest.overall_conclusion === 'PASS' ? 'bg-om-running-bg text-om-running' : 'bg-om-blocked-bg text-om-blocked'}`}>
                                {labTest.overall_conclusion === 'PASS' ? '报告合格' : '报告不合格'}
                            </span>
                        </div>
                        <div className="mt-2 text-xs text-om-muted">
                            抽样 {labTest.sampled_at || '—'} ｜ 签发 {labTest.issued_at || '—'}
                            {labTest.sample_number ? ` ｜ 样品编号 ${labTest.sample_number}` : ''}
                            {labTest.recorded_by ? ` ｜ 登记人 ${labTest.recorded_by}` : ''}
                        </div>
                        {labTest.attachment_url && (
                            <div className="mt-2">
                                <a href={labTest.attachment_url} className="text-xs font-bold text-om-accent hover:underline">
                                    查看报告附件：{labTest.attachment_name}
                                </a>
                            </div>
                        )}
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full table-fixed text-xs">
                                <thead>
                                    <tr className="border-b border-om-line2 text-left text-om-muted">
                                        <th className="w-[38%] py-1 pr-2 font-semibold">项目</th>
                                        <th className="w-[24%] py-1 pr-2 font-semibold">实测</th>
                                        <th className="w-[24%] py-1 pr-2 font-semibold">限值</th>
                                        <th className="w-[14%] py-1 font-semibold">判定</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(labTest.items ?? []).map((item, index) => (
                                        <tr key={`${item.category}-${item.name}-${index}`} className="border-b border-om-line2/60">
                                            <td className="py-1 pr-2">
                                                <div className="font-semibold text-om-ink">{item.name}</div>
                                                <div className="text-[11px] text-om-muted">{item.category}</div>
                                            </td>
                                            <td className="py-1 pr-2 break-words text-om-ink">{item.result}</td>
                                            <td className="py-1 pr-2 break-words text-om-muted">{item.limit}</td>
                                            <td className={`py-1 font-semibold ${item.conclusion === '合格' ? 'text-om-running' : 'text-om-blocked'}`}>{item.conclusion}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {labTest.notes && <p className="mt-2 text-xs text-om-muted">{labTest.notes}</p>}
                    </>
                ) : (
                    <p className="text-sm font-semibold text-om-ink">
                        {labTestRequired
                            ? '客户标准要求外检，尚未登记第三方报告；放行门会判失败。'
                            : '客户标准未要求外检；如客户临时要求，可在这里补登。'}
                    </p>
                )}

                <div className="mt-3 flex justify-end">
                    <button type="button" onClick={() => setOpen((value) => !value)} className="btn-touch btn-secondary text-xs">
                        {open ? '收起' : (labTest ? '重新登记报告' : '登记外检报告')}
                    </button>
                </div>

                {open && (
                    <div className="mt-3 space-y-2 border-t border-om-line2 pt-3">
                        <div className="grid gap-2 sm:grid-cols-2">
                            <input value={form.lab_name} onChange={(e) => setField('lab_name', e.target.value)} placeholder="检测机构" className="rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent" />
                            <input value={form.report_number} onChange={(e) => setField('report_number', e.target.value)} placeholder="报告编号" className="rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent" />
                            <input value={form.sample_number} onChange={(e) => setField('sample_number', e.target.value)} placeholder="样品编号（可选）" className="rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent" />
                            <select value={form.overall_conclusion} onChange={(e) => setField('overall_conclusion', e.target.value)} className="rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent">
                                <option value="PASS">总体结论：合格</option>
                                <option value="FAIL">总体结论：不合格</option>
                            </select>
                            <input type="date" value={form.sampled_at} onChange={(e) => setField('sampled_at', e.target.value)} className="rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent" />
                            <input type="date" value={form.issued_at} onChange={(e) => setField('issued_at', e.target.value)} className="rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent" />
                        </div>
                        <textarea
                            rows={7}
                            value={form.items_text}
                            onChange={(e) => setField('items_text', e.target.value)}
                            className="w-full rounded border border-om-line bg-om-card px-3 py-2 font-mono text-xs text-om-ink outline-none focus:border-om-accent"
                        />
                        <p className="text-[11px] text-om-muted">每行一条，格式：类别|项目|结果|限值|判定。类别建议用“农残 / 重金属 / 微生物”。</p>
                        {labTest && (
                            <div className="rounded border border-dashed border-om-line bg-om-card px-3 py-2">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <div className="text-xs font-bold text-om-ink">报告原件附件</div>
                                        <p className="text-[11px] text-om-muted">支持 PDF / JPG / PNG，最大 20 MB；上传后可在上方直接查看。</p>
                                    </div>
                                    <label className="btn-touch btn-secondary cursor-pointer text-xs">
                                        上传 / 替换附件
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            className="hidden"
                                            onChange={(event) => {
                                                const file = event.target.files?.[0];
                                                if (!file) return;
                                                router.post(`/admin/coa/${batch.id}/lab-tests/${labTest.id}/attachment`, { attachment: file }, {
                                                    forceFormData: true,
                                                    preserveScroll: true,
                                                });
                                                event.target.value = '';
                                            }}
                                        />
                                    </label>
                                </div>
                                {errors.attachment && <p className="mt-1 text-xs font-semibold text-om-blocked">{errors.attachment}</p>}
                            </div>
                        )}
                        {errors.items_text && <p className="text-xs font-semibold text-om-blocked">{errors.items_text}</p>}
                        {errors.report_number && <p className="text-xs font-semibold text-om-blocked">{errors.report_number}</p>}
                        <div className="flex justify-end">
                            <button type="button" onClick={submit} className="btn-touch btn-primary text-xs">保存外检报告</button>
                        </div>
                    </div>
                )}
            </div>
        </Section>
    );
}

function SeparationPanel({ separation }) {
    if (!separation) return null;

    const approved = Boolean(separation.approved_by);
    const passed = Boolean(separation.passed);
    const tone = passed
        ? 'border-om-running/40 bg-om-running-bg'
        : approved
            ? 'border-om-blocked/40 bg-om-blocked-bg'
            : 'border-om-line bg-om-card';

    return (
        <Section title="双人复核" subtitle="放行人与 COA 批准人必须为不同人员，同一账号不能自己放行自己批准">
            <div className={`rounded border px-4 py-3 ${tone}`}>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-wide text-om-muted">放行人</div>
                        <div className="mt-1 text-sm font-bold text-om-ink">{separation.released_by ?? '未放行'}</div>
                        <div className="text-xs text-om-muted">{separation.released_roles?.join(' / ') || '无角色记录'}</div>
                    </div>
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-wide text-om-muted">COA 批准人</div>
                        <div className="mt-1 text-sm font-bold text-om-ink">{separation.approved_by ?? '未批准'}</div>
                        <div className="text-xs text-om-muted">{separation.approved_roles?.join(' / ') || '无角色记录'}</div>
                    </div>
                </div>
                <p className="mt-3 text-xs font-semibold text-om-muted">{separation.detail}</p>
            </div>
        </Section>
    );
}

const RELEASE_CHECK_META = [
    {
        key: 'RETENTION_SAMPLE',
        title: '留样登记',
        hint: '成品留样已封样、贴标、入库，数量与批号可追溯。',
        placeholder: '留样编号 / 数量 / 位置，例如 LY-2643-01，2 袋，留样柜 A',
    },
    {
        key: 'LINE_CLEARANCE',
        title: '清线检查',
        hint: '上一批物料、标签、半成品已清离产线，现场无混批风险。',
        placeholder: '清线人 / 检查点，例如 拼配机、筛分机、包装台已清空',
    },
    {
        key: 'LABEL_BALANCE',
        title: '标签平衡',
        hint: '领用、使用、报废、退回标签数量闭合，无标签流失。',
        placeholder: '领用 / 使用 / 报废 / 退回，例如 500 / 498 / 2 / 0',
    },
];

function ReleaseCheckPanel({ batch, releaseChecks = {} }) {
    const [notes, setNotes] = useState({});

    const record = (type, status) => {
        router.post(`/admin/coa/${batch.id}/checks`, {
            check_type: type,
            status,
            evidence: notes[type] ?? '',
        }, { preserveScroll: true });
    };

    return (
        <Section title="批记录确认" subtitle="G06–G08：留样、清线、标签平衡；每次登记追加，放行门取最新一条">
            <div className="space-y-3">
                {RELEASE_CHECK_META.map((meta) => {
                    const check = releaseChecks[meta.key];
                    const tone = check?.status === 'PASS'
                        ? 'bg-om-running-bg text-om-running'
                        : check?.status === 'FAIL'
                            ? 'bg-om-blocked-bg text-om-blocked'
                            : 'bg-om-chip text-om-muted';

                    return (
                        <div key={meta.key} className="rounded border border-om-line2 p-3">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div className="text-sm font-bold text-om-ink">{meta.title}</div>
                                    <div className="mt-0.5 text-xs text-om-muted">{meta.hint}</div>
                                </div>
                                <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${tone}`}>
                                    {check?.status === 'PASS' ? '已通过' : check?.status === 'FAIL' ? '未通过' : '未登记'}
                                </span>
                            </div>

                            {check && (
                                <div className="mt-2 text-xs text-om-muted">
                                    {check.checked_by ?? '—'} · {formatDateTime(check.checked_at)}
                                    {check.evidence ? ` · ${check.evidence}` : ''}
                                </div>
                            )}

                            <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input
                                    value={notes[meta.key] ?? ''}
                                    onChange={(event) => setNotes((current) => ({ ...current, [meta.key]: event.target.value }))}
                                    placeholder={meta.placeholder}
                                    className="min-w-0 flex-1 rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/15"
                                />
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => record(meta.key, 'PASS')}
                                        className="btn-touch btn-primary text-xs"
                                    >
                                        登记通过
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => record(meta.key, 'FAIL')}
                                        className="btn-touch btn-danger text-xs"
                                    >
                                        登记不通过
                                    </button>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </Section>
    );
}

function Section({ title, subtitle, children }) {
    return (
        <div className="overflow-hidden rounded-om-sm bg-om-card shadow-sm">
            <div className="border-b border-om-line2 px-5 py-4">
                <h2 className="text-base font-bold text-om-ink">{title}</h2>
                {subtitle && <p className="mt-1 text-xs text-om-muted">{subtitle}</p>}
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
}

function InfoGrid({ rows }) {
    return (
        <div className="grid gap-x-6 gap-y-0 sm:grid-cols-2">
            {rows.map(([label, value], index) => (
                <div key={index} className="border-b border-om-line2 py-2 last:border-0">
                    <div className="text-[11px] font-bold tracking-wide text-om-faint">{label}</div>
                    <div className="mt-0.5 text-sm text-om-ink">{value}</div>
                </div>
            ))}
        </div>
    );
}

function TimelineRow({ label, value, by, danger = false, emptyLabel = '未完成' }) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-om-line2 pb-2 last:border-0">
            <div>
                <div className={`text-sm font-medium ${danger ? 'text-om-blocked' : 'text-om-ink'}`}>{label}</div>
                {by && <div className="mt-0.5 text-xs text-om-muted">{by}</div>}
            </div>
            <div className="text-right font-mono text-xs text-om-muted">{value ? formatDateTime(value) : emptyLabel}</div>
        </div>
    );
}

function StatusBadge({ status }) {
    const styles = {
        DRAFT: 'bg-om-chip text-om-muted',
        QA_REVIEW: 'bg-om-accent-bg text-om-accent',
        APPROVED: 'bg-om-accent-bg text-om-accent',
        ISSUED: 'bg-om-running-bg text-om-running',
        VOID: 'bg-om-blocked-bg text-om-blocked',
    };
    return (
        <span className={`inline-block rounded px-2 py-0.5 text-xs font-bold ${styles[status] ?? styles.DRAFT}`}>
            {STATUS_LABEL[status] ?? status}
        </span>
    );
}

function PassBadge({ pass }) {
    if (pass === null || pass === undefined) {
        return <span className="rounded bg-om-chip px-2 py-0.5 text-xs font-bold text-om-muted">未判定</span>;
    }
    return (
        <span className={`rounded px-2 py-0.5 text-xs font-bold ${pass ? 'bg-om-running-bg text-om-running' : 'bg-om-blocked-bg text-om-blocked'}`}>
            {pass ? '合格' : '不合格'}
        </span>
    );
}

CoaShow.layout = (page) => <AppLayout>{page}</AppLayout>;
