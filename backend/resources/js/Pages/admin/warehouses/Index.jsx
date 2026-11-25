import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import { Icon } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime } from '../../../lib/i18n';

const GATE = {
    PASS: { label: 'G09 通过', className: 'bg-om-running-bg text-om-running', icon: 'check' },
    BLOCK: { label: 'G09 阻断', className: 'bg-om-blocked-bg text-om-blocked', icon: 'shield-alert' },
    MISSING: { label: '待登记', className: 'bg-om-downtime-bg text-om-downtime', icon: 'clock' },
};

const FILTERS = [
    { key: '', label: '全部' },
    { key: 'variance', label: '差异未平' },
    { key: 'balanced', label: '已平' },
    { key: 'missing', label: '待登记' },
];

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const INPUT = 'h-9 w-full min-w-0 rounded-om-sm border border-om-line bg-om-card px-2.5 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-2 focus:ring-om-accent/20';
const signed = (value) => {
    const n = Number(value || 0);
    return n === 0 ? '0' : (n > 0 ? `+${number(n)}` : `−${number(Math.abs(n))}`);
};

export default function WarehouseLabelBalance() {
    const { rows = [], summary = {}, filters = {} } = usePage().props;
    const [q, setQ] = useState(filters.q ?? '');
    const [editing, setEditing] = useState(null);
    const form = useForm({ issued: '', consumed: '', scrapped: '', returned: '', notes: '' });

    const apply = (patch) => {
        const next = { ...filters, ...patch };
        const params = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined));
        router.get('/admin/warehouses', params, { preserveState: true, replace: true });
    };

    const submitSearch = (event) => {
        event.preventDefault();
        apply({ q });
    };

    const countFor = (key) => {
        if (key === '') return summary.total ?? 0;
        if (key === 'variance') return summary.variance ?? 0;
        if (key === 'balanced') return summary.balanced ?? 0;
        return summary.missing ?? 0;
    };

    const openRecord = (row) => {
        const suggested = Number(row.suggested_issued || 0);
        setEditing(row);
        form.clearErrors();
        form.setData({
            // A missing record gets only a suggested issued quantity. The other
            // three counts stay blank so the operator must enter the actual
            // label count; a prefilled balance would falsely pass G09.
            issued: row.has_counts ? String(row.issued) : String(suggested),
            consumed: row.has_counts ? String(row.consumed) : '',
            scrapped: row.has_counts ? String(row.scrapped) : '',
            returned: row.has_counts ? String(row.returned) : '',
            notes: row.notes ?? '',
        });
    };

    const close = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submit = (event) => {
        event.preventDefault();
        if (!editing) return;
        form.post(`/admin/warehouses/label-balance/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    };

    const hasFilter = Boolean(filters.q || filters.status);

    return (
        <>
            <Head title="仓储 · 标签平衡" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">标签平衡</h1>
                        <p className="mt-1 max-w-[760px] text-sm leading-6 text-om-muted">
                            每个批次只回答一件事：领用的标签，是否等于消耗、报废和退回。差异不为 0 就记 FAIL，批次控制台的 G09 标签平衡会直接阻断放行。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            共 {summary.total ?? 0} 批 · 已平 {summary.balanced ?? 0} · 差异未平 {summary.variance ?? 0} · 待登记 {summary.missing ?? 0}
                        </p>
                    </div>
                    <Link href="/admin/coa" className="btn-touch btn-secondary text-sm">批次控制台</Link>
                </div>

                <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="已平批次" value={summary.balanced ?? 0} sub="G09 可放行" tone="text-om-running" icon="check" />
                    <Metric label="差异未平" value={summary.variance ?? 0} sub="G09 阻断放行" tone={(summary.variance ?? 0) > 0 ? 'text-om-blocked' : 'text-om-ink'} icon="shield-alert" />
                    <Metric label="待登记" value={summary.missing ?? 0} sub="没有标签平衡记录" tone={(summary.missing ?? 0) > 0 ? 'text-om-downtime' : 'text-om-ink'} icon="clock" />
                    <Metric label="差异合计" value={signed(summary.variance_total ?? 0)} sub="领用 − 消耗 − 报废 − 退回" tone={Number(summary.variance_total || 0) !== 0 ? 'text-om-blocked' : 'text-om-running'} icon="scale" />
                </div>

                <div className="rounded-om-sm border border-om-line bg-om-chip/40 px-4 py-3 text-xs leading-5 text-om-muted">
                    登记会追加一条新的标签平衡记录，G09 取最新一条。已放行批次只读；差异未查清时，不能再用旧记录覆盖成通过。
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {FILTERS.map((item) => {
                        const active = (filters.status ?? '') === item.key;
                        return (
                            <button
                                key={item.key || 'all'}
                                type="button"
                                onClick={() => apply({ status: item.key })}
                                className={`btn-touch text-xs ${active ? 'bg-om-accent text-white hover:bg-om-accent/90' : 'hover:text-om-ink'}`}
                                style={active ? undefined : { background: 'var(--om-chip)', color: 'var(--om-muted)' }}
                            >
                                {item.label} <span className={`ml-1 font-mono ${active ? 'text-white/80' : 'text-om-faint'}`}>{countFor(item.key)}</span>
                            </button>
                        );
                    })}
                    {hasFilter && (
                        <button type="button" onClick={() => { setQ(''); router.get('/admin/warehouses'); }} className="text-xs font-bold text-om-muted hover:text-om-ink">
                            清除筛选
                        </button>
                    )}
                </div>

                <form onSubmit={submitSearch} className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="搜批次号、工单号、产品"
                        className="h-10 min-w-0 rounded-om-sm border border-om-line bg-om-card px-3 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-2 focus:ring-om-accent/20"
                    />
                    <button type="submit" className="btn-touch btn-secondary text-sm">搜索</button>
                </form>

                <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                        <h2 className="text-sm font-bold text-om-ink">标签领用台账</h2>
                        <span className="text-xs text-om-faint">差异优先，其次待登记</span>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-4 py-12 text-center">
                            <p className="text-sm font-bold text-om-ink">{hasFilter ? '没有符合筛选的批次。' : '还没有可登记标签平衡的批次。'}</p>
                            <p className="mx-auto mt-2 max-w-[520px] text-xs leading-5 text-om-muted">
                                {hasFilter ? '清除筛选后再看全部批次。' : '批次建立后，在这里登记领用、消耗、报废和退回；没有记录不会自动获得“已平”结论。'}
                            </p>
                            {hasFilter && (
                                <button type="button" onClick={() => { setQ(''); router.get('/admin/warehouses'); }} className="btn-touch btn-secondary mt-4 text-xs">
                                    清除筛选
                                </button>
                            )}
                        </div>
                    ) : (
                        <>
                            <div className="hidden lg:block">
                                <DesktopTable rows={rows} onRecord={openRecord} />
                            </div>
                            <div className="divide-y divide-om-line2 lg:hidden">
                                {rows.map((row) => <MobileRow key={row.id} row={row} onRecord={openRecord} />)}
                            </div>
                            <div className="grid grid-cols-2 gap-3 border-t border-om-line2 bg-om-chip/40 px-4 py-3 sm:grid-cols-4">
                                <Total label="领用合计" value={number(summary.issued)} />
                                <Total label="消耗合计" value={number(summary.consumed)} />
                                <Total label="报废合计" value={number(summary.scrapped)} />
                                <Total label="差异合计" value={signed(summary.variance_total)} tone={Number(summary.variance_total || 0) !== 0 ? 'text-om-blocked' : 'text-om-running'} />
                            </div>
                        </>
                    )}
                </div>
            </div>

            {editing && <RecordModal row={editing} form={form} onSubmit={submit} onClose={close} />}
        </>
    );
}

function Metric({ label, value, sub, tone, icon }) {
    return (
        <div className="min-w-0 border-b border-om-line2 px-4 py-3 last:border-0 sm:border-b-0 sm:border-r sm:last:border-r-0">
            <div className="flex items-center justify-between gap-2">
                <span className="text-[11px] font-bold tracking-wide text-om-faint">{label}</span>
                <Icon name={icon} size={13} className={tone} />
            </div>
            <div className={`mt-1 font-mono text-xl font-bold ${tone}`}>{value}</div>
            <div className="mt-0.5 text-[11px] text-om-muted">{sub}</div>
        </div>
    );
}

function GatePill({ gate }) {
    const meta = GATE[gate] ?? GATE.MISSING;
    return (
        <span className={`inline-flex items-center gap-1 rounded px-2 py-1 text-[11px] font-bold ${meta.className}`}>
            <Icon name={meta.icon} size={12} />
            {meta.label}
        </span>
    );
}

function DesktopTable({ rows, onRecord }) {
    return (
        <table className="w-full table-fixed text-sm">
            <thead>
                <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-[11px] font-bold tracking-wide text-om-muted">
                    <th className="w-[22%] px-4 py-2.5">批次 / 产品</th>
                    <th className="w-[13%] px-2 py-2.5 text-right">领用</th>
                    <th className="w-[13%] px-2 py-2.5 text-right">消耗</th>
                    <th className="w-[11%] px-2 py-2.5 text-right">报废</th>
                    <th className="w-[12%] px-2 py-2.5 text-right">差异</th>
                    <th className="w-[23%] px-4 py-2.5">G09 / 最近登记</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id} className={`border-b border-om-line2 last:border-0 hover:bg-om-chip/20 ${row.gate === 'BLOCK' ? 'bg-om-blocked-bg/10' : ''}`}>
                        <td className="px-4 py-3">
                            <Link href={row.coa_url} className="font-mono text-sm font-bold text-om-ink hover:text-om-accent">{row.batch_number}</Link>
                            <div className="mt-0.5 truncate text-xs text-om-muted">{row.product}</div>
                            <div className="mt-0.5 truncate text-[10px] text-om-faint">{row.customer} · {row.order_no}</div>
                        </td>
                        <NumCell value={row.issued} has={row.has_counts} />
                        <NumCell value={row.consumed} has={row.has_counts} />
                        <NumCell value={row.scrapped} has={row.has_counts} />
                        <td className="px-2 py-3 text-right">
                            {row.has_counts ? (
                                <span className={`font-mono text-sm font-bold ${row.variance === 0 ? 'text-om-running' : 'text-om-blocked'}`}>
                                    {signed(row.variance)}
                                </span>
                            ) : <span className="text-xs text-om-faint">—</span>}
                            <div className="mt-0.5 text-[10px] text-om-faint">退回 {row.has_counts ? number(row.returned) : '—'}</div>
                        </td>
                        <td className="px-4 py-3">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <GatePill gate={row.gate} />
                                {row.released && <span className="text-[10px] font-bold text-om-faint">已放行</span>}
                            </div>
                            <div className="mt-1 text-[10px] leading-4 text-om-muted">
                                {row.checked_at ? `${formatDateTime(row.checked_at)} · ${row.checked_by ?? '未记录'}` : '尚无标签平衡记录'}
                            </div>
                            <div className="mt-1.5 flex items-center gap-2">
                                <Link href={row.coa_url} className="text-[11px] font-bold text-om-accent hover:underline">查看批次</Link>
                                <Link href={row.slip_url} className="text-[11px] font-bold text-om-muted hover:text-om-ink">打印领用单</Link>
                                {!row.released && (
                                    <button type="button" onClick={() => onRecord(row)} className="text-[11px] font-bold text-om-muted hover:text-om-ink">
                                        登记
                                    </button>
                                )}
                            </div>
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function NumCell({ value, has }) {
    return (
        <td className="px-2 py-3 text-right">
            <span className={`font-mono text-sm ${has ? 'text-om-ink' : 'text-om-faint'}`}>{has ? number(value) : '—'}</span>
        </td>
    );
}

function MobileRow({ row, onRecord }) {
    return (
        <div className={`px-4 py-3 ${row.gate === 'BLOCK' ? 'bg-om-blocked-bg/10' : ''}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <Link href={row.coa_url} className="font-mono text-sm font-bold text-om-ink hover:text-om-accent">{row.batch_number}</Link>
                    <div className="mt-0.5 truncate text-xs text-om-muted">{row.product} · {row.customer}</div>
                </div>
                <GatePill gate={row.gate} />
            </div>
            <div className="mt-2 grid grid-cols-3 gap-2">
                <MiniMetric label="领用" value={row.has_counts ? number(row.issued) : '—'} />
                <MiniMetric label="消耗" value={row.has_counts ? number(row.consumed) : '—'} />
                <MiniMetric label="报废" value={row.has_counts ? number(row.scrapped) : '—'} />
            </div>
            <div className="mt-2 flex items-center justify-between gap-2 rounded bg-om-chip/50 px-2.5 py-2">
                <span className="text-[11px] font-bold text-om-muted">差异（领用 − 消耗 − 报废 − 退回）</span>
                <span className={`font-mono text-sm font-bold ${row.has_counts && row.variance !== 0 ? 'text-om-blocked' : 'text-om-running'}`}>
                    {row.has_counts ? signed(row.variance) : '—'}
                </span>
            </div>
            <div className="mt-1 text-[10px] leading-4 text-om-faint">
                {row.checked_at ? `${formatDateTime(row.checked_at)} · ${row.checked_by ?? '未记录'}` : '尚无标签平衡记录'}
            </div>
            <div className="mt-2 flex items-center gap-3">
                <Link href={row.coa_url} className="text-[11px] font-bold text-om-accent hover:underline">查看批次</Link>
                <Link href={row.slip_url} className="text-[11px] font-bold text-om-muted hover:text-om-ink">打印领用单</Link>
                {!row.released && <button type="button" onClick={() => onRecord(row)} className="text-[11px] font-bold text-om-muted hover:text-om-ink">登记标签平衡</button>}
                {row.released && <span className="text-[10px] font-bold text-om-faint">已放行，只读</span>}
            </div>
        </div>
    );
}

