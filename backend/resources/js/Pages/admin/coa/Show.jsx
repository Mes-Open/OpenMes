import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
    COA_APPROVAL: 'COA 批准',
    COA_ISSUE: 'COA 签发',
    COA_VOID: 'COA 作废',
};

export default function CoaShow() {
    const { batch, document, versions = [], releaseGate, issueGate, batchSignatures = [], documentSignatures = [] } = usePage().props;
    const form = useForm({ notes: '', reason: '' });

    const post = (path) => form.post(path, { preserveScroll: true });

    if (!document) {
        return <NoCoa batch={batch} releaseGate={releaseGate} form={form} post={post} />;
    }

    return (
        <>
            <Head title={`${document.coa_number} — COA 与放行`} />
            <div className="mx-auto max-w-[1180px] space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href="/admin/coa" className="text-xs font-bold text-om-accent hover:underline">← 返回 COA 工作台</Link>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-bold text-om-ink">{document.coa_number}</h1>
                            <StatusBadge status={document.status} />
                            <span className="rounded bg-om-chip px-2 py-0.5 text-xs font-bold text-om-muted">
                                V{String(document.current_version).padStart(2, '0')}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-om-muted">
                            {batch.lot_number ?? `B${batch.batch_number}`} · {batch.product ?? '—'} · {batch.customer ?? '—'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={`/admin/coa/${batch.id}/pdf`} className="btn-touch btn-secondary text-sm">
                            下载 PDF
                        </a>
                        {!batch.released_at && (
                            <button
                                type="button"
                                disabled={!releaseGate.passed || form.processing}
                                onClick={() => post(`/admin/coa/${batch.id}/release`)}
                                className="btn-touch btn-primary text-sm disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                确认放行
                            </button>
                        )}
                        {document.status === 'DRAFT' && (
                            <button type="button" disabled={form.processing} onClick={() => post(`/admin/coa/${batch.id}/submit`)} className="btn-touch btn-primary text-sm disabled:opacity-40">
                                提交 QA 复核
                            </button>
                        )}
                        {document.status === 'QA_REVIEW' && (
                            <button
                                type="button"
                                disabled={!batch.released_at || form.processing}
                                onClick={() => post(`/admin/coa/${batch.id}/approve`)}
                                className="btn-touch btn-primary text-sm disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                批准 COA
                            </button>
                        )}
                        {document.status === 'APPROVED' && (
                            <button
                                type="button"
                                disabled={!issueGate.passed || form.processing}
                                onClick={() => post(`/admin/coa/${batch.id}/issue`)}
                                className="btn-touch btn-primary text-sm disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                签发 COA
                            </button>
                        )}
                        {document.status === 'ISSUED' && (
                            <button type="button" disabled={form.processing} onClick={() => post(`/admin/coa/${batch.id}/void`)} className="btn-touch btn-danger text-sm disabled:opacity-40">
                                作废
                            </button>
                        )}
                        {document.status === 'VOID' && (
                            <button type="button" disabled={form.processing} onClick={() => post(`/admin/coa/${batch.id}/reissue`)} className="btn-touch btn-primary text-sm disabled:opacity-40">
                                重签
                            </button>
                        )}
                    </div>
                </div>

                {Object.keys(form.errors).length > 0 && (
                    <div className="rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-4 py-3 text-sm text-om-blocked">
                        {Object.values(form.errors).map((error) => <div key={error}>{error}</div>)}
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <GatePanel title="放行门" subtitle="批次能否放行到销售" gate={releaseGate} />
                    <GatePanel title="签发门" subtitle="COA 能否对外签发" gate={issueGate} />
                </div>

                {(document.status === 'ISSUED' || document.status === 'VOID') && (
                    <div className="rounded-om-sm border border-om-line bg-om-card px-5 py-4 shadow-sm">
                        <label className="text-sm font-bold text-om-ink" htmlFor="reason">
                            {document.status === 'ISSUED' ? '作废原因' : '重签原因'}
                        </label>
                        <p className="mt-1 text-xs text-om-muted">写入版本记录并生成签名，旧版本不会被覆盖。</p>
                        <div className="mt-3 flex flex-col gap-2 sm:flex-row">
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
                                onClick={() => post(`/admin/coa/${batch.id}/${document.status === 'ISSUED' ? 'void' : 'reissue'}`)}
                                className="btn-touch btn-danger text-sm disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {document.status === 'ISSUED' ? '确认作废' : '创建新版本'}
                            </button>
                        </div>
                    </div>
                )}

                {!batch.released_at && (
                    <div className="rounded-om-sm border border-om-line bg-om-card px-5 py-4 shadow-sm">
                        <label className="text-sm font-bold text-om-ink" htmlFor="notes">放行备注</label>
                        <p className="mt-1 text-xs text-om-muted">可选。写入批次备注，不影响放行门判定。</p>
                        <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) => form.setData('notes', event.target.value)}
                                placeholder="例如：首件确认通过，留样已入库"
                                className="min-w-0 flex-1 rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/15"
                            />
                            <button
                                type="button"
                                disabled={!releaseGate.passed || form.processing}
                                onClick={() => post(`/admin/coa/${batch.id}/release`)}
                                className="btn-touch btn-primary text-sm disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {form.processing ? '处理中…' : '放行并签名'}
                            </button>
                        </div>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
                    <Section title="产品与批次" subtitle="COA 文档头的冻结字段">
                        <InfoGrid rows={[
                            ['客户', batch.customer ?? '—'],
                            ['客户编码', document.customer_code ?? '—'],
                            ['产品', batch.product ?? '—'],
                            ['产品编码', batch.product_code ?? '—'],
                            ['配方 / 版本', document.revision_code ?? '—'],
                            ['生产工单', batch.order_no ?? '—'],
                            ['生产批次', batch.lot_number ?? `B${batch.batch_number}`],
                            ['产线', batch.line ?? '—'],
                            ['成品数量', `${formatNumber(batch.produced_qty)} kg`],
                            ['生产完成', batch.completed_at ? formatDateTime(batch.completed_at) : '—'],
                            ['保质期至', batch.expiry_date ?? '—'],
                            ['放行', batch.released_at ? `${batch.released_by ?? '—'} · ${formatDateTime(batch.released_at)}` : '未放行'],
                        ]} />
                    </Section>

                    <Section title="文档状态" subtitle="受控 COA 生命周期">
                        <div className="space-y-3 text-sm">
                            <TimelineRow label="创建草稿" value={document.prepared_at} by={document.prepared_by} />
                            <TimelineRow label="提交复核" value={document.submitted_at} />
                            <TimelineRow label="QA 批准" value={document.approved_at} by={document.approved_by} />
                            <TimelineRow label="签发" value={document.issued_at} by={document.issued_by} />
                            <TimelineRow label="作废" value={document.voided_at} by={document.voided_by} danger emptyLabel="未作废" />
                            {document.void_reason && (
                                <div className="rounded bg-om-blocked-bg px-3 py-2 text-xs text-om-blocked">
                                    {document.void_reason}
                                </div>
                            )}
                        </div>
                    </Section>
                </div>

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
                            <table className="w-full min-w-[640px] text-sm">
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

                <Section title="检验结果快照" subtitle="来自最近一次批次质量检查">
                    {document.quality?.samples?.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
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
                                            <td className="py-2">
                                                <PassBadge pass={sample.is_passed} />
                                            </td>
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

                <div className="grid gap-4 lg:grid-cols-2">
                    <Section title="版本历史" subtitle="每次重签新增版本，旧版本保留">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[520px] text-sm">
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
                    </Section>

                    <Section title="电子签名" subtitle="姓名、时间、含义与记录哈希">
                        {[...batchSignatures, ...documentSignatures].length === 0 ? (
                            <p className="text-sm text-om-faint">还没有签名记录。</p>
                        ) : (
                            <div className="space-y-3">
                                {[...batchSignatures, ...documentSignatures].map((signature) => (
                                    <div key={`${signature.meaning}-${signature.id}`} className="rounded border border-om-line2 px-3 py-2">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="text-sm font-bold text-om-ink">
                                                {SIGNATURE_LABEL[signature.meaning] ?? signature.meaning}
                                                <span className={`ml-2 rounded px-1.5 py-0.5 text-[10px] font-normal ${signature.verified ? 'bg-om-running/10 text-om-running' : 'bg-om-blocked/10 text-om-blocked'}`}>
                                                    {signature.verified ? '哈希校验通过' : '哈希校验失败'}
                                                </span>
                                            </span>
                                            <span className="text-xs text-om-muted">{signature.user ?? '—'} · {signature.signed_at ? formatDateTime(signature.signed_at) : '—'}</span>
                                        </div>
                                        <div className="mt-1 truncate font-mono text-[10px] text-om-faint">{signature.record_hash}</div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Section>
                </div>
            </div>
        </>
    );
}

function NoCoa({ batch, releaseGate, form, post }) {
    return (
        <>
            <Head title={`${batch.lot_number ?? `B${batch.batch_number}`} — COA 与放行`} />
            <div className="mx-auto max-w-[1180px] space-y-5">
                <div>
                    <Link href="/admin/coa" className="text-xs font-bold text-om-accent hover:underline">← 返回 COA 工作台</Link>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <h1 className="font-mono text-2xl font-bold text-om-ink">{batch.lot_number ?? `B${batch.batch_number}`}</h1>
                        <span className="rounded bg-om-chip px-2 py-0.5 text-xs font-bold text-om-muted">COA 未生成</span>
                    </div>
                    <p className="mt-1 text-sm text-om-muted">
                        {batch.product ?? '—'} · {batch.customer ?? '—'} · 尚未创建受控 COA 文档
                    </p>
                </div>

                {Object.keys(form.errors).length > 0 && (
                    <div className="rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-4 py-3 text-sm text-om-blocked">
                        {Object.values(form.errors).map((error) => <div key={error}>{error}</div>)}
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <GatePanel title="放行门" subtitle="批次能否放行到销售" gate={releaseGate} />
                    <div className="rounded-om-sm border border-om-line bg-om-card p-5 shadow-sm">
                        <h2 className="text-sm font-bold text-om-ink">COA 草稿</h2>
                        <p className="mt-1 text-xs leading-5 text-om-muted">
                            草稿会把客户/产品/配方/检验结果/包装检查冻结成快照；签发前仍可作废重签。
                        </p>
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() => post(`/admin/coa/${batch.id}/draft`)}
                            className="btn-touch btn-primary mt-4 text-sm disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {form.processing ? '处理中…' : '生成 COA 草稿'}
                        </button>
                    </div>
                </div>

                {!batch.released_at && (
                    <div className="rounded-om-sm border border-om-line bg-om-card px-5 py-4 shadow-sm">
                        <label className="text-sm font-bold text-om-ink" htmlFor="notes">放行备注</label>
                        <p className="mt-1 text-xs text-om-muted">可选。写入批次备注，不影响放行门判定。</p>
                        <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) => form.setData('notes', event.target.value)}
                                placeholder="例如：首件确认通过，留样已入库"
                                className="min-w-0 flex-1 rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/15"
                            />
                            <button
                                type="button"
                                disabled={!releaseGate.passed || form.processing}
                                onClick={() => post(`/admin/coa/${batch.id}/release`)}
                                className="btn-touch btn-primary text-sm disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {form.processing ? '处理中…' : '放行并签名'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function GatePanel({ title, subtitle, gate }) {
    const tone = gate.passed ? 'border-om-running/30' : 'border-om-line';
    return (
        <div className={`rounded-om-sm border bg-om-card p-5 shadow-sm ${tone}`}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 className="text-base font-bold text-om-ink">{title}</h2>
                    <p className="mt-0.5 text-xs text-om-muted">{subtitle}</p>
                </div>
                <div className={`font-mono text-lg font-bold ${gate.passed ? 'text-om-running' : 'text-om-blocked'}`}>
                    {gate.passed_count}/{gate.total}
                </div>
            </div>
            <div className="mt-4 h-2 overflow-hidden rounded-full bg-om-chip">
                <div
                    className={`h-full rounded-full ${gate.passed ? 'bg-om-running' : 'bg-om-blocked'}`}
                    style={{ width: `${Math.round((gate.passed_count / Math.max(gate.total, 1)) * 100)}%` }}
                />
            </div>
            <div className="mt-4 space-y-3">
                {gate.checks.map((check) => (
                    <div key={check.key} className="flex gap-3">
                        <span className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold ${check.passed ? 'bg-om-running-bg text-om-running' : 'bg-om-blocked-bg text-om-blocked'}`}>
                            {check.passed ? '✓' : '!'}
                        </span>
                        <div className="min-w-0">
                            <div className="text-sm font-medium text-om-ink">{check.label}</div>
                            <div className="mt-0.5 text-xs text-om-muted">{check.detail}</div>
                            <div className="mt-0.5 text-[11px] text-om-faint">依据：{check.evidence}</div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
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
