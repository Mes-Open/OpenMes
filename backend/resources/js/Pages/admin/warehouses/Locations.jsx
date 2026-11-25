import { Head, Link, router, usePage } from '@inertiajs/react';
import React from 'react';
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

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const unit = (value) => ({ pcs: '件' }[value] ?? value);
const qtyText = (qtyByUom) => {
    const entries = Object.entries(qtyByUom ?? {}).sort((a, b) => Number(b[1]) - Number(a[1]));
    return entries.length ? entries.map(([uom, qty]) => `${number(qty)} ${unit(uom)}`).join(' · ') : '—';
};

export default function WarehouseLocations() {
    const { locations = [], summary = {}, warehouses = [], filters = {} } = usePage().props;
    const activeWarehouse = String(filters.warehouse ?? '');

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
                            按仓库的库区与库位看物料批和成品占用。库位只记录物理位置，不改变库存余额；库位之间暂不做过账。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            {summary.zones ?? 0} 个库区 · {summary.bins ?? 0} 个库位 · 已定位库存 {summary.located_items ?? 0} 项
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
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
                    物料批按收货批次入位，成品按成品入库单入位；冻结或拒收批仍占库位，单独标出。库位页面不替代库存台账的数量对账。
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
                            {warehouse.zones.map((zone) => <ZoneCard key={zone.id} zone={zone} />)}
                        </div>
                    ))
                )}
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

function ZoneCard({ zone }) {
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
                    </div>
                    <div className="text-right text-[10px] leading-4 text-om-muted">
                        <div>{zone.bins.length} 个库位 · 已占用 {occupied} 个{held > 0 ? ` · 冻结 ${held}` : ''}</div>
                        <div className="font-mono text-om-ink">{items} 项 · {qtyText(qtyByUom)}</div>
                    </div>
                </div>
            </div>

            <div className="divide-y divide-om-line2">
                {zone.bins.map((bin) => <BinRow key={bin.id} bin={bin} />)}
            </div>
        </div>
    );
}

function BinRow({ bin }) {
    const meta = BIN_STATUS[bin.status] ?? BIN_STATUS.EMPTY;
    return (
        <div className={`px-4 py-3 ${bin.status === 'HOLD' ? 'bg-om-blocked-bg/10' : ''}`}>
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
                </div>
            </div>

            {bin.lots.length === 0 ? (
                <div className="mt-2 rounded-om-sm border border-dashed border-om-line2 px-3 py-2 text-[11px] text-om-faint">空库位，等待收料或成品入库。</div>
            ) : (
                <div className="mt-2 space-y-1.5">
                    {bin.lots.map((item) => <BinItem key={item.id} item={item} />)}
                </div>
            )}
        </div>
    );
}

function BinItem({ item }) {
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
                <Link href={item.url} className="mt-1 block text-[10px] font-bold text-om-accent hover:underline">{isProduct ? '查看库存 →' : '查看物料批 →'}</Link>
            </div>
        </div>
    );
}

WarehouseLocations.layout = (page) => <AppLayout>{page}</AppLayout>;
