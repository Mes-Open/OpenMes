import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import { Icon } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';

const STATUS = {
    OK: { label: '在库', className: 'bg-om-running-bg text-om-running', icon: 'check' },
    HOLD: { label: '冻结批', className: 'bg-om-blocked-bg text-om-blocked', icon: 'shield-alert' },
    NOLOT: { label: '缺批次', className: 'bg-om-downtime-bg text-om-downtime', icon: 'triangle-alert' },
    ZERO: { label: '零库存', className: 'bg-om-chip text-om-muted', icon: 'circle-dashed' },
};

const LOT_STATUS = {
    released: 'bg-om-running-bg text-om-running',
    rejected: 'bg-om-blocked-bg text-om-blocked',
    expired: 'bg-om-blocked-bg text-om-blocked',
    quarantine: 'bg-om-downtime-bg text-om-downtime',
    received: 'bg-om-chip text-om-muted',
    consumed: 'bg-om-chip text-om-faint',
};

const TYPES = [
    { key: '', label: '全部' },
    { key: 'material', label: '原料' },
    { key: 'packaging', label: '包材' },
    { key: 'product', label: '成品' },
];

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const unit = (value) => ({ pcs: '件' }[value] ?? value);
const typeCount = (summary, key) => {
    if (key === '') return summary.total ?? 0;
    return summary[key] ?? 0;
};

