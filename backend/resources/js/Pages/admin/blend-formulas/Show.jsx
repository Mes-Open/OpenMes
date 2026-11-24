import { Head, Link, useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDate, formatDateTime, formatNumber } from '../../../lib/i18n';

const STATUS = {
    DRAFT: { label: '草稿', className: 'bg-om-chip text-om-muted' },
    SAMPLE: { label: '小样', className: 'bg-om-downtime-bg text-om-downtime' },
    LARGE_SAMPLE: { label: '大样', className: 'bg-om-downtime-bg text-om-downtime' },
    APPROVED: { label: '批准', className: 'bg-om-running-bg text-om-running' },
    FROZEN: { label: '冻结', className: 'border border-om-line bg-om-card text-om-ink' },
};

const RESULT = {
    PASS: { label: '合格', className: 'bg-om-running-bg text-om-running' },
    FAIL: { label: '超差', className: 'bg-om-blocked-bg text-om-blocked' },
    PENDING: { label: '待样', className: 'bg-om-chip text-om-faint' },
};

const num = (value, digits = 1) => value == null || value === ''
    ? '—'
    : formatNumber(value, { minimumFractionDigits: digits, maximumFractionDigits: digits });

const signed = (value) => value == null ? '—' : `${value > 0 ? '+' : ''}${num(value)}`;

const resultTone = (result) => result === 'PASS'
    ? 'text-om-running'
    : result === 'FAIL'
        ? 'text-om-blocked'
        : 'text-om-downtime';

