import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
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
    const { batch, document, versions = [], releaseGate, issueGate, separation, weighing, labTest, labTestRequired = false, releaseChecks = {}, batchSignatures = [], documentSignatures = [] } = usePage().props;
    const form = useForm({ notes: '', reason: '' });

    const post = (path) => form.post(path, { preserveScroll: true });

    if (!document) {
        return <NoCoa batch={batch} releaseGate={releaseGate} weighing={weighing} labTest={labTest} labTestRequired={labTestRequired} releaseChecks={releaseChecks} form={form} post={post} />;
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

                <SeparationPanel separation={separation} />

                <WeighingPanel batch={batch} weighing={weighing} />

                <LabTestPanel batch={batch} labTest={labTest} labTestRequired={labTestRequired} />

                <ReleaseCheckPanel batch={batch} releaseChecks={releaseChecks} />

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
                                            <span className="text-xs text-om-muted">
                                                {signature.user ?? '—'}
                                                {signature.roles?.length ? ` · ${signature.roles.join(' / ')}` : ''}
                                                {' · '}
                                                {signature.signed_at ? formatDateTime(signature.signed_at) : '—'}
                                            </span>
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

function NoCoa({ batch, releaseGate, weighing, labTest, labTestRequired = false, releaseChecks = {}, form, post }) {
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

                <WeighingPanel batch={batch} weighing={weighing} />

                <LabTestPanel batch={batch} labTest={labTest} labTestRequired={labTestRequired} />

                <ReleaseCheckPanel batch={batch} releaseChecks={releaseChecks} />

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
                    <table className="w-full min-w-[680px] text-xs">
                        <thead>
                            <tr className="border-b border-om-line2 text-left text-om-muted">
                                <th className="py-1 pr-2 font-semibold">原料</th>
                                <th className="py-1 pr-2 font-semibold">目标量</th>
                                <th className="py-1 pr-2 font-semibold">实际量</th>
                                <th className="py-1 pr-2 font-semibold">偏差</th>
                                <th className="py-1 pr-2 font-semibold">公差</th>
                                <th className="py-1 pr-2 font-semibold">秤 / 操作人</th>
                                <th className="py-1 font-semibold">判定</th>
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
                                            <td className="py-1 pr-2 text-om-muted">{record ? `${record.variance_qty > 0 ? '+' : ''}${formatNumber(record.variance_qty, 2)} / ${record.variance_pct > 0 ? '+' : ''}${formatNumber(record.variance_pct, 2)}%` : '—'}</td>
                                            <td className="py-1 pr-2 text-om-muted">±{formatNumber(record?.tolerance_pct ?? row.tolerance_pct, 2)}%</td>
                                            <td className="py-1 pr-2 text-om-muted">{record ? `${record.scale_code || '—'} · ${record.weighed_by || '—'}` : '—'}</td>
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
                            <table className="w-full min-w-[560px] text-xs">
                                <thead>
                                    <tr className="border-b border-om-line2 text-left text-om-muted">
                                        <th className="py-1 pr-2 font-semibold">类别</th>
                                        <th className="py-1 pr-2 font-semibold">项目</th>
                                        <th className="py-1 pr-2 font-semibold">实测</th>
                                        <th className="py-1 pr-2 font-semibold">限值</th>
                                        <th className="py-1 font-semibold">判定</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(labTest.items ?? []).map((item, index) => (
                                        <tr key={`${item.category}-${item.name}-${index}`} className="border-b border-om-line2/60">
                                            <td className="py-1 pr-2 text-om-muted">{item.category}</td>
                                            <td className="py-1 pr-2 font-semibold text-om-ink">{item.name}</td>
                                            <td className="py-1 pr-2 text-om-ink">{item.result}</td>
                                            <td className="py-1 pr-2 text-om-muted">{item.limit}</td>
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