export default function WarehouseStock() {
    const { rows = [], summary = {}, warehouses = [], filters = {} } = usePage().props;
    const [q, setQ] = useState(filters.q ?? '');
    const [detail, setDetail] = useState(null);

    const apply = (patch) => {
        const next = { ...filters, ...patch };
        const params = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined));
        router.get('/admin/warehouses/stock', params, { preserveState: true, replace: true });
    };

    const submitSearch = (event) => {
        event.preventDefault();
        apply({ q });
    };

    const clear = () => {
        setQ('');
        router.get('/admin/warehouses/stock');
    };

    const hasFilter = Boolean(filters.q || filters.warehouse || filters.type);

    const qtyEntries = Object.entries(summary.qty_by_uom ?? {}).sort((a, b) => Number(b[1]) - Number(a[1]));
    const mainQty = qtyEntries[0];
    const otherQty = qtyEntries.slice(1);

    return (
        <>
            <Head title="仓储 · 库存台账" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">库存台账</h1>
                        <p className="mt-1 max-w-[760px] text-sm leading-6 text-om-muted">
                            按仓库看实物库存、批次和冻结状态。数量来自库存余额，批次明细来自物料批；只读，不在这里调整库存。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            共 {summary.total ?? 0} 个库存项 · 原料 {summary.material ?? 0} · 包材 {summary.packaging ?? 0} · 成品 {summary.product ?? 0}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/warehouses" className="btn-touch btn-secondary text-sm">标签平衡</Link>
                        <Link href="/admin/coa" className="btn-touch btn-secondary text-sm">批次控制台</Link>
                    </div>
                </div>

                <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="启用仓库" value={summary.warehouses ?? 0} sub={warehouses.map((w) => w.name).join(' · ') || '没有启用仓库'} tone="text-om-ink" icon="warehouse" />
                    <Metric label="库存 SKU" value={summary.total ?? 0} sub={`原料 ${summary.material ?? 0} · 包材 ${summary.packaging ?? 0} · 成品 ${summary.product ?? 0}`} tone="text-om-ink" icon="boxes" />
                    <Metric
                        label="库存数量"
                        value={mainQty ? `${number(mainQty[1])} ${unit(mainQty[0])}` : '0'}
                        sub={otherQty.length ? otherQty.map(([uom, qty]) => `${number(qty)} ${unit(uom)}`).join(' · ') : '按单位分开统计'}
                        tone="text-om-ink"
                        icon="package"
                    />
                    <Metric label="批次缺口" value={summary.missing_lots ?? 0} sub={`有批次 ${summary.with_lots ?? 0} · 冻结批 ${summary.held_lots ?? 0}`} tone={(summary.missing_lots ?? 0) > 0 ? 'text-om-downtime' : 'text-om-running'} icon="layers" />
                </div>

                <div className="rounded-om-sm border border-om-line bg-om-chip/40 px-4 py-3 text-xs leading-5 text-om-muted">
                    批次跟踪物料没有物料批时标记“缺批次”；已收货、待检或拒收的批次会保留在库，但在批次表里单独标出，不混入“可放行”数量。
                </div>

                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-[11px] font-bold tracking-wide text-om-faint">仓库</span>
                        <FilterButton active={!filters.warehouse} onClick={() => apply({ warehouse: '' })}>
                            全部仓
                        </FilterButton>
                        {warehouses.map((warehouse) => (
                            <FilterButton key={warehouse.id} active={String(filters.warehouse ?? '') === String(warehouse.id)} onClick={() => apply({ warehouse: warehouse.id })}>
                                {warehouse.name}
                            </FilterButton>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-[11px] font-bold tracking-wide text-om-faint">类型</span>
                        {TYPES.map((type) => (
                            <FilterButton key={type.key || 'all'} active={(filters.type ?? '') === type.key} onClick={() => apply({ type: type.key })}>
                                {type.label} <span className={`ml-1 font-mono ${(filters.type ?? '') === type.key ? 'text-white/75' : 'text-om-faint'}`}>{typeCount(summary, type.key)}</span>
                            </FilterButton>
                        ))}
                        {hasFilter && (
                            <button type="button" onClick={clear} className="text-xs font-bold text-om-muted hover:text-om-ink">清除筛选</button>
                        )}
                    </div>
                </div>

                <form onSubmit={submitSearch} className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="搜物料名称、编码或仓库"
                        className="h-10 min-w-0 rounded-om-sm border border-om-line bg-om-card px-3 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-2 focus:ring-om-accent/20"
                    />
                    <button type="submit" className="btn-touch btn-secondary text-sm">搜索</button>
                </form>

                <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                        <h2 className="text-sm font-bold text-om-ink">库存明细</h2>
                        <span className="text-xs text-om-faint">冻结和缺批次优先，其次按仓库与名称</span>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-4 py-12 text-center">
                            <p className="text-sm font-bold text-om-ink">{hasFilter ? '没有符合筛选的库存项。' : '仓库里还没有库存余额。'}</p>
                            <p className="mx-auto mt-2 max-w-[540px] text-xs leading-5 text-om-muted">
                                {hasFilter ? '清除筛选后再看全部仓库。' : '收料、入库或生产入库过账后，库存余额会出现在这里。'}
                            </p>
                            {hasFilter && <button type="button" onClick={clear} className="btn-touch btn-secondary mt-4 text-xs">清除筛选</button>}
                        </div>
                    ) : (
                        <>
                            <div className="hidden lg:block">
                                <StockTable rows={rows} onOpen={setDetail} />
                            </div>
                            <div className="divide-y divide-om-line2 lg:hidden">
                                {rows.map((row) => <StockCard key={row.id} row={row} onOpen={setDetail} />)}
                            </div>
                            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-om-line2 bg-om-chip/40 px-4 py-3 text-[11px] text-om-muted">
                                <span>全仓单位合计</span>
                                <span className="font-mono">
                                    {qtyEntries.map(([uom, qty]) => `${number(qty)} ${unit(uom)}`).join(' · ') || '—'}
                                </span>
                            </div>
                        </>
                    )}
                </div>
            </div>

            {detail && <StockDrawer stock={detail} onClose={() => setDetail(null)} />}
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

function Metric({ label, value, sub, tone, icon }) {
    return (
        <div className="min-w-0 border-b border-om-line2 px-4 py-3 last:border-0 sm:border-b-0 sm:border-r sm:last:border-r-0">
            <div className="flex items-center justify-between gap-2">
                <span className="text-[11px] font-bold tracking-wide text-om-faint">{label}</span>
                <Icon name={icon} size={13} className={tone} />
            </div>
            <div className={`mt-1 font-mono text-xl font-bold ${tone}`}>{value}</div>
            <div className="mt-0.5 line-clamp-2 text-[11px] leading-4 text-om-muted">{sub}</div>
        </div>
    );
}

function StatusPill({ status, label }) {
    const meta = STATUS[status] ?? STATUS.OK;
    return (
        <span className={`inline-flex items-center gap-1 rounded px-2 py-1 text-[11px] font-bold ${meta.className}`}>
            <Icon name={meta.icon} size={12} />
            {label ?? meta.label}
        </span>
    );
}

function LotStatus({ lot }) {
    return (
        <span className={`inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold ${LOT_STATUS[lot.status] ?? LOT_STATUS.received}`}>
            {lot.status_label}
        </span>
    );
}

function StockTable({ rows, onOpen }) {
    return (
        <table className="w-full table-fixed text-sm">
            <thead>
                <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-[11px] font-bold tracking-wide text-om-muted">
                    <th className="w-[31%] px-4 py-2.5">物料 / 成品</th>
                    <th className="w-[19%] px-2 py-2.5">仓库</th>
                    <th className="w-[13%] px-2 py-2.5 text-right">数量</th>
                    <th className="w-[19%] px-2 py-2.5">批次</th>
                    <th className="w-[18%] px-4 py-2.5">状态</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id} className={`border-b border-om-line2 last:border-0 hover:bg-om-chip/20 ${row.status === 'HOLD' ? 'bg-om-blocked-bg/10' : ''}`}>
                        <td className="px-4 py-3">
                            <div className="truncate text-sm font-bold text-om-ink">{row.item_name}</div>
                            <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{row.item_code} · {row.item_type}</div>
                        </td>
                        <td className="px-2 py-3">
                            <div className="font-mono text-[11px] font-bold text-om-ink">{row.warehouse_code}</div>
                            <div className="mt-0.5 truncate text-[10px] text-om-muted">{row.warehouse_name}</div>
                        </td>
                        <td className="px-2 py-3 text-right">
                            <div className="font-mono text-base font-bold text-om-ink">{number(row.quantity)} <span className="text-[10px] font-medium text-om-muted">{unit(row.uom)}</span></div>
                        </td>
                        <td className="px-2 py-3">
                            <div className="text-[11px] font-bold text-om-ink">{row.lot_summary}</div>
                            <div className="mt-0.5 text-[10px] text-om-faint">
                                {row.lots_count > 0 ? `可用 ${number(row.lots_available)} ${unit(row.uom)}` : row.tracking_label}
                                {row.next_expiry ? ` · 至 ${row.next_expiry}` : ''}
                            </div>
                        </td>
                        <td className="px-4 py-3">
                            <StatusPill status={row.status} label={row.status_label} />
                            <button type="button" onClick={() => onOpen(row)} className="mt-1.5 block text-[11px] font-bold text-om-muted hover:text-om-ink">批次明细 →</button>
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function StockCard({ row, onOpen }) {
    return (
        <div className={`px-4 py-3 ${row.status === 'HOLD' ? 'bg-om-blocked-bg/10' : ''}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="truncate text-sm font-bold text-om-ink">{row.item_name}</div>
                    <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{row.item_code} · {row.item_type}</div>
                </div>
                <StatusPill status={row.status} label={row.status_label} />
            </div>
            <div className="mt-2 flex items-end justify-between gap-3">
                <div>
                    <div className="font-mono text-xl font-bold text-om-ink">{number(row.quantity)} <span className="text-xs font-medium text-om-muted">{unit(row.uom)}</span></div>
                    <div className="mt-0.5 text-[10px] text-om-muted">{row.warehouse_code} · {row.warehouse_name}</div>
                </div>
                <div className="text-right">
                    <div className="text-[11px] font-bold text-om-ink">{row.lot_summary}</div>
                    <div className="mt-0.5 text-[10px] text-om-faint">{row.lots_count > 0 ? `可用 ${number(row.lots_available)} ${unit(row.uom)}` : row.tracking_label}</div>
                </div>
            </div>
            <div className="mt-2 flex items-center justify-between gap-2">
                <span className="text-[10px] text-om-faint">{row.next_expiry ? `最近有效期 ${row.next_expiry}` : '没有登记有效期'}</span>
                <button type="button" onClick={() => onOpen(row)} className="text-[11px] font-bold text-om-accent hover:underline">批次明细 →</button>
            </div>
        </div>
    );
}

function StockDrawer({ stock, onClose }) {
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="stock-detail-title" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-[560px] overflow-y-auto rounded-t-om border border-om-line bg-om-card shadow-xl sm:rounded-om" onClick={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                    <div className="min-w-0">
                        <h2 id="stock-detail-title" className="truncate text-sm font-bold text-om-ink">{stock.item_name}</h2>
                        <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{stock.item_code} · {stock.item_type}</div>
                    </div>
                    <button type="button" onClick={onClose} className="btn-touch btn-secondary px-3 text-xs">关闭</button>
                </div>

                <div className="grid grid-cols-2 gap-3 px-4 py-4">
                    <div>
                        <div className="text-[10px] text-om-faint">库存数量</div>
                        <div className="mt-1 font-mono text-2xl font-bold text-om-ink">{number(stock.quantity)} <span className="text-xs text-om-muted">{unit(stock.uom)}</span></div>
                    </div>
                    <div className="text-right">
                        <div className="text-[10px] text-om-faint">状态</div>
                        <div className="mt-1"><StatusPill status={stock.status} label={stock.status_label} /></div>
                    </div>
                    <div>
                        <div className="text-[10px] text-om-faint">仓库</div>
                        <div className="mt-1 text-xs font-bold text-om-ink">{stock.warehouse_code} · {stock.warehouse_name}</div>
                    </div>
                    <div>
                        <div className="text-[10px] text-om-faint">批次规则</div>
                        <div className="mt-1 text-xs font-bold text-om-ink">{stock.tracking_label}</div>
                    </div>
                </div>

                <div className="border-t border-om-line2 px-4 py-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-xs font-bold text-om-ink">在库批次</h3>
                        <span className="text-[10px] text-om-faint">
                            {stock.lots_count > 0 ? `批次可用合计 ${number(stock.lots_available)} ${unit(stock.uom)}` : '没有批次记录'}
                        </span>
                    </div>

                    {stock.lots.length === 0 ? (
                        <div className="mt-3 rounded-om-sm border border-om-line bg-om-chip/30 px-3 py-4 text-xs leading-5 text-om-muted">
                            {stock.tracking === 'batch'
                                ? '该物料要求批次跟踪，但当前库存余额没有对应的物料批。先补收料/检验批次，再按批次发料。'
                                : '该物料不要求批次跟踪，当前只维护汇总数量。'}
                        </div>
                    ) : (
                        <div className="mt-2 divide-y divide-om-line2">
                            {stock.lots.map((lot) => (
                                <div key={lot.id} className="py-2.5">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <div className="truncate font-mono text-[11px] font-bold text-om-ink">{lot.lot_number}</div>
                                            <div className="mt-0.5 text-[10px] text-om-faint">收货 {lot.received_at ?? '—'}{lot.expiry ? ` · 有效期 ${lot.expiry}` : ''}</div>
                                        </div>
                                        <LotStatus lot={lot} />
                                    </div>
                                    <div className="mt-1 flex items-center justify-between gap-2">
                                        <span className="font-mono text-[11px] text-om-muted">可用 {number(lot.available)} / 收 {number(lot.received)} {unit(lot.uom)}</span>
                                        <Link href={lot.url} className="text-[11px] font-bold text-om-accent hover:underline">查看物料批 →</Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-between gap-2 border-t border-om-line2 bg-om-chip/30 px-4 py-3">
                    <span className="text-[10px] text-om-faint">库存数量不在本页调整。</span>
                    <div className="flex items-center gap-2">
                        {stock.lots_url && <Link href={stock.lots_url} className="text-[11px] font-bold text-om-muted hover:text-om-ink">物料批台账</Link>}
                        <button type="button" onClick={onClose} className="btn-touch btn-secondary text-xs">关闭</button>
                    </div>
                </div>
            </div>
        </div>
    );
}

WarehouseStock.layout = (page) => <AppLayout>{page}</AppLayout>;
