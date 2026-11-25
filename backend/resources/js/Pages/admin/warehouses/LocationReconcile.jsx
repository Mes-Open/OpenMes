import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import { Icon } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';

const STOCK_STATUS = {
    OK: { label: '已对平', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' },
    UNLOCATED: { label: '未指定库位', className: 'bg-om-accent/10 text-om-accent' },
    PARTIAL: { label: '部分定位', className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
    OVER: { label: '超出账面', className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
};

const KIND = {
    material: { label: '物料批', short: '批', className: 'bg-om-accent/10 text-om-accent' },
    product: { label: '成品', short: '成', className: 'bg-om-chip text-om-muted' },
    stock: { label: '物料', short: '料', className: 'bg-om-chip text-om-muted' },
};

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const unitsText = (units) => Object.entries(units ?? {}).map(([unit, value]) => `${number(value)} ${unit}`).join(' · ');

export default function LocationReconcile() {
    const { warehouses = [], summary = {}, filters = {} } = usePage().props;
    const [view, setView] = useState('location');
    const [onlyGap, setOnlyGap] = useState(false);
    const [assign, setAssign] = useState(null);
    const [assignTarget, setAssignTarget] = useState('');
    const [assignNote, setAssignNote] = useState('');
    const [assignErrors, setAssignErrors] = useState({});
    const activeWarehouse = filters.warehouse ? String(filters.warehouse) : '';

    const applyWarehouse = (id) => {
        if (id) {
            router.get('/admin/warehouses/locations/reconcile', { warehouse: id }, { preserveState: true, replace: true });
        } else {
            router.get('/admin/warehouses/locations/reconcile', {}, { preserveState: true, replace: true });
        }
    };

    const gapCount = (summary.unlocated_lines ?? 0) + (summary.variance_lines ?? 0);

    const openAssign = (item, warehouse) => {
        setAssign({ item, warehouse });
        setAssignTarget('');
        setAssignNote('');
        setAssignErrors({});
    };

    const closeAssign = () => {
        setAssign(null);
        setAssignTarget('');
        setAssignNote('');
        setAssignErrors({});
    };

    const submitAssign = (event) => {
        event.preventDefault();
        if (!assign || !assignTarget) return;

        router.post('/admin/warehouses/locations/move', {
            item_type: 'material_stock',
            item_id: assign.item.id,
            to_location_id: Number(assignTarget),
            note: assignNote,
        }, {
            preserveScroll: true,
            onSuccess: () => closeAssign(),
            onError: (errors) => setAssignErrors(errors ?? {}),
        });
    };

    return (
        <>
            <Head title="仓储 · 库位对账" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">库位对账</h1>
                        <p className="mt-1 max-w-[760px] text-sm leading-6 text-om-muted">
                            用账面库存行对照库位占用，看清哪些库存已经落到库位，哪些还留在仓库账面没有位置。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            {warehouses.length} 个仓库 · {summary.located_items ?? 0} 项库位占用 · 核对库位，不改库存数量
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/warehouses/locations" className="btn-touch btn-secondary text-sm">库位地图</Link>
                        <Link href="/admin/warehouses/stock" className="btn-touch btn-secondary text-sm">库存台账</Link>
                    </div>
                </div>

                <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="库存行已对平" value={`${summary.located_lines ?? 0} / ${summary.stock_lines ?? 0}`} sub="库位合计与账面一致" icon="circle-check" tone="text-om-ink" />
                    <Metric label="未指定库位" value={summary.unlocated_lines ?? 0} sub="账面有量，未挂库位" icon="circle-help" tone={summary.unlocated_lines ? 'text-om-accent' : 'text-om-ink'} />
                    <Metric label="数量差异" value={summary.variance_lines ?? 0} sub="部分定位或超出账面" icon="scale" tone={summary.variance_lines ? 'text-om-accent' : 'text-om-ink'} />
                    <Metric label="冻结库位" value={summary.frozen_bins ?? 0} sub="有拒收或待检批次" icon="triangle-alert" tone={summary.frozen_bins ? 'text-om-accent' : 'text-om-ink'} />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[11px] font-bold tracking-wide text-om-faint">仓库</span>
                    <FilterButton active={!activeWarehouse} onClick={() => applyWarehouse('')}>全部仓</FilterButton>
                    {warehouses.map((warehouse) => (
                        <FilterButton key={warehouse.id} active={activeWarehouse === String(warehouse.id)} onClick={() => applyWarehouse(warehouse.id)}>
                            {warehouse.name}
                        </FilterButton>
                    ))}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-1 rounded-full border border-om-line bg-om-card p-1">
                        <ViewButton active={view === 'location'} onClick={() => setView('location')}>按库位</ViewButton>
                        <ViewButton active={view === 'stock'} onClick={() => setView('stock')}>按库存行</ViewButton>
                    </div>
                    {view === 'stock' && (
                        <button
                            type="button"
                            onClick={() => setOnlyGap((value) => !value)}
                            className={`btn-touch text-xs ${onlyGap ? 'bg-om-accent text-white' : 'bg-om-chip text-om-muted'}`}
                        >
                            {onlyGap ? '显示全部库存行' : `只看未对平（${gapCount}）`}
                        </button>
                    )}
                </div>

                {view === 'location' ? (
                    <LocationView warehouses={warehouses} onAssign={openAssign} />
                ) : (
                    <StockView warehouses={warehouses} onlyGap={onlyGap} onAssign={openAssign} />
                )}

                <div className="rounded-om-sm border border-om-line bg-om-chip/40 px-4 py-3 text-xs leading-5 text-om-muted">
                    对账口径：物料批按可用量计入库位，拒收批可用量为 0，但仍在库位上可见；成品库存按成品仓汇总行计入库位。未指定库位可从本页指定；这里不拆数量、不改库存余额。
                </div>
            </div>

            {assign && (
                <AssignDialog
                    assign={assign}
                    target={assignTarget}
                    setTarget={setAssignTarget}
                    note={assignNote}
                    setNote={setAssignNote}
                    errors={assignErrors}
                    onClose={closeAssign}
                    onSubmit={submitAssign}
                />
            )}
        </>
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

function ViewButton({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`btn-touch px-3 text-xs font-bold ${active ? 'bg-om-accent text-white' : 'text-om-muted hover:text-om-ink'}`}
        >
            {children}
        </button>
    );
}

function LocationView({ warehouses, onAssign }) {
    return (
        <div className="space-y-6">
            {warehouses.map((warehouse) => (
                <section key={warehouse.id} className="space-y-3">
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 className="text-base font-bold text-om-ink">{warehouse.name}</h2>
                            <p className="mt-0.5 text-[10px] text-om-faint">{warehouse.code} · {warehouse.locations.length} 个库位 · {warehouse.located_count} / {warehouse.stock_count} 行已对平</p>
                        </div>
                        {warehouse.unlocated_count > 0 && <span className="rounded-full bg-om-accent/10 px-2 py-1 text-[10px] font-bold text-om-accent">{warehouse.unlocated_count} 行未指定库位</span>}
                    </div>

                    <div className="grid gap-3 xl:grid-cols-2">
                        {warehouse.locations.map((location) => <LocationCard key={location.id} location={location} />)}
                        {warehouse.unassigned.length > 0 && <UnassignedCard warehouse={warehouse} onAssign={onAssign} />}
                    </div>
                </section>
            ))}
        </div>
    );
}

function LocationCard({ location }) {
    const held = location.held_count > 0;
    return (
        <div className={`overflow-hidden rounded-om-sm border bg-om-card ${held ? 'border-om-accent/30' : 'border-om-line'}`}>
            <div className="flex flex-wrap items-start justify-between gap-2 border-b border-om-line2 px-4 py-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-sm font-bold text-om-ink">{location.code}</span>
                        <span className="truncate text-xs text-om-muted">{location.name}</span>
                        {held && <span className="rounded bg-om-accent/10 px-1.5 py-0.5 text-[10px] font-bold text-om-accent">冻结 {location.held_count}</span>}
                    </div>
                    <p className="mt-1 text-[10px] text-om-faint">{location.zone_name} · {location.item_count} 项</p>
                </div>
                <div className="shrink-0 text-right">
                    <div className="font-mono text-xs font-bold text-om-ink">{unitsText(location.units) || '0'}</div>
                    <div className="mt-0.5 text-[10px] text-om-faint">{location.item_count ? '库位合计' : '空库位'}</div>
                </div>
            </div>

            {location.items.length === 0 ? (
                <div className="px-4 py-5 text-xs text-om-faint">空库位，暂无占用。</div>
            ) : (
                <div className="divide-y divide-om-line2">
                    {location.items.map((item) => (
                        <div key={item.key} className="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div className="flex min-w-0 items-start gap-2">
                                <KindBadge kind={item.kind} />
                                <div className="min-w-0">
                                    <div className="truncate text-xs font-bold text-om-ink">{item.name}</div>
                                    <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{item.lot_number || item.code}</div>
                                </div>
                            </div>
                            <div className="shrink-0 text-right">
                                <div className="font-mono text-xs font-bold text-om-ink">{number(item.quantity)} <span className="font-sans text-[10px] text-om-muted">{item.uom}</span></div>
                                <div className={`mt-0.5 text-[10px] ${item.is_held ? 'font-bold text-om-accent' : 'text-om-faint'}`}>{item.status_label}</div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function UnassignedCard({ warehouse, onAssign }) {
    return (
        <div className="overflow-hidden rounded-om-sm border border-dashed border-om-accent/40 bg-om-card">
            <div className="border-b border-om-line2 px-4 py-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 className="text-sm font-bold text-om-ink">未指定库位</h3>
                        <p className="mt-0.5 text-[10px] text-om-faint">账面有量，但没有挂到任何库位</p>
                    </div>
                    <span className="rounded bg-om-accent/10 px-2 py-1 text-[10px] font-bold text-om-accent">{warehouse.unassigned.length} 行</span>
                </div>
            </div>
            <div className="divide-y divide-om-line2">
                {warehouse.unassigned.map((line) => (
                    <div key={line.id} className="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div className="min-w-0">
                            <div className="truncate text-xs font-bold text-om-ink">{line.name}</div>
                            <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{line.code} · 账面 {number(line.balance)} {line.uom}</div>
                        </div>
                        <div className="shrink-0 text-right">
                            <div className="font-mono text-xs font-bold text-om-accent">{number(line.unlocated)} {line.uom}</div>
                            {line.lot_count === 0
                                ? <button type="button" onClick={() => onAssign(line, warehouse)} className="mt-1 text-[10px] font-bold text-om-accent hover:underline">指定库位</button>
                                : <div className="mt-1 text-[10px] text-om-faint">按批次调整</div>}
                        </div>
                    </div>
                ))}
            </div>
            <div className="border-t border-om-line2 px-4 py-2 text-[10px] text-om-faint">
                <Link href="/admin/warehouses/stock" className="font-bold text-om-muted hover:text-om-ink">去库存台账查看这批库存 →</Link>
            </div>
        </div>
    );
}

function StockView({ warehouses, onlyGap, onAssign }) {
    const visible = (warehouse) => onlyGap
        ? warehouse.stock_lines.filter((line) => line.status !== 'OK')
        : warehouse.stock_lines;

    return (
        <div className="space-y-5">
            {warehouses.map((warehouse) => {
                const lines = visible(warehouse);
                return (
                    <section key={warehouse.id} className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                            <div>
                                <h2 className="text-sm font-bold text-om-ink">{warehouse.name}</h2>
                                <p className="mt-0.5 text-[10px] text-om-faint">{warehouse.code} · {warehouse.stock_count} 行账面库存</p>
                            </div>
                            <div className="flex gap-1 text-[10px]">
                                <span className="rounded bg-om-chip px-1.5 py-0.5 font-bold text-om-muted">对平 {warehouse.located_count}</span>
                                {warehouse.unlocated_count > 0 && <span className="rounded bg-om-accent/10 px-1.5 py-0.5 font-bold text-om-accent">未定位 {warehouse.unlocated_count}</span>}
                                {warehouse.variance_count > 0 && <span className="rounded bg-amber-100 px-1.5 py-0.5 font-bold text-amber-800">差异 {warehouse.variance_count}</span>}
                            </div>
                        </div>

                        {lines.length === 0 ? (
                            <div className="px-4 py-8 text-center text-xs text-om-muted">这个仓库没有需要显示的未对平库存行。</div>
                        ) : (
                            <>
                                <div className="hidden lg:block">
                                    <StockTable lines={lines} />
                                </div>
                                <div className="divide-y divide-om-line2 lg:hidden">
                                    {lines.map((line) => <StockCard key={line.id} line={line} warehouse={warehouse} onAssign={onAssign} />)}
                                </div>
                            </>
                        )}
                    </section>
                );
            })}
        </div>
    );
}

function StockCard({ line, warehouse, onAssign }) {
    return (
        <div className="px-4 py-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <KindBadge kind={line.kind === 'material' ? 'stock' : 'product'} />
                    <div className="min-w-0">
                        <div className="truncate text-sm font-bold text-om-ink">{line.name}</div>
                        <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{line.code}</div>
                    </div>
                </div>
                <StatusBadge status={line.status} />
            </div>
            <div className="mt-3 grid grid-cols-3 gap-2 rounded-om-sm bg-om-chip/50 px-3 py-2">
                <Figure label="账面" value={`${number(line.balance)} ${line.uom}`} />
                <Figure label="库位合计" value={`${number(line.located)} ${line.uom}`} />
                <Figure label="差异" value={line.located <= 0 ? '未定位' : `${line.variance > 0 ? '+' : ''}${number(line.variance)}`} accent={line.variance !== 0} />
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-om-faint">
                <span>库位</span>
                {line.location_codes.length > 0 ? line.location_codes.map((code) => (
                    <span key={code} className="rounded border border-om-line bg-om-chip px-1.5 py-0.5 font-mono font-bold text-om-ink">{code}</span>
                )) : <span className="italic">未指定</span>}
                {line.lot_count > 1 && <span>· {line.lot_count} 个批次</span>}
                {line.held_lot_count > 0 && <span className="font-bold text-om-accent">· 冻结 {line.held_lot_count} 批</span>}
                {line.kind === 'material' && line.status === 'UNLOCATED' && (
                    line.lot_count === 0
                        ? <button type="button" onClick={() => onAssign(line, warehouse)} className="ml-auto text-[10px] font-bold text-om-accent hover:underline">指定库位</button>
                        : <span className="ml-auto text-[10px] text-om-faint">按批次调整</span>
                )}
            </div>
        </div>
    );
}

function StockTable({ lines }) {
    return (
        <table className="w-full table-fixed text-left text-xs">
            <thead className="border-b border-om-line2 bg-om-chip/40 text-[10px] font-bold text-om-faint">
                <tr>
                    <th className="w-[72px] px-4 py-2">类型</th>
                    <th className="px-2 py-2">库存项</th>
                    <th className="w-[92px] px-2 py-2 text-right">账面</th>
                    <th className="w-[92px] px-2 py-2 text-right">库位合计</th>
                    <th className="w-[68px] px-2 py-2 text-right">差异</th>
                    <th className="w-[112px] px-2 py-2">库位</th>
                    <th className="w-[94px] px-4 py-2">状态</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-om-line2">
                {lines.map((line) => (
                    <tr key={line.id}>
                        <td className="px-4 py-2.5"><KindBadge kind={line.kind === 'material' ? 'stock' : 'product'} /></td>
                        <td className="min-w-0 px-2 py-2.5">
                            <div className="truncate font-bold text-om-ink">{line.name}</div>
                            <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{line.code}{line.lot_count > 1 ? ` · ${line.lot_count} 批` : ''}</div>
                        </td>
                        <td className="px-2 py-2.5 text-right font-mono text-om-ink">{number(line.balance)} <span className="font-sans text-[10px] text-om-muted">{line.uom}</span></td>
                        <td className="px-2 py-2.5 text-right font-mono text-om-ink">{number(line.located)} <span className="font-sans text-[10px] text-om-muted">{line.uom}</span></td>
                        <td className={`px-2 py-2.5 text-right font-mono font-bold ${line.variance !== 0 ? 'text-om-accent' : 'text-om-muted'}`}>{line.located <= 0 ? '未定位' : `${line.variance > 0 ? '+' : ''}${number(line.variance)}`}</td>
                        <td className="px-2 py-2.5">
                            <div className="flex flex-wrap gap-1">
                                {line.location_codes.length > 0 ? line.location_codes.map((code) => (
                                    <span key={code} className="rounded border border-om-line bg-om-chip px-1 py-0.5 font-mono text-[10px] font-bold text-om-ink">{code}</span>
                                )) : <span className="text-[10px] italic text-om-faint">未指定</span>}
                            </div>
                        </td>
                        <td className="px-4 py-2.5"><StatusBadge status={line.status} /></td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function KindBadge({ kind }) {
    const meta = KIND[kind] ?? KIND.stock;
    return <span className={`inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold ${meta.className}`}>{meta.short}</span>;
}

function StatusBadge({ status }) {
    const meta = STOCK_STATUS[status] ?? STOCK_STATUS.OK;
    return <span className={`inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold ${meta.className}`}>{meta.label}</span>;
}

function Figure({ label, value, accent }) {
    return (
        <div className="min-w-0">
            <div className="text-[9px] font-bold text-om-faint">{label}</div>
            <div className={`mt-0.5 truncate font-mono text-[11px] font-bold ${accent ? 'text-om-accent' : 'text-om-ink'}`}>{value}</div>
        </div>
    );
}

function AssignDialog({ assign, target, setTarget, note, setNote, errors, onClose, onSubmit }) {
    const { item, warehouse } = assign;
    const targets = warehouse.locations ?? [];
    const error = errors.to_location_id || errors.item_id || errors.note;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="assign-location-title" onClick={onClose}>
            <form onSubmit={onSubmit} className="max-h-[92vh] w-full max-w-[540px] overflow-y-auto rounded-t-om border border-om-line bg-om-card shadow-xl sm:rounded-om" onClick={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                    <div className="min-w-0">
                        <h2 id="assign-location-title" className="text-sm font-bold text-om-ink">指定库位 · {item.name}</h2>
                        <p className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{item.code} · {warehouse.name}</p>
                    </div>
                    <button type="button" onClick={onClose} className="btn-touch btn-secondary px-3 text-xs">关闭</button>
                </div>

                <div className="grid grid-cols-2 gap-3 px-4 py-4">
                    <div>
                        <div className="text-[10px] text-om-faint">当前库位</div>
                        <div className="mt-1 font-semibold text-sm text-om-accent">未指定</div>
                    </div>
                    <div className="text-right">
                        <div className="text-[10px] text-om-faint">账面数量</div>
                        <div className="mt-1 font-mono text-lg font-bold text-om-ink">{number(item.unlocated)} <span className="text-xs font-medium text-om-muted">{item.uom}</span></div>
                    </div>
                    <div className="col-span-2">
                        <label className="block">
                            <span className="mb-1 block text-[11px] font-bold text-om-muted">目标库位</span>
                            <select
                                value={target}
                                onChange={(event) => setTarget(event.target.value)}
                                className="h-10 w-full rounded-om-sm border border-om-line bg-om-card px-3 text-sm text-om-ink outline-none focus:border-om-accent"
                                required
                            >
                                <option value="">选择同仓库位</option>
                                {targets.map((location) => (
                                    <option key={location.id} value={location.id}>
                                        {location.code} · {location.name}{location.item_count > 0 ? ` · 已有 ${location.item_count} 项` : ' · 空'}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {!targets.length && <div className="mt-1 text-[10px] text-om-accent">这个仓库还没有启用库位，请先在库位页新增。</div>}
                    </div>
                    <div className="col-span-2">
                        <label className="block">
                            <span className="mb-1 block text-[11px] font-bold text-om-muted">备注（可选）</span>
                            <textarea
                                rows={2}
                                value={note}
                                onChange={(event) => setNote(event.target.value)}
                                placeholder="例如：糖浆整桶上架到辅料位"
                                className="h-auto min-h-[68px] w-full resize-none rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent"
                                maxLength={160}
                            />
                        </label>
                    </div>
                </div>

                {error && <div className="mx-4 mb-4 rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-3 py-2 text-[11px] text-om-blocked">{error}</div>}

                <div className="mx-4 mb-4 rounded-om-sm border border-om-line bg-om-chip/30 px-3 py-2 text-[11px] leading-5 text-om-muted">
                    只给这一行账面库存指定库位，不拆数量、不改库存余额。已有批次的物料请回库位地图按批次调整。
                </div>

                <div className="flex items-center justify-between gap-2 border-t border-om-line2 px-4 pt-3 pb-4">
                    <span className="text-[10px] text-om-faint">会追加一条库位记录。</span>
                    <div className="flex gap-2">
                        <button type="button" onClick={onClose} className="btn-touch btn-secondary text-xs">取消</button>
                        <button type="submit" disabled={!target} className="btn-touch btn-accent text-xs disabled:cursor-not-allowed disabled:opacity-50">确认指定</button>
                    </div>
                </div>
            </form>
        </div>
    );
}

LocationReconcile.layout = (page) => <AppLayout>{page}</AppLayout>;
