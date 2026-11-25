import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import { Icon } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';

const BIN_STATUS = {
    OCCUPIED: { label: '有料', className: 'bg-om-running-bg text-om-running', icon: 'check' },
    HOLD: { label: '冻结 / 待处理', className: 'bg-om-blocked-bg text-om-blocked', icon: 'shield-alert' },
    EMPTY: { label: '空库位', className: 'bg-om-chip text-om-muted', icon: 'circle-dashed' },
};

const ITEM_STATUS = {
    released: 'bg-om-running-bg text-om-running',
    rejected: 'bg-om-blocked-bg text-om-blocked',
    expired: 'bg-om-blocked-bg text-om-blocked',
    quarantine: 'bg-om-downtime-bg text-om-downtime',
    received: 'bg-om-chip text-om-muted',
    consumed: 'bg-om-chip text-om-faint',
    product: 'bg-om-chip text-om-muted',
};

const INPUT = 'h-9 w-full min-w-0 rounded-om-sm border border-om-line bg-om-card px-2.5 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-2 focus:ring-om-accent/20';

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const unit = (value) => ({ pcs: '件' }[value] ?? value);
const qtyText = (qtyByUom) => {
    const entries = Object.entries(qtyByUom ?? {}).sort((a, b) => Number(b[1]) - Number(a[1]));
    return entries.length ? entries.map(([uom, qty]) => `${number(qty)} ${unit(uom)}`).join(' · ') : '—';
};

