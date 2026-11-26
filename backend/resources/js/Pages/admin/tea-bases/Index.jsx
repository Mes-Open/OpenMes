import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime } from '../../../lib/i18n';

const TEA_BASE_STATUS = {
    PENDING: { label: '待检', className: 'bg-om-downtime-bg text-om-downtime' },
    AVAILABLE: { label: '可用', className: 'bg-om-running-bg text-om-running' },
    FROZEN: { label: '冻结', className: 'bg-om-blocked-bg text-om-blocked' },
    RETIRED: { label: '停用', className: 'bg-om-chip text-om-muted' },
};

const QUALITY_STATUS = {
    PASS: { label: '合格', className: 'text-om-running', dot: 'bg-om-running' },
    FAIL: { label: '不合格', className: 'text-om-blocked', dot: 'bg-om-blocked' },
    PENDING: { label: '待检', className: 'text-om-downtime', dot: 'bg-om-downtime' },
};

const STATUS_FILTERS = [
    { key: '', label: '全部' },
    { key: 'AVAILABLE', label: '可用' },
    { key: 'PENDING', label: '待检' },
    { key: 'FROZEN', label: '冻结' },
    { key: 'RETIRED', label: '停用' },
];

export default function TeaBaseIndex() {
    const { teaBases, filters = {}, origins = [], varieties = [], summary = {} } = usePage().props;
    const rows = teaBases?.data ?? [];
    const [q, setQ] = useState(filters.q ?? '');
    const hasFilter = Boolean(filters.q || filters.status || filters.origin || filters.variety);

    const apply = (patch) => {
        const next = { ...filters, ...patch };
        const params = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined));
        router.get('/admin/tea-bases', params, { preserveState: true, replace: true });
    };

    const submitSearch = (event) => {
        event.preventDefault();
        apply({ q });
    };

    return (
        <>
            <Head title="茶底档案" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">茶底档案</h1>
                        <p className="mt-1 max-w-[700px] text-sm leading-6 text-om-muted">
                            每份茶底一张身份证：产地、品种、等级、季节和含水率、农残、重金属、感官指纹。先看能不能用，再看有没有可用批次进拼配。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            {summary.total ?? rows.length} 份档案 · 可用 {summary.available ?? 0} · 待检 {summary.pending ?? 0} · 冻结 {summary.frozen ?? 0}
                        </p>
                    </div>
                    <Link href="/admin/material-lots" className="btn-touch btn-secondary text-sm">物料批</Link>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {STATUS_FILTERS.map((item) => {
                        const active = (filters.status ?? '') === item.key;
                        const count = item.key === '' ? summary.total : summary[item.key.toLowerCase()];
                        return (
                            <button
                                key={item.key || 'all'}
                                type="button"
                                onClick={() => apply({ status: item.key })}
                                className={`btn-touch text-xs ${active ? 'bg-om-accent text-white hover:bg-om-accent/90' : 'bg-om-chip text-om-muted hover:text-om-ink'}`}
                            >
                                {item.label} <span className={`ml-1 font-mono ${active ? 'text-white/80' : 'text-om-faint'}`}>{count ?? 0}</span>
                            </button>
                        );
                    })}
                    {hasFilter && (
                        <button type="button" onClick={() => { setQ(''); router.get('/admin/tea-bases'); }} className="text-xs font-bold text-om-muted hover:text-om-ink">
                            清除筛选
                        </button>
                    )}
                </div>

                <form onSubmit={submitSearch} className="grid gap-2 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,.8fr)_minmax(0,.8fr)_auto]">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="搜编码、名称、产地、品种"
                        className="h-10 min-w-0 rounded-om-sm border border-om-line bg-om-card px-3 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-2 focus:ring-om-accent/20"
                    />
                    <select
                        value={filters.origin ?? ''}
                        onChange={(event) => apply({ origin: event.target.value })}
                        className="h-10 min-w-0 rounded-om-sm border border-om-line bg-om-card px-2 text-sm text-om-ink outline-none focus:border-om-accent"
                    >
                        <option value="">全部产地</option>
                        {origins.map((origin) => <option key={origin} value={origin}>{origin}</option>)}
                    </select>
                    <select
                        value={filters.variety ?? ''}
                        onChange={(event) => apply({ variety: event.target.value })}
                        className="h-10 min-w-0 rounded-om-sm border border-om-line bg-om-card px-2 text-sm text-om-ink outline-none focus:border-om-accent"
                    >
                        <option value="">全部品种</option>
                        {varieties.map((variety) => <option key={variety} value={variety}>{variety}</option>)}
                    </select>
                    <button type="submit" className="btn-touch btn-secondary text-sm">搜索</button>
                </form>

                <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                        <h2 className="text-sm font-bold text-om-ink">茶底身份表</h2>
                        <span className="text-xs text-om-faint">可用优先，冻结与停用靠后</span>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-4 py-12 text-center">
                            <p className="text-sm text-om-faint">还没有茶底档案。</p>
                            <p className="mt-2 text-xs text-om-muted">先从来料检验放行的茶叶批生成，手工登记将在下一阶段开放。</p>
                            <Link href="/admin/inspection-plans" className="btn-touch btn-secondary mt-4 inline-flex text-xs">打开来料检验</Link>
                        </div>
                    ) : (
                        <>
                            <div className="hidden lg:block">
                                <DesktopTable rows={rows} />
                            </div>
                            <div className="divide-y divide-om-line2 lg:hidden">
                                {rows.map((row) => <MobileRow key={row.id} row={row} />)}
                            </div>
                        </>
                    )}

                    {teaBases?.links?.length > 3 && (
                        <div className="flex flex-wrap gap-1 border-t border-om-line2 px-4 py-3">
                            {teaBases.links.map((link, index) => (
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        className={`rounded px-3 py-1.5 text-xs font-medium ${link.active ? 'bg-om-accent text-white' : 'bg-om-chip text-om-muted hover:text-om-ink'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={index} className="rounded px-3 py-1.5 text-xs text-om-faint" dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function DesktopTable({ rows }) {
    return (
        <table className="w-full table-fixed text-sm">
            <thead>
                <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-[11px] font-bold tracking-wide text-om-muted">
                    <th className="w-[24%] px-4 py-2.5">茶底编码 / 产地</th>
                    <th className="w-[11%] px-2 py-2.5">等级 / 季节</th>
                    <th className="w-[9%] px-2 py-2.5">含水率</th>
                    <th className="w-[9%] px-2 py-2.5">农残</th>
                    <th className="w-[9%] px-2 py-2.5">重金属</th>
                    <th className="w-[9%] px-2 py-2.5">感官</th>
                    <th className="w-[10%] px-2 py-2.5">可用批次</th>
                    <th className="w-[11%] px-2 py-2.5">状态</th>
                    <th className="w-[4%] px-2 py-2.5" />
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id} className="border-b border-om-line2 last:border-0 hover:bg-om-chip/20">
                        <td className="px-4 py-3">
                            <Link href={`/admin/tea-bases/${row.id}`} className="font-mono text-sm font-bold text-om-ink hover:text-om-accent">{row.code}</Link>
                            <div className="mt-0.5 truncate text-xs text-om-ink">{row.name}</div>
                            <div className="mt-0.5 truncate text-[11px] text-om-muted">{row.origin ?? '—'} · {row.variety ?? '—'}</div>
                        </td>
                        <td className="px-2 py-3">
                            <div className="text-xs font-medium text-om-ink">{row.grade ?? '—'}</div>
                            <div className="mt-0.5 text-[11px] text-om-muted">{row.season ?? '—'}</div>
                        </td>
                        <td className="px-2 py-3">
                            <div className="font-mono text-xs font-bold text-om-ink">{row.moisture_percent != null ? `${row.moisture_percent}%` : '—'}</div>
                            <div className="mt-0.5 text-[10px] text-om-faint">{row.screen_size ?? '—'}</div>
                        </td>
                        <td className="px-2 py-3"><QualityDot status={row.pesticide_status} /></td>
                        <td className="px-2 py-3"><QualityDot status={row.heavy_metal_status} /></td>
                        <td className="px-2 py-3"><QualityDot status={row.sensory_status} /></td>
                        <td className="px-2 py-3">
                            <div className={`font-mono text-xs font-bold ${row.available_lot_count > 0 ? 'text-om-running' : 'text-om-faint'}`}>{row.available_lot_count} 批</div>
                            <div className="mt-0.5 text-[10px] text-om-faint">{row.supplier_name ?? '—'}</div>
                        </td>
                        <td className="px-2 py-3"><TeaBasePill status={row.status} /></td>
                        <td className="px-2 py-3 text-right">
                            <Link href={`/admin/tea-bases/${row.id}`} className="text-om-faint hover:text-om-accent" aria-label={`打开 ${row.code}`}>→</Link>
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function MobileRow({ row }) {
    return (
        <div className="px-4 py-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <Link href={`/admin/tea-bases/${row.id}`} className="font-mono text-sm font-bold text-om-ink hover:text-om-accent">{row.code}</Link>
                    <div className="mt-0.5 truncate text-sm font-semibold text-om-ink">{row.name}</div>
                </div>
                <TeaBasePill status={row.status} />
            </div>
            <div className="mt-1 truncate text-[11px] text-om-muted">
                {row.origin ?? '—'} · {row.variety ?? '—'} · {row.grade ?? '—'} · {row.season ?? '—'}
            </div>
            <div className="mt-2 grid grid-cols-3 gap-2">
                <MiniMetric label="含水率" value={row.moisture_percent != null ? `${row.moisture_percent}%` : '—'} note={row.screen_size ?? ''} />
                <MiniMetric label="农残"><QualityDot status={row.pesticide_status} /></MiniMetric>
                <MiniMetric label="重金属"><QualityDot status={row.heavy_metal_status} /></MiniMetric>
                <MiniMetric label="感官"><QualityDot status={row.sensory_status} /></MiniMetric>
                <MiniMetric label="可用批次" value={`${row.available_lot_count} 批`} tone={row.available_lot_count > 0 ? 'text-om-running' : 'text-om-faint'} />
                <MiniMetric label="最近检验" value={row.last_inspected_at ? formatDateTime(row.last_inspected_at) : '—'} compact />
            </div>
            <div className="mt-2 flex items-center justify-between gap-2">
                <span className="truncate text-[11px] text-om-faint">{row.supplier_name ?? '供应商未登记'}</span>
                <Link href={`/admin/tea-bases/${row.id}`} className="text-[11px] font-bold text-om-accent hover:underline">打开 →</Link>
            </div>
        </div>
    );
}

function MiniMetric({ label, value, note, tone = 'text-om-ink', compact = false, children }) {
    return (
        <div className="min-w-0 rounded border border-om-line2 bg-om-bg px-2 py-1.5">
            <div className="text-[10px] font-bold text-om-faint">{label}</div>
            {children ? (
                <div className="mt-0.5">{children}</div>
            ) : value !== undefined ? (
                <div className={`mt-0.5 truncate font-mono font-bold ${compact ? 'text-[10px]' : 'text-xs'} ${tone}`}>{value}</div>
            ) : null}
            {note && <div className="mt-0.5 text-[10px] text-om-muted">{note}</div>}
        </div>
    );
}

function QualityDot({ status }) {
    const item = QUALITY_STATUS[status] ?? QUALITY_STATUS.PENDING;
    return (
        <span className={`inline-flex items-center gap-1 text-[11px] font-bold ${item.className}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${item.dot}`} />
            {item.label}
        </span>
    );
}

function TeaBasePill({ status }) {
    const item = TEA_BASE_STATUS[status] ?? TEA_BASE_STATUS.PENDING;
    return <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-bold ${item.className}`}>{item.label}</span>;
}

TeaBaseIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