export default function BlendFormulaShow() {
    const { formula, components = [] } = usePage().props;
    const [freezeOpen, setFreezeOpen] = useState(false);
    const freezeForm = useForm({ reason: '' });

    const submitFreeze = (event) => {
        event.preventDefault();
        freezeForm.post(`/admin/blend-formulas/${formula.id}/freeze`, {
            preserveScroll: true,
            onSuccess: () => {
                setFreezeOpen(false);
                freezeForm.reset();
            },
        });
    };

    const failedCount = components.filter((component) => component.out_of_tolerance).length;

    return (
        <>
            <Head title={`拼配版本 ${formula.version}`} />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-om-faint">
                            <Link href="/admin/blend-formulas" className="hover:text-om-accent">拼配版本</Link>
                            <span>/</span>
                            <span className="font-mono text-om-muted">{formula.code}</span>
                        </div>
                        <div className="mt-1.5 flex flex-wrap items-center gap-2.5">
                            <h1 className="font-mono text-3xl font-bold tracking-tight text-om-ink">{formula.version}</h1>
                            <StatusPill status={formula.status} />
                            {formula.is_frozen && <span className="text-[11px] font-bold text-om-muted">只读</span>}
                        </div>
                        <p className="mt-1 text-sm leading-6 text-om-muted">
                            <span className="font-semibold text-om-ink">{formula.product ?? '未指定产品'}</span>
                            {formula.product_code && <span className="ml-2 font-mono text-[11px] text-om-faint">{formula.product_code}</span>}
                            <span className="mx-2 text-om-faintest">·</span>
                            {formula.customer ?? '未指定客户'}
                            {formula.customer_code && <span className="ml-1 font-mono text-[10px] text-om-faint">{formula.customer_code}</span>}
                        </p>
                        <p className="mt-0.5 text-[11px] text-om-faint">
                            生效 {formula.effective_date ?? '未生效'}
                            <span className="mx-2 text-om-faintest">·</span>
                            批准 {formula.approved_by ?? '未批准'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/blend-formulas" className="btn-touch btn-secondary text-sm">全部版本</Link>
                        <Link
                            method="post"
                            as="button"
                            href={`/admin/blend-formulas/${formula.id}/duplicate`}
                            className="btn-touch btn-primary text-sm"
                            title="复制配方与标准样，开一个新草稿"
                        >
                            复制为新版本
                        </Link>
                        {formula.can_freeze && !formula.is_frozen && (
                            <button
                                type="button"
                                onClick={() => setFreezeOpen((open) => !open)}
                                className="btn-touch btn-secondary text-sm text-om-blocked"
                            >
                                {freezeOpen ? '收起冻结' : '冻结这一版'}
                            </button>
                        )}
                    </div>
                </div>

                {formula.is_frozen && (
                    <div className="rounded-om-sm border border-om-line bg-om-chip/40 px-4 py-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-xs font-bold text-om-ink">这一版已冻结，比例为只读；要改配方只能复制新版本。</p>
                            <p className="font-mono text-[10px] text-om-faint">
                                {formula.frozen_by ?? 'QA'} · {formula.frozen_at ? formatDateTime(formula.frozen_at) : '—'}
                            </p>
                        </div>
                        {formula.freeze_reason && <p className="mt-1.5 text-xs leading-5 text-om-muted">{formula.freeze_reason}</p>}
                    </div>
                )}

                {freezeOpen && (
                    <div className="rounded-om-sm border border-om-line bg-om-card p-4">
                        <form onSubmit={submitFreeze}>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-bold text-om-ink">冻结 {formula.code}</h2>
                                    <p className="mt-0.5 text-xs leading-5 text-om-muted">冻结后这一版的比例只读；后续修改从“复制为新版本”开始。</p>
                                </div>
                                <button type="button" onClick={() => setFreezeOpen(false)} className="text-xs text-om-faint hover:text-om-ink">取消</button>
                            </div>
                            <label className="mt-3 block text-xs font-bold text-om-ink" htmlFor="freeze-reason">冻结原因</label>
                            <textarea
                                id="freeze-reason"
                                value={freezeForm.data.reason}
                                onChange={(event) => freezeForm.setData('reason', event.target.value)}
                                rows={3}
                                className="mt-1.5 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent"
                                placeholder="例如：小样超差、标准样换版，旧版留档"
                            />
                            {freezeForm.errors.reason && <p className="mt-1 text-xs text-om-blocked">{freezeForm.errors.reason}</p>}
                            <div className="mt-3 flex items-center justify-end gap-2">
                                <button type="button" onClick={() => setFreezeOpen(false)} className="btn-touch btn-secondary text-xs">取消</button>
                                <button type="submit" disabled={freezeForm.processing} className="btn-touch btn-secondary text-xs text-om-blocked disabled:opacity-60">
                                    {freezeForm.processing ? '冻结中…' : '确认冻结'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                    <SummaryCell
                        label="标准样"
                        value={formula.standard_sample_code ?? '未指定'}
                        note={formula.has_standard_sample
                            ? (formula.standard_sample_approved_at ? `批准 ${formatDate(formula.standard_sample_approved_at)}` : '已批准')
                            : '批准前置未满足'}
                        tone={formula.has_standard_sample ? 'text-om-ink' : 'text-om-downtime'}
                        mono
                    />
                    <SummaryCell label="小样" value={(RESULT[formula.small_sample_result] ?? RESULT.PENDING).label} note={formula.small_sample_result === 'PASS' ? '评审已过' : '看配方表'} tone={resultTone(formula.small_sample_result)} />
                    <SummaryCell label="大样" value={formula.large_sample_result === 'PENDING' ? '待大样' : (RESULT[formula.large_sample_result] ?? RESULT.PENDING).label} note={formula.large_sample_result === 'PENDING' ? '未做放大' : '看配方表'} tone={resultTone(formula.large_sample_result)} />
                    <SummaryCell label="生效 / 批准" value={formula.effective_date ?? '未生效'} note={formula.approved_by ? `批准 ${formula.approved_by}` : '未批准'} mono />
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.85fr)]">
                    <Panel
                        title="配方表"
                        subtitle="标准比例按 100% 设计；允差与偏差都用百分点。只有超差行着色。"
                        action={(
                            <div className="text-right">
                                <div className={`font-mono text-sm font-bold ${formula.formula_complete ? 'text-om-ink' : 'text-om-blocked'}`}>
                                    {num(formula.formula_total)} / 100
                                </div>
                                <div className={`mt-0.5 text-[10px] ${failedCount > 0 ? 'text-om-blocked' : 'text-om-faint'}`}>
                                    {failedCount > 0 ? `${failedCount} 行超差` : formula.formula_complete ? '配方完整' : '合计未满 100%'}
                                </div>
                            </div>
                        )}
                    >
                        {components.length === 0 ? (
                            <p className="px-4 py-10 text-center text-sm text-om-faint">还没有配方组分。</p>
                        ) : (
                            <>
                                <div className="hidden overflow-hidden lg:block">
                                    <table className="w-full table-fixed text-xs">
                                        <thead>
                                            <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-[10px] font-bold tracking-wide text-om-muted">
                                                <th className="w-[30%] px-4 py-2.5">原料</th>
                                                <th className="w-[12%] px-2 py-2.5 text-right">标准</th>
                                                <th className="w-[10%] px-2 py-2.5 text-right">允差</th>
                                                <th className="w-[12%] px-2 py-2.5 text-right">小样</th>
                                                <th className="w-[12%] px-2 py-2.5 text-right">大样</th>
                                                <th className="w-[14%] px-2 py-2.5 text-right">偏差</th>
                                                <th className="w-[10%] px-2 py-2.5">判定</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {components.map((component) => <ComponentRow key={component.id} component={component} />)}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="divide-y divide-om-line2 lg:hidden">
                                    {components.map((component) => <ComponentCard key={component.id} component={component} />)}
                                </div>
                            </>
                        )}
                        <div className="border-t border-om-line2 px-4 py-2.5 text-[11px] text-om-faint">
                            偏差 = 实测 - 标准；小样和大样任一超出允差，判定为超差。大样没做时只显示“待大样”，不拿小样结果代替。
                        </div>
                    </Panel>

                    <div className="space-y-4">
                        <StandardSamplePanel formula={formula} />
                        <ApprovalPanel formula={formula} />
                    </div>
                </div>

                <Panel title="审批时间线" subtitle="谁提交、谁评、谁批准、何时冻结。">
                    <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                        {formula.timeline.map((step) => <TimelineStep key={step.key} step={step} />)}
                    </div>
                </Panel>

                {formula.notes && (
                    <div className="rounded-om-sm border border-om-line bg-om-card px-4 py-3">
                        <h2 className="text-xs font-bold text-om-ink">版本备注</h2>
                        <p className="mt-1 whitespace-pre-line text-sm leading-6 text-om-muted">{formula.notes}</p>
                    </div>
                )}

                <p className="text-[11px] leading-5 text-om-faint">
                    本阶段只开放版本对照、复制新版本和冻结；比例编辑、提交小样/大样与批准动作在下一阶段接入。
                </p>
            </div>
        </>
    );
}

function ComponentRow({ component }) {
    const smallOut = component.small_actual != null && Math.abs(component.small_deviation) > component.tolerance + 0.001;
    const largeOut = component.large_actual != null && Math.abs(component.large_deviation) > component.tolerance + 0.001;

    return (
        <tr className={`border-b border-om-line2 last:border-0 ${component.out_of_tolerance ? 'bg-om-blocked-bg/45' : ''}`}>
            <td className="px-4 py-3">
                <div className="truncate font-semibold text-om-ink" title={component.component_name}>{component.component_name}</div>
                <div className="mt-0.5 truncate text-[10px] text-om-faint">
                    {component.tea_base ? <><span className="font-mono">{component.tea_base.code}</span> · {component.tea_base.origin ?? '产地未登记'}</> : '未关联茶底档案'}
                </div>
            </td>
            <td className="px-2 py-3 text-right font-mono font-bold text-om-ink">{num(component.standard_ratio)}%</td>
            <td className="px-2 py-3 text-right font-mono text-om-muted">±{num(component.tolerance)}</td>
            <ActualCell value={component.small_actual} deviation={component.small_deviation} out={smallOut} pendingLabel="待样" />
            <ActualCell value={component.large_actual} deviation={component.large_deviation} out={largeOut} pendingLabel="待大样" />
            <td className="px-2 py-3 text-right font-mono text-[11px]">
                <div className={smallOut ? 'text-om-blocked' : 'text-om-muted'}>小 {signed(component.small_deviation)}</div>
                <div className={`mt-0.5 ${largeOut ? 'text-om-blocked' : 'text-om-muted'}`}>大 {signed(component.large_deviation)}</div>
            </td>
            <td className="px-2 py-3"><VerdictBadge verdict={component.verdict} /></td>
        </tr>
    );
}

function ActualCell({ value, deviation, out, pendingLabel }) {
    if (value == null) {
        return <td className="px-2 py-3 text-right text-[11px] text-om-faint">{pendingLabel}</td>;
    }

    return (
        <td className={`px-2 py-3 text-right font-mono ${out ? 'font-bold text-om-blocked' : 'text-om-ink'}`}>
            {num(value)}%
            <div className="mt-0.5 text-[10px] font-normal text-om-faint">{signed(deviation)}</div>
        </td>
    );
}

function ComponentCard({ component }) {
    const smallOut = component.small_actual != null && Math.abs(component.small_deviation) > component.tolerance + 0.001;
    const largeOut = component.large_actual != null && Math.abs(component.large_deviation) > component.tolerance + 0.001;

    return (
        <div className={`px-4 py-3.5 ${component.out_of_tolerance ? 'bg-om-blocked-bg/45' : ''}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="truncate font-semibold text-om-ink">{component.component_name}</div>
                    <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">
                        {component.tea_base?.code ?? '未关联茶底'} · 标准 {num(component.standard_ratio)}% ±{num(component.tolerance)}
                    </div>
                </div>
                <VerdictBadge verdict={component.verdict} />
            </div>
            <div className="mt-2.5 grid grid-cols-2 gap-2">
                <MiniValue label="小样" value={component.small_actual == null ? '待样' : `${num(component.small_actual)}%`} note={component.small_actual == null ? '' : `偏差 ${signed(component.small_deviation)}`} out={smallOut} />
                <MiniValue label="大样" value={component.large_actual == null ? '待大样' : `${num(component.large_actual)}%`} note={component.large_actual == null ? '未做放大' : `偏差 ${signed(component.large_deviation)}`} out={largeOut} />
            </div>
        </div>
    );
}

function StandardSamplePanel({ formula }) {
    return (
        <Panel title="标准样" subtitle="批准前必须有一支已批准的标准样。">
            {formula.has_standard_sample ? (
                <div className="p-4">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <div className="font-mono text-sm font-bold text-om-ink" title={formula.standard_sample_code}>{formula.standard_sample_code}</div>
                            <div className="mt-0.5 text-[11px] text-om-running">标准样已批准</div>
                        </div>
                        <span className="shrink-0 rounded bg-om-running-bg px-2 py-0.5 text-[10px] font-bold text-om-running">已指定</span>
                    </div>

                    <div className="mt-3 flex items-end justify-between gap-3 rounded-om-sm bg-om-chip/40 px-3 py-2.5">
                        <div>
                            <div className="text-[10px] font-bold tracking-wide text-om-faint">盲评结果</div>
                            <div className="mt-0.5 font-mono text-lg font-bold text-om-ink">
                                {formula.standard_sample_blind_result == null ? '—' : num(formula.standard_sample_blind_result)}
                                <span className="ml-0.5 text-[11px] font-normal text-om-faint">/ 100</span>
                            </div>
                        </div>
                        <div className="text-right text-[10px] text-om-faint">
                            <div>批准日期</div>
                            <div className="mt-0.5 font-mono text-om-muted">{formula.standard_sample_approved_at ? formatDate(formula.standard_sample_approved_at) : '—'}</div>
                        </div>
                    </div>

                    {formula.standard_sample_summary && (
                        <div className="mt-3">
                            <div className="text-[10px] font-bold tracking-wide text-om-faint">感官摘要</div>
                            <p className="mt-1 text-xs leading-5 text-om-muted">{formula.standard_sample_summary}</p>
                        </div>
                    )}
                </div>
            ) : (
                <div className="p-4">
                    <div className="rounded-om-sm border border-dashed border-om-line bg-om-bg px-4 py-6 text-center">
                        <p className="text-sm font-bold text-om-ink">还没有标准样</p>
                        <p className="mt-1 text-xs leading-5 text-om-muted">先上传或指定标准样；在它被批准前，这一版不能批准。</p>
                    </div>
                </div>
            )}
        </Panel>
    );
}

function ApprovalPanel({ formula }) {
    if (formula.is_frozen) {
        return (
            <Panel title="批准前置" subtitle="冻结后不再进入批准流程。">
                <div className="p-4">
                    <p className="rounded-om-sm bg-om-chip/40 px-3 py-2.5 text-xs leading-5 text-om-muted">这一版已经冻结，保留为历史版本；新版本从“复制为新版本”开始。</p>
                </div>
            </Panel>
        );
    }

    if (formula.status === 'APPROVED') {
        return (
            <Panel title="批准前置" subtitle="标准样、小样和大样均已满足。">
                <div className="p-4">
                    <div className="rounded-om-sm border border-om-line bg-om-running-bg/45 px-3 py-2.5">
                        <div className="text-[10px] font-bold tracking-wide text-om-running">已批准</div>
                        <p className="mt-1 text-xs leading-5 text-om-ink">
                            {formula.approved_by ? `${formula.approved_by} 已批准` : '已批准'}
                            {formula.effective_date ? `，生效 ${formula.effective_date}` : '，待生效'}。
                        </p>
                    </div>
                </div>
            </Panel>
        );
    }

    if (formula.approval_block_reason) {
        return (
            <Panel title="批准前置" subtitle="未满足的条件会一直显示。">
                <div className="p-4">
                    <div className="rounded-om-sm border border-om-line bg-om-downtime-bg/45 px-3 py-2.5">
                        <div className="text-[10px] font-bold tracking-wide text-om-downtime">未满足</div>
                        <p className="mt-1 text-xs leading-5 text-om-ink">{formula.approval_block_reason}</p>
                    </div>
                </div>
            </Panel>
        );
    }

    return (
        <Panel title="批准前置" subtitle="小样合格、标准样存在，才具备批准条件。">
            <div className="p-4">
                <div className="rounded-om-sm border border-om-line bg-om-running-bg/45 px-3 py-2.5">
                    <div className="text-[10px] font-bold tracking-wide text-om-running">已满足</div>
                    <p className="mt-1 text-xs leading-5 text-om-ink">小样合格，标准样已存在；本阶段只保留版本对照，批准动作在下一阶段接入。</p>
                </div>
            </div>
        </Panel>
    );
}

function TimelineStep({ step }) {
    return (
        <div className={`rounded-om-sm border px-3 py-2.5 ${step.done ? 'border-om-line bg-om-card' : 'border-om-line2 bg-om-chip/25'}`}>
            <div className="flex items-center gap-2">
                <span className={`h-2 w-2 rounded-full ${step.done ? 'bg-om-running' : 'bg-om-faintest'}`} />
                <span className={`text-xs font-bold ${step.done ? 'text-om-ink' : 'text-om-muted'}`}>{step.label}</span>
                {step.done && <span className="ml-auto text-[10px] font-bold text-om-running">完成</span>}
            </div>
            <div className="mt-2 text-[11px] text-om-muted">
                {step.actor ?? '待定'}
                <span className="mx-1 text-om-faintest">·</span>
                <span className="font-mono text-[10px] text-om-faint">{step.at ? formatDateTime(step.at) : '—'}</span>
            </div>
            <div className="mt-0.5 text-[10px] leading-4 text-om-faint">{step.note}</div>
        </div>
    );
}

function SummaryCell({ label, value, note, tone = 'text-om-ink', mono = false }) {
    return (
        <div className="rounded-om-sm border border-om-line bg-om-card px-3 py-2.5">
            <div className="text-[10px] font-bold tracking-wide text-om-faint">{label}</div>
            <div className={`mt-0.5 truncate text-sm font-bold ${tone} ${mono ? 'font-mono' : ''}`} title={value}>{value}</div>
            <div className="mt-0.5 truncate text-[10px] text-om-faint">{note}</div>
        </div>
    );
}

function Panel({ title, subtitle, action, children }) {
    return (
        <section className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
            <div className="flex flex-wrap items-start justify-between gap-2 border-b border-om-line2 px-4 py-3">
                <div className="min-w-0">
                    <h2 className="text-sm font-bold text-om-ink">{title}</h2>
                    {subtitle && <p className="mt-0.5 text-xs leading-5 text-om-muted">{subtitle}</p>}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

function StatusPill({ status }) {
    const tone = STATUS[status] ?? STATUS.DRAFT;
    return (
        <span className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-bold ${tone.className}`}>
            <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
            {tone.label}
        </span>
    );
}

function VerdictBadge({ verdict }) {
    const tone = RESULT[verdict] ?? RESULT.PENDING;
    return <span className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-bold ${tone.className}`}>{tone.label}</span>;
}

function MiniValue({ label, value, note, out = false }) {
    return (
        <div className="rounded-om-sm bg-om-bg px-2.5 py-2">
            <div className="text-[10px] font-bold text-om-faint">{label}</div>
            <div className={`mt-0.5 font-mono text-xs font-bold ${out ? 'text-om-blocked' : 'text-om-ink'}`}>{value}</div>
            {note && <div className={`mt-0.5 text-[10px] ${out ? 'text-om-blocked' : 'text-om-faint'}`}>{note}</div>}
        </div>
    );
}