export default function WarehouseLocations() {
    const { locations = [], moves = [], summary = {}, warehouses = [], filters = {} } = usePage().props;
    const activeWarehouse = String(filters.warehouse ?? '');
    const [moveItem, setMoveItem] = useState(null);
    const [editor, setEditor] = useState(null);

    const apply = (warehouse) => {
        const params = warehouse ? { warehouse } : {};
        router.get('/admin/warehouses/locations', params, { preserveState: true, replace: true });
    };

    const grouped = warehouses
        .filter((warehouse) => !activeWarehouse || String(warehouse.id) === activeWarehouse)
        .map((warehouse) => ({
            ...warehouse,
            zones: locations
                .filter((location) => location.warehouse_id === warehouse.id && location.kind === 'zone')
                .map((zone) => ({
                    ...zone,
                    bins: locations.filter((location) => location.warehouse_id === warehouse.id && location.kind === 'bin' && location.parent_id === zone.id),
                })),
        }))
        .filter((warehouse) => warehouse.zones.length > 0);

    const hasFilter = Boolean(activeWarehouse);

    return (
        <>
            <Head title="仓储 · 库位" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">库位</h1>
                        <p className="mt-1 max-w-[760px] text-sm leading-6 text-om-muted">
                            按仓库维护库区与库位，并查看物料批和成品占用；可以把一项库存整项移到同仓另一个库位，只改位置、不动数量，并保留调整记录。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            {summary.zones ?? 0} 个库区 · {summary.bins ?? 0} 个库位 · 已定位库存 {summary.located_items ?? 0} 项
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setEditor({ mode: 'create-zone', warehouseId: activeWarehouse || warehouses[0]?.id || null })}
                            className="btn-touch btn-accent text-sm"
                        >
                            新增库区
                        </button>
                        <a
                            href={`/admin/warehouses/locations/labels${activeWarehouse ? `?warehouse=${activeWarehouse}` : ''}`}
                            target="_blank"
                            rel="noreferrer"
                            className="btn-touch btn-secondary text-sm"
                        >
                            打印标签
                        </a>
                        <Link href="/admin/warehouses/stock" className="btn-touch btn-secondary text-sm">库存台账</Link>
                        <Link href="/admin/warehouses" className="btn-touch btn-secondary text-sm">标签平衡</Link>
                    </div>
                </div>

                <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="启用仓库" value={summary.warehouses ?? 0} sub={warehouses.map((warehouse) => warehouse.name).join(' · ') || '没有启用仓库'} tone="text-om-ink" icon="warehouse" />
                    <Metric label="库区 / 库位" value={`${summary.zones ?? 0} / ${summary.bins ?? 0}`} sub={`${summary.zones ?? 0} 个库区 · ${summary.bins ?? 0} 个库位`} tone="text-om-ink" icon="map-pin" />
                    <Metric label="已占用库位" value={summary.occupied_bins ?? 0} sub={`冻结 / 待处理 ${summary.hold_bins ?? 0} 位`} tone={(summary.hold_bins ?? 0) > 0 ? 'text-om-blocked' : 'text-om-running'} icon="layers" />
                    <Metric label="库位内库存" value={summary.located_items ?? 0} sub={`物料批 ${summary.located_lots ?? 0} · 成品 ${summary.located_products ?? 0} · 未分配 ${summary.unassigned_items ?? 0}`} tone="text-om-ink" icon="boxes" />
                </div>

                <div className="rounded-om-sm border border-om-line bg-om-chip/40 px-4 py-3 text-xs leading-5 text-om-muted">
                    移库只调整物料批或成品库存所在库位，不改变库存余额，也不拆分批次数量；每次调整都会写一条记录。冻结或拒收批仍占库位，移到待处理位时状态不变。
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[11px] font-bold tracking-wide text-om-faint">仓库</span>
                    <FilterButton active={!activeWarehouse} onClick={() => apply('')}>全部仓</FilterButton>
                    {warehouses.map((warehouse) => (
                        <FilterButton key={warehouse.id} active={activeWarehouse === String(warehouse.id)} onClick={() => apply(warehouse.id)}>
                            {warehouse.name}
                        </FilterButton>
                    ))}
                    {hasFilter && <button type="button" onClick={() => apply('')} className="text-xs font-bold text-om-muted hover:text-om-ink">清除筛选</button>}
                </div>

                {grouped.length === 0 ? (
                    <div className="rounded-om-sm border border-om-line bg-om-card px-4 py-12 text-center">
                        <p className="text-sm font-bold text-om-ink">当前仓库还没有库位层级。</p>
                        <p className="mt-2 text-xs text-om-muted">库位会在仓库收货或成品入库时使用；这里只显示已登记的库区与库位。</p>
                    </div>
                ) : (
                    grouped.map((warehouse) => (
                        <div key={warehouse.id} className="space-y-2">
                            <div className="flex flex-wrap items-baseline justify-between gap-2 px-1">
                                <div className="flex min-w-0 items-baseline gap-2">
                                    <h2 className="text-base font-bold text-om-ink">{warehouse.name}</h2>
                                    <span className="font-mono text-[10px] text-om-faint">{warehouse.code}</span>
                                </div>
                                <span className="text-[11px] text-om-muted">{warehouse.zones.length} 个库区</span>
                            </div>
                            {warehouse.zones.map((zone) => (
                                <ZoneCard
                                    key={zone.id}
                                    zone={zone}
                                    onMove={(item, bin) => setMoveItem({ item, bin })}
                                    onEdit={() => setEditor({ mode: 'edit', location: zone })}
                                    onAddBin={() => setEditor({ mode: 'create-bin', parent: zone })}
                                    onEditBin={(bin) => setEditor({ mode: 'edit', location: bin })}
                                />
                            ))}
                        </div>
                    ))
                )}

                {moves.length > 0 && (
                    <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                            <div>
                                <h2 className="text-sm font-bold text-om-ink">最近移库</h2>
                                <p className="mt-0.5 text-[10px] text-om-faint">只记录位置变化，库存数量不变。</p>
                            </div>
                            <span className="text-[10px] text-om-muted">{moves.length} 条</span>
                        </div>
                        <div className="divide-y divide-om-line2">
                            {moves.map((move) => <MoveRow key={move.id} move={move} />)}
                        </div>
                    </div>
                )}
            </div>

            {moveItem && (
                <MoveModal
                    item={moveItem.item}
                    fromBin={moveItem.bin}
                    locations={locations}
                    onClose={() => setMoveItem(null)}
                />
            )}

            {editor && (
                <LocationModal
                    editor={editor}
                    warehouses={warehouses}
                    locations={locations}
                    onClose={() => setEditor(null)}
                />
            )}
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

function ZoneCard({ zone, onMove, onEdit, onAddBin, onEditBin }) {
    const qtyByUom = {};
    zone.bins.forEach((bin) => {
        Object.entries(bin.available_by_uom ?? {}).forEach(([uom, qty]) => {
            qtyByUom[uom] = Number(qtyByUom[uom] ?? 0) + Number(qty);
        });
    });
    const occupied = zone.bins.filter((bin) => bin.status !== 'EMPTY').length;
    const held = zone.bins.filter((bin) => bin.status === 'HOLD').length;
    const items = zone.bins.reduce((total, bin) => total + Number(bin.lots_count ?? 0), 0);

    return (
        <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
            <div className="border-b border-om-line2 px-4 py-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-mono text-[11px] font-bold text-om-accent">{zone.code}</span>
                            <span className="rounded bg-om-chip px-1.5 py-0.5 text-[10px] font-bold text-om-muted">库区</span>
                            <h3 className="text-sm font-bold text-om-ink">{zone.name}</h3>
                        </div>
                        {zone.description && <p className="mt-1 text-[11px] leading-4 text-om-muted">{zone.description}</p>}
                        <div className="mt-1.5 flex items-center gap-3 text-[10px]">
                            <button type="button" onClick={onEdit} className="font-bold text-om-muted hover:text-om-ink">编辑库区</button>
                            <button type="button" onClick={onAddBin} className="font-bold text-om-accent hover:underline">+ 库位</button>
                        </div>
                    </div>
                    <div className="text-right text-[10px] leading-4 text-om-muted">
                        <div>{zone.bins.length} 个库位 · 已占用 {occupied} 个{held > 0 ? ` · 冻结 ${held}` : ''}</div>
                        <div className="font-mono text-om-ink">{items} 项 · {qtyText(qtyByUom)}</div>
                    </div>
                </div>
            </div>

            <div className="divide-y divide-om-line2">
                {zone.bins.length === 0 && (
                    <div className="px-4 py-4 text-[11px] text-om-faint">这个库区还没有库位，点上方“+ 库位”添加。</div>
                )}
                {zone.bins.map((bin) => <BinRow key={bin.id} bin={bin} onMove={onMove} onEdit={() => onEditBin(bin)} />)}
            </div>
        </div>
    );
}

function BinRow({ bin, onMove, onEdit }) {
    const meta = BIN_STATUS[bin.status] ?? BIN_STATUS.EMPTY;
    return (
        <div id={`bin-${bin.id}`} className={`scroll-mt-20 px-4 py-3 ${bin.status === 'HOLD' ? 'bg-om-blocked-bg/10' : ''}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-[11px] font-bold text-om-ink">{bin.code}</span>
                        <span className="text-sm font-bold text-om-ink">{bin.name}</span>
                        <span className={`inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-bold ${meta.className}`}>
                            <Icon name={meta.icon} size={11} /> {meta.label}
                        </span>
                    </div>
                    {bin.description && <p className="mt-0.5 line-clamp-1 text-[10px] text-om-faint">{bin.description}</p>}
                </div>
                <div className="shrink-0 text-right">
                    <div className="text-[10px] text-om-faint">库位内</div>
                    <div className="font-mono text-[11px] font-bold text-om-ink">{bin.lots_count} 项</div>
                    <div className="mt-1 flex items-center justify-end gap-2 text-[10px]">
                        <button type="button" onClick={onEdit} className="font-bold text-om-muted hover:text-om-ink">编辑</button>
                        <a href={`/admin/warehouses/locations/${bin.id}/label`} target="_blank" rel="noreferrer" className="font-bold text-om-muted hover:text-om-ink">标签</a>
                    </div>
                </div>
            </div>

            {bin.lots.length === 0 ? (
                <div className="mt-2 rounded-om-sm border border-dashed border-om-line2 px-3 py-2 text-[11px] text-om-faint">空库位，等待收料或成品入库。</div>
            ) : (
                <div className="mt-2 space-y-1.5">
                    {bin.lots.map((item) => <BinItem key={item.id} item={item} bin={bin} onMove={onMove} />)}
                </div>
            )}
        </div>
    );
}

function BinItem({ item, bin, onMove }) {
    const isProduct = item.kind === 'product';
    return (
        <div className="flex items-start justify-between gap-2 rounded-om-sm border border-om-line2 bg-om-chip/20 px-2.5 py-2">
            <div className="min-w-0">
                <div className="truncate text-[11px] font-bold text-om-ink">{item.material_name}</div>
                <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">
                    {isProduct ? `${item.material_code} · 成品` : `${item.lot_number} · ${item.material_code}`}
                </div>
                {!isProduct && (item.received_at || item.expiry) && (
                    <div className="mt-0.5 text-[10px] text-om-faint">收货 {item.received_at ?? '—'}{item.expiry ? ` · 有效期 ${item.expiry}` : ''}</div>
                )}
            </div>
            <div className="shrink-0 text-right">
                <div className="font-mono text-xs font-bold text-om-ink">{number(item.available)} <span className="text-[10px] font-medium text-om-muted">{unit(item.uom)}</span></div>
                <span className={`mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold ${ITEM_STATUS[item.status] ?? ITEM_STATUS.received}`}>{item.status_label}</span>
                <div className="mt-1 flex items-center justify-end gap-2 whitespace-nowrap">
                    <button type="button" onClick={() => onMove(item, bin)} className="text-[10px] font-bold text-om-muted hover:text-om-ink">调整库位</button>
                    <Link href={item.url} className="text-[10px] font-bold text-om-accent hover:underline">{isProduct ? '查看库存 →' : '查看物料批 →'}</Link>
                </div>
            </div>
        </div>
    );
}

function MoveRow({ move }) {
    return (
        <div className="px-4 py-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={`rounded px-1.5 py-0.5 text-[10px] font-bold ${move.item_type === 'product_stock' ? 'bg-om-chip text-om-muted' : 'bg-om-accent/10 text-om-accent'}`}>{move.item_type_label}</span>
                        <span className="truncate text-[11px] font-bold text-om-ink">{move.item_name}</span>
                    </div>
                    <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{move.item_code}</div>
                </div>
                <div className="shrink-0 text-right">
                    <div className="font-mono text-[11px] font-bold text-om-ink">{number(move.quantity)} <span className="text-[10px] font-medium text-om-muted">{unit(move.uom)}</span></div>
                    <div className="mt-0.5 text-[10px] text-om-faint">{move.moved_at}</div>
                </div>
            </div>
            <div className="mt-1.5 flex flex-wrap items-center gap-1 text-[11px]">
                <span className="font-mono rounded bg-om-chip px-1.5 py-0.5 text-om-muted">{move.from_code ?? '未分配'}</span>
                <Icon name="arrow-right" size={11} className="text-om-faint" />
                <span className="font-mono rounded bg-om-running-bg px-1.5 py-0.5 font-bold text-om-running">{move.to_code}</span>
                <span className="text-om-faint">由 {move.moved_by} 操作</span>
            </div>
            {move.note && <div className="mt-1 text-[10px] text-om-muted">{move.note}</div>}
        </div>
    );
}

function MoveModal({ item, fromBin, locations, onClose }) {
    const [target, setTarget] = useState('');
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(null);

    const targets = locations
        .filter((location) => location.kind === 'bin' && location.warehouse_id === fromBin.warehouse_id && location.id !== fromBin.id)
        .sort((a, b) => a.code.localeCompare(b.code));

    const targetBin = targets.find((location) => String(location.id) === target);

    const submit = (event) => {
        event.preventDefault();
        if (!target) {
            setError('请选择目标库位。');
            return;
        }
        setProcessing(true);
        setError(null);
        router.post('/admin/warehouses/locations/move', {
            item_type: item.kind === 'product' ? 'product_stock' : 'material_lot',
            item_id: item.item_id,
            to_location_id: Number(target),
            note,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errors) => {
                setError(Object.values(errors)[0] ?? '移库失败，请检查目标库位。');
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="move-location-title" onClick={onClose}>
            <form onSubmit={submit} className="max-h-[92vh] w-full max-w-[560px] overflow-y-auto rounded-t-om border border-om-line bg-om-card shadow-xl sm:rounded-om" onClick={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                    <div className="min-w-0">
                        <h2 id="move-location-title" className="text-sm font-bold text-om-ink">调整库位 · {item.material_name}</h2>
                        <p className="mt-0.5 truncate font-mono text-[10px] text-om-faint">{item.kind === 'product' ? `${item.material_code} · 成品` : `${item.lot_number} · ${item.material_code}`}</p>
                    </div>
                    <button type="button" onClick={onClose} className="btn-touch btn-secondary px-3 text-xs">关闭</button>
                </div>

                <div className="grid grid-cols-2 gap-3 px-4 py-4">
                    <div>
                        <div className="text-[10px] text-om-faint">当前库位</div>
                        <div className="mt-1 font-mono text-sm font-bold text-om-ink">{fromBin.code} · {fromBin.name}</div>
                    </div>
                    <div className="text-right">
                        <div className="text-[10px] text-om-faint">整项移动数量</div>
                        <div className="mt-1 font-mono text-lg font-bold text-om-ink">{number(item.available)} <span className="text-xs font-medium text-om-muted">{unit(item.uom)}</span></div>
                    </div>
                    <div className="col-span-2">
                        <label className="block">
                            <span className="mb-1 block text-[11px] font-bold text-om-muted">目标库位</span>
                            <select value={target} onChange={(event) => setTarget(event.target.value)} className={INPUT} required>
                                <option value="">选择同仓库位</option>
                                {targets.map((location) => (
                                    <option key={location.id} value={location.id}>
                                        {location.code} · {location.name}{location.lots_count > 0 ? ` · 已有 ${location.lots_count} 项` : ' · 空'}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {targetBin && <div className="mt-1 text-[10px] text-om-faint">将移到 {targetBin.code} · {targetBin.name}，当前 {targetBin.lots_count} 项</div>}
                    </div>
                    <div className="col-span-2">
                        <label className="block">
                            <span className="mb-1 block text-[11px] font-bold text-om-muted">备注（可选）</span>
                            <textarea rows={2} value={note} onChange={(event) => setNote(event.target.value)} placeholder="例如：叶类茶集中到 A-01，待盘点" className={`${INPUT} h-auto min-h-[68px] resize-none py-2`} maxLength={160} />
                        </label>
                    </div>
                </div>

                {error && <div className="mx-4 mb-4 rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-3 py-2 text-[11px] text-om-blocked">{error}</div>}

                <div className="rounded-om-sm border border-om-line bg-om-chip/30 px-4 py-3 text-[11px] leading-5 text-om-muted">
                    移库只改库位，不拆数量、不改库存余额。物料批仍保留原批号、状态和有效期；成品仍按汇总数量管理。
                </div>

                <div className="flex items-center justify-between gap-2 border-t border-om-line2 px-4 py-3">
                    <span className="text-[10px] text-om-faint">会追加一条移库记录。</span>
                    <div className="flex gap-2">
                        <button type="button" onClick={onClose} className="btn-touch btn-secondary text-xs">取消</button>
                        <button type="submit" disabled={processing} className="btn-touch btn-accent text-xs">
                            {processing ? '移库中…' : '确认移库'}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}

function LocationModal({ editor, warehouses, locations, onClose }) {
    const location = editor.location ?? null;
    const isEdit = editor.mode === 'edit';
    const isZone = isEdit ? location?.kind === 'zone' : editor.mode === 'create-zone';
    const kind = isZone ? 'zone' : 'bin';

    const [warehouseId, setWarehouseId] = useState(String(location?.warehouse_id ?? editor.parent?.warehouse_id ?? editor.warehouseId ?? warehouses[0]?.id ?? ''));
    const [parentId, setParentId] = useState(String(location?.parent_id ?? editor.parent?.id ?? ''));
    const [code, setCode] = useState(location?.code ?? '');
    const [name, setName] = useState(location?.name ?? '');
    const [description, setDescription] = useState(location?.description ?? '');
    const [sortOrder, setSortOrder] = useState(String(location?.sort_order ?? 0));
    const [active, setActive] = useState(location?.is_active ?? true);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(null);

    const parentOptions = locations.filter((item) => item.kind === 'zone' && String(item.warehouse_id) === String(warehouseId) && (!isEdit || item.id !== location?.id));

    const title = isEdit ? `编辑${isZone ? '库区' : '库位'} · ${location?.code ?? ''}` : isZone ? '新增库区' : `新增库位 · ${editor.parent?.name ?? ''}`;

    const submit = (event) => {
        event.preventDefault();
        if (!code.trim() || !name.trim()) {
            setError('库位编码和名称不能为空。');
            return;
        }
        if (!isZone && !parentId) {
            setError('新增库位必须选择一个所属库区。');
            return;
        }

        setProcessing(true);
        setError(null);

        const payload = {
            warehouse_id: Number(warehouseId),
            kind,
            parent_id: isZone ? null : Number(parentId),
            code: code.trim(),
            name: name.trim(),
            description: description.trim(),
            sort_order: Number(sortOrder || 0),
            is_active: Boolean(active),
        };

        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errors) => {
                setError(Object.values(errors)[0] ?? '保存失败，请检查编码或所属库区。');
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        };

        if (isEdit) {
            router.put(`/admin/warehouses/locations/${location.id}`, payload, options);
        } else {
            router.post('/admin/warehouses/locations', payload, options);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="location-editor-title" onClick={onClose}>
            <form onSubmit={submit} className="max-h-[92vh] w-full max-w-[560px] overflow-y-auto rounded-t-om border border-om-line bg-om-card shadow-xl sm:rounded-om" onClick={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                    <div className="min-w-0">
                        <h2 id="location-editor-title" className="text-sm font-bold text-om-ink">{title}</h2>
                        <p className="mt-0.5 text-[10px] text-om-faint">{isEdit ? '只改库位档案，不影响已放库存或库存数量。' : isZone ? '库区是库位的上一层，创建后再往里加库位。' : '库位编码会出现在库存台账和移库记录里。'}</p>
                    </div>
                    <button type="button" onClick={onClose} className="btn-touch btn-secondary px-3 text-xs">关闭</button>
                </div>

                <div className="grid grid-cols-2 gap-3 px-4 py-4">
                    <label className="col-span-2 block">
                        <span className="mb-1 block text-[11px] font-bold text-om-muted">仓库</span>
                        <select value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)} disabled={editor.mode !== 'create-zone'} className={`${INPUT} disabled:opacity-60`} required>
                            {warehouses.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.code}</option>)}
                        </select>
                    </label>

                    {!isZone && (
                        <label className="col-span-2 block">
                            <span className="mb-1 block text-[11px] font-bold text-om-muted">所属库区</span>
                            <select value={parentId} onChange={(event) => setParentId(event.target.value)} className={INPUT} required>
                                <option value="">选择库区</option>
                                {parentOptions.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}
                            </select>
                        </label>
                    )}

                    <label className="block">
                        <span className="mb-1 block text-[11px] font-bold text-om-muted">编码</span>
                        <input value={code} onChange={(event) => setCode(event.target.value)} maxLength={50} required placeholder={isZone ? 'NT-LOC-RM-C' : 'A-05'} className={INPUT} />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-[11px] font-bold text-om-muted">名称</span>
                        <input value={name} onChange={(event) => setName(event.target.value)} maxLength={100} required placeholder={isZone ? '原料 C 区' : '辅料暂存位'} className={INPUT} />
                    </label>
                    <label className="col-span-2 block">
                        <span className="mb-1 block text-[11px] font-bold text-om-muted">说明（可选）</span>
                        <textarea rows={2} value={description} onChange={(event) => setDescription(event.target.value)} maxLength={200} placeholder="例如：只放常温包材，靠近收货口" className={`${INPUT} h-auto min-h-[62px] resize-none py-2`} />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-[11px] font-bold text-om-muted">排序</span>
                        <input type="number" min="0" max="999" value={sortOrder} onChange={(event) => setSortOrder(event.target.value)} className={INPUT} />
                    </label>
                    {isEdit && (
                        <label className="flex items-end gap-2 pb-2">
                            <input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} className="h-4 w-4 rounded border-om-line accent-om-accent" />
                            <span className="text-[11px] font-bold text-om-muted">{`启用这个${isZone ? '库区' : '库位'}`}</span>
                        </label>
                    )}
                </div>

                {error && <div className="mx-4 mb-4 rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-3 py-2 text-[11px] text-om-blocked">{error}</div>}

                {isEdit && !active && (
                    <div className="mx-4 mb-4 rounded-om-sm border border-om-downtime/30 bg-om-downtime-bg px-3 py-2 text-[11px] text-om-downtime">
                        停用后会从库位页隐藏；仍有库存或有启用中的子库位时，系统会拒绝停用。
                    </div>
                )}

                <div className="flex items-center justify-between gap-2 border-t border-om-line2 px-4 py-3">
                    <span className="text-[10px] text-om-faint">{isEdit ? '编码修改后不影响已有库存引用。' : '创建后可回到页面继续移库。'}</span>
                    <div className="flex gap-2">
                        <button type="button" onClick={onClose} className="btn-touch btn-secondary text-xs">取消</button>
                        <button type="submit" disabled={processing} className="btn-touch btn-accent text-xs">{processing ? '保存中…' : '保存'}</button>
                    </div>
                </div>
            </form>
        </div>
    );
}

WarehouseLocations.layout = (page) => <AppLayout>{page}</AppLayout>;