function MiniMetric({ label, value }) {
    return (
        <div className="rounded border border-om-line2 px-2.5 py-2">
            <div className="text-[10px] text-om-faint">{label}</div>
            <div className="mt-0.5 font-mono text-sm font-bold text-om-ink">{value}</div>
        </div>
    );
}

function Total({ label, value, tone = 'text-om-ink' }) {
    return (
        <div>
            <div className="text-[10px] font-bold tracking-wide text-om-faint">{label}</div>
            <div className={`mt-0.5 font-mono text-sm font-bold ${tone}`}>{value}</div>
        </div>
    );
}

function RecordModal({ row, form, onSubmit, onClose }) {
    const data = form.data;
    const variance = Number(data.issued || 0) - Number(data.consumed || 0) - Number(data.scrapped || 0) - Number(data.returned || 0);
    const balanced = Math.abs(variance) < 0.0001;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="label-balance-title">
            <form onSubmit={onSubmit} className="w-full max-w-[520px] overflow-hidden rounded-om border border-om-line bg-om-card shadow-xl">
                <div className="flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                    <div>
                        <h2 id="label-balance-title" className="text-sm font-bold text-om-ink">登记标签平衡 · {row.batch_number}</h2>
                        <p className="mt-0.5 text-[11px] text-om-muted">{row.product} · 差异不为 0 会直接写 FAIL 并阻断 G09</p>
                    </div>
                    <button type="button" onClick={onClose} className="btn-touch btn-secondary px-3 text-xs">关闭</button>
                </div>

                <div className="grid grid-cols-2 gap-3 px-4 py-4">
                    <Field label="领用" error={form.errors.issued}>
                        <input type="number" min="0" step="1" required value={data.issued} onChange={(e) => form.setData('issued', e.target.value)} className={INPUT} />
                    </Field>
                    <Field label="消耗" error={form.errors.consumed}>
                        <input type="number" min="0" step="1" required value={data.consumed} onChange={(e) => form.setData('consumed', e.target.value)} className={INPUT} />
                    </Field>
                    <Field label="报废" error={form.errors.scrapped}>
                        <input type="number" min="0" step="1" required value={data.scrapped} onChange={(e) => form.setData('scrapped', e.target.value)} className={INPUT} />
                    </Field>
                    <Field label="退回" error={form.errors.returned}>
                        <input type="number" min="0" step="1" required value={data.returned} onChange={(e) => form.setData('returned', e.target.value)} className={INPUT} />
                    </Field>
                </div>

                <div className={`mx-4 mb-4 rounded-om-sm border px-3 py-2.5 ${balanced ? 'border-om-running/30 bg-om-running-bg text-om-running' : 'border-om-blocked/30 bg-om-blocked-bg text-om-blocked'}`}>
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-xs font-bold">差异 = 领用 − 消耗 − 报废 − 退回</span>
                        <span className="font-mono text-sm font-bold">{signed(variance)}</span>
                    </div>
                    <div className="mt-1 text-[10px] leading-4 opacity-90">
                        {balanced ? '已平，提交后 G09 标签平衡记为 PASS。' : '未平，提交后 G09 标签平衡记为 FAIL，批次放行会被阻断。'}
                    </div>
                </div>

                <div className="px-4 pb-4">
                    <Field label="备注（可选）" error={form.errors.notes}>
                        <textarea rows={2} value={data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="例如：3 张标签破损，已隔离待复核" className={`${INPUT} h-auto min-h-[68px] resize-none py-2`} />
                    </Field>
                </div>

                <div className="flex items-center justify-between gap-2 border-t border-om-line2 bg-om-chip/30 px-4 py-3">
                    <span className="text-[10px] text-om-faint">登记后追加一条记录，不覆盖历史。</span>
                    <div className="flex gap-2">
                        <button type="button" onClick={onClose} className="btn-touch btn-secondary text-xs">取消</button>
                        <button type="submit" disabled={form.processing} className="btn-touch btn-accent text-xs">
                            {form.processing ? '登记中…' : '登记标签平衡'}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block min-w-0">
            <span className="mb-1 block text-[11px] font-bold text-om-muted">{label}</span>
            {children}
            {error && <span className="mt-1 block text-[10px] text-om-blocked">{error}</span>}
        </label>
    );
}

WarehouseLabelBalance.layout = (page) => <AppLayout>{page}</AppLayout>;
