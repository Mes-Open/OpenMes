import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import { Icon } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';

const TYPE = {
    material_lot: { label: '物料批', className: 'bg-om-accent/10 text-om-accent', short: '批' },
    material_stock: { label: '物料库存', className: 'bg-om-chip text-om-muted', short: '料' },
    product_stock: { label: '成品库存', className: 'bg-om-chip text-om-muted', short: '成' },
};

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));

const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('zh-CN', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);
};

const formatLongDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);
};

const cleanParams = (filters, patch = {}) => {
    const next = { ...filters, ...patch };
    return Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined));
};

export default function LocationMoves() {
    const { moves = {}, summary = {}, warehouses = [], filters = {} } = usePage().props;
    const [q, setQ] = useState(filters.q ?? '');
    const rows = moves.data ?? [];
    const hasFilter = Boolean(filters.warehouse || filters.type || filters.q);

    const apply = (patch) => {
        const params = cleanParams(filters, patch);
        router.get('/admin/warehouses/locations/moves', params, { preserveState: true, replace: true });
    };

    const clear = () => {
        setQ('');
        router.get('/admin/warehouses/locations/moves');
    };

    const submitSearch = (event) => {
        event.preventDefault();
        apply({ q });
    };

    const exportHref = `/admin/warehouses/locations/moves/export${Object.keys(cleanParams(filters)).length ? `?${new URLSearchParams(cleanParams(filters)).toString()}` : ''}`;

    return (
        <>
            <Head title="仓储 · 移库记录" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">移库记录</h1>
                        <p className="mt-1 max-w-[760px] text-sm leading-6 text-om-muted">
                            这里记录物料批、物料库存和成品库存的整项库位调整：只改物理位置，不拆数量、不改库存余额。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            按筛选结果共 {summary.total ?? 0} 条 · 物料批 {summary.material_lot ?? 0} · 物料库存 {summary.material_stock ?? 0} · 成品 {summary.product_stock ?? 0}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/warehouses/locations" className="btn-touch btn-secondary text-sm">库位地图</Link>
                        <a href={exportHref} className="btn-touch btn-secondary text-sm">导出 CSV</a>
                    </div>
                </div>

                <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="记录总数" value={summary.total ?? 0} sub="当前筛选范围" icon="history" tone="text-om-ink" />
                    <Metric label="物料移库" value={(summary.material_lot ?? 0) + (summary.material_stock ?? 0)} sub={`物料批 ${summary.material_lot ?? 0} · 库存行 ${summary.material_stock ?? 0}`} icon="package" tone="text-om-accent" />
                    <Metric label="成品库存移库" value={summary.product_stock ?? 0} sub="按成品汇总行移动" icon="boxes" tone="text-om-ink" />
                    <Metric label="操作人" value={summary.operators ?? 0} sub={`最近 ${formatLongDate(summary.last_moved_at)}`} icon="user-round" tone="text-om-ink" />
                </div>

                <div className="rounded-om-sm border border-om-line bg-om-chip/40 px-4 py-3 text-xs leading-5 text-om-muted">
                    库位记录与库存台账分开：这里能追溯“谁把什么从哪儿移到哪儿”，库存数量仍以仓库余额为准。
                </div>

                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-[11px] font-bold tracking-wide text-om-faint">仓库</span>
                        <FilterButton active={!filters.warehouse} onClick={() => apply({ warehouse: '' })}>全部仓</FilterButton>
                        {warehouses.map((warehouse) => (
                            <FilterButton key={warehouse.id} active={String(filters.warehouse ?? '') === String(warehouse.id)} onClick={() => apply({ warehouse: warehouse.id })}>
                                {warehouse.name}
                            </FilterButton>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-[11px] font-bold tracking-wide text-om-faint">类型</span>
                        <FilterButton active={!filters.type} onClick={() => apply({ type: '' })}>全部</FilterButton>
                        <FilterButton active={filters.type === 'material_lot'} onClick={() => apply({ type: 'material_lot' })}>物料批</FilterButton>
                        <FilterButton active={filters.type === 'material_stock'} onClick={() => apply({ type: 'material_stock' })}>物料库存</FilterButton>
                        <FilterButton active={filters.type === 'product_stock'} onClick={() => apply({ type: 'product_stock' })}>成品库存</FilterButton>
                        {hasFilter && <button type="button" onClick={clear} className="text-xs font-bold text-om-muted hover:text-om-ink">清除筛选</button>}
                    </div>
                </div>

                <form onSubmit={submitSearch} className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="搜物料/成品、库位编码、备注或操作人"
                        className="h-10 min-w-0 rounded-om-sm border border-om-line bg-om-card px-3 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-2 focus:ring-om-accent/20"
                    />
                    <button type="submit" className="btn-touch btn-secondary text-sm">搜索</button>
                </form>

                <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                        <div>
                            <h2 className="text-sm font-bold text-om-ink">调整明细</h2>
                            <p className="mt-0.5 text-[10px] text-om-faint">按移库时间倒序；同一对象可以有多次往返。</p>
                        </div>
                        <span className="text-[10px] text-om-muted">{moves.from ?? 0}–{moves.to ?? 0} / {moves.total ?? 0} 条</span>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-4 py-12 text-center">
                            <p className="text-sm font-bold text-om-ink">{hasFilter ? '没有符合筛选的移库记录。' : '还没有移库记录。'}</p>
                            <p className="mx-auto mt-2 max-w-[540px] text-xs leading-5 text-om-muted">
                                {hasFilter ? '清除筛选后再看全部记录。' : '从库位地图调整一个物料批或成品库存的库位后，这里会留下记录。'}
                            </p>
                            {hasFilter && <button type="button" onClick={clear} className="btn-touch btn-secondary mt-4 text-xs">清除筛选</button>}
                        </div>
                    ) : (
                        <>
                            <div className="hidden lg:block">
                                <MoveTable rows={rows} />
                            </div>
                            <div className="divide-y divide-om-line2 lg:hidden">
                                {rows.map((move) => <MoveCard key={move.id} move={move} />)}
                            </div>
                            {moves.last_page > 1 && <Pagination links={moves.links ?? []} />}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

function FilterButton({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`btn-touch text-xs ${active ? 'bg-om-accent text-white hover:bg-om-accent/90' : 'hover:text-om-ink'}`}
            style={active ? undefined : { background: 'var(--om-chip)', color: 'var(--om-muted)' }}
        >
            {children}
        </button>
    );
}

function Metric({ label, value, sub, icon, tone }) {
    return (
        <div className="min-w-0 border-b border-om-line2 px-4 py-3 last:border-0 sm:border-b-0 sm:border-r sm:last:border-r-0">
            <div className="flex items-center justify-between gap-2">
                <span className="text-[11px] font-bold text-om-muted">{label}</span>
                <span className="text-om-faint"><Icon name={icon} size={13} /></span>
            </div>
            <div className={`mt-1 font-mono text-xl font-bold leading-none tracking-tight ${tone}`}>{value}</div>
            <div className="mt-1 truncate text-[10px] text-om-faint">{sub}</div>
        </div>
    );
}

function TypeBadge({ type }) {
    const meta = TYPE[type] ?? TYPE.material_lot;
    return <span className={`inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold ${meta.className}`}>{meta.label}</span>;
}

function LocationPill({ location }) {
    return (
        <span className="inline-flex max-w-full items-center rounded border border-om-line bg-om-chip px-1.5 py-0.5 font-mono text-[10px] font-bold text-om-ink" title={location?.name ?? ''}>
            {location?.code ?? '—'}
        </span>
    );
}

function MoveCard({ move }) {
    const meta = TYPE[move.item_type] ?? TYPE.material_lot;
    return (
        <div className="px-4 py-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                    <TypeBadge type={move.item_type} />
                    <span className="truncate text-sm font-bold text-om-ink">{move.item_name}</span>
                    <span className="font-mono text-[10px] text-om-faint">{move.item_code}</span>
                </div>
                <div className="shrink-0 text-right">
                    <div className="font-mono text-sm font-bold text-om-ink">{number(move.quantity)} {move.unit_of_measure}</div>
                    <div className="mt-0.5 text-[10px] text-om-faint">{formatDate(move.moved_at)}</div>
                </div>
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-om-muted">
                <LocationPill location={move.from_location} />
                <span className="text-om-accent">→</span>
                <LocationPill location={move.to_location} />
                <span className="text-om-faint">·</span>
                <span>{move.warehouse?.name ?? '—'} · {move.moved_by?.name ?? '系统'}</span>
            </div>
            {move.note && <p className="mt-1.5 line-clamp-2 text-[11px] leading-4 text-om-muted">{move.note}</p>}
        </div>
    );
}

function MoveTable({ rows }) {
    return (
        <table className="w-full table-fixed text-left text-xs">
            <thead className="border-b border-om-line2 bg-om-chip/40 text-[10px] font-bold text-om-faint">
                <tr>
                    <th className="w-[88px] px-4 py-2">时间</th>
                    <th className="w-[84px] px-2 py-2">类型</th>
                    <th className="px-2 py-2">对象</th>
                    <th className="w-[96px] px-2 py-2 text-right">数量</th>
                    <th className="w-[126px] px-2 py-2">库位</th>
                    <th className="w-[88px] px-2 py-2">操作人</th>
                    <th className="px-4 py-2">备注</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-om-line2">
                {rows.map((move) => (
                    <tr key={move.id} className="align-top">
                        <td className="px-4 py-3 font-mono text-[10px] text-om-muted">{formatDate(move.moved_at)}</td>
                        <td className="px-2 py-3"><TypeBadge type={move.item_type} /></td>
                        <td className="min-w-0 px-2 py-3">
                            <div className="truncate font-bold text-om-ink">{move.item_name}</div>
                            <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{move.item_code}</div>
                        </td>
                        <td className="px-2 py-3 text-right font-mono text-om-ink">{number(move.quantity)} {move.unit_of_measure}</td>
                        <td className="px-2 py-3">
                            <div className="flex flex-wrap items-center gap-1">
                                <LocationPill location={move.from_location} />
                                <span className="text-om-accent">→</span>
                                <LocationPill location={move.to_location} />
                            </div>
                        </td>
                        <td className="truncate px-2 py-3 text-om-muted">{move.moved_by?.name ?? '系统'}</td>
                        <td className="px-4 py-3 text-[10px] leading-4 text-om-muted">{move.note ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function Pagination({ links }) {
    return (
        <div className="flex flex-wrap items-center justify-end gap-1 border-t border-om-line2 px-4 py-3">
            {links.filter((link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;').map((link, index) => (
                link.url ? (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        preserveScroll
                        className={`btn-touch px-2 text-[11px] ${link.active ? 'bg-om-accent text-white' : 'bg-om-chip text-om-muted'}`}
                    >
                        {link.label}
                    </Link>
                ) : (
                    <span key={`${link.label}-${index}`} className="px-2 text-[11px] text-om-faint">{link.label}</span>
                )
            ))}
        </div>
    );
}

LocationMoves.layout = (page) => <AppLayout>{page}</AppLayout>;
