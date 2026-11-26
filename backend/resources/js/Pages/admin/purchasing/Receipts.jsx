import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useEffect, useMemo, useState } from 'react';
import { Icon } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { MesHeader, MesPage, MesSection, MesStats, MesTag } from '../../../Components/mes/MesUI';

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const unit = (value) => ({ pcs: '件', kg: 'kg', g: 'g', l: 'L' }[value] ?? value ?? '');
const dateTime = (value, timezone) => {
    if (!value) return '—';
    try {
        return new Intl.DateTimeFormat('zh-CN', {
            month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
            timeZone: timezone || undefined,
        }).format(new Date(value));
    } catch {
        return value;
    }
};

const receiptMatches = (row, query) => {
    const q = query.trim().toLowerCase();
    if (!q) return true;
    return [row.material_name, row.material_code, row.reference, row.operator, row.source_label, row.warehouse?.name]
        .some((value) => value != null && String(value).toLowerCase().includes(q));
};
const lotMatches = (row, query) => {
    const q = query.trim().toLowerCase();
    if (!q) return true;
    return [row.lot_number, row.material_name, row.material_code, row.supplier_name, row.supplier_lot_no, row.location, row.warehouse]
        .some((value) => value != null && String(value).toLowerCase().includes(q));
};

export default function PurchasingReceipts() {
    const page = usePage();
    const { receipts = [], pendingLots = [], summary = {}, warehouses = [], materialWarehouses = [], materials = [], purchaseOrders = [], selectedPurchaseOrder = null, filters = {} } = page.props;
    const timezone = page.props.timezone;
    const activeWarehouse = String(filters.warehouse ?? '');
    const [query, setQuery] = useState('');
    const [showReceipt, setShowReceipt] = useState(false);
    const [notice, setNotice] = useState('');

    const filteredReceipts = useMemo(() => receipts.filter((row) => receiptMatches(row, query)), [receipts, query]);
    const filteredLots = useMemo(() => pendingLots.filter((row) => lotMatches(row, query)), [pendingLots, query]);
    const hasFilter = Boolean(query.trim()) || Boolean(activeWarehouse);

    const applyWarehouse = (warehouse) => {
        const params = warehouse ? { warehouse } : {};
        router.get('/admin/purchasing/receipts', params, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setQuery('');
        if (activeWarehouse) applyWarehouse('');
    };

    return (
        <>
            <Head title="采购 · 到货登记" />
            <MesPage>
                <MesHeader
                    breadcrumb={['采购与来料', '到货登记']}
                    title="到货登记"
                    tag={{ label: '收货 · 待检 · 批次', tone: 'blue' }}
                    description="按采购订单收货，直接形成仓储入库并派发来料检验；无订单的零星到货仍可用手工登记。手工到货只记库存流水，不会自动生成批次。"
                    meta={`${purchaseOrders.length} 张待收采购订单 · ${summary.active_materials ?? 0} 个启用物料 · ${warehouses.length} 个启用仓库`}
                    actions={(
                        <>
                            <button type="button" onClick={() => setShowReceipt(true)} disabled={!materials.length} className="mes-btn mes-btn-primary">
                                登记到货
                            </button>
                            <Link href="/admin/purchasing/orders" className="mes-btn">采购订单</Link>
                            <Link href="/admin/purchasing/reconciliation" className="mes-btn">供应商对账</Link>
                            <Link href="/admin/warehouses/stock" className="mes-btn">库存台账</Link>
                        </>
                    )}
                />

                <div className="space-y-3">
                    {notice && (
                        <div className="mes-panel flex items-center justify-between gap-3 border-l-2 border-l-[var(--mes-green)] px-3 py-2 text-[12px]">
                            <span className="text-[var(--mes-ink)]">{notice}</span>
                            <button type="button" onClick={() => setNotice('')} className="text-[var(--mes-faint)] hover:text-[var(--mes-ink)]" aria-label="关闭提示">
                                <Icon name="x" size={14} />
                            </button>
                        </div>
                    )}

                    <MesStats items={[
                        { label: '今日到货', value: number(summary.today_receipts ?? 0), sub: '按到货流水计', tone: 'var(--mes-blue)' },
                        { label: '近 7 天到货', value: number(summary.week_receipts ?? 0), sub: '最近一周收货流水', tone: 'var(--mes-ink)' },
                        { label: '待检批次', value: number(summary.pending_lots ?? 0), sub: '状态为“已收货”的物料批', tone: (summary.pending_lots ?? 0) > 0 ? 'var(--mes-orange)' : 'var(--mes-green)' },
                        { label: '冻结批次', value: number(summary.hold_lots ?? 0), sub: '等待质量处理，不可领用', tone: (summary.hold_lots ?? 0) > 0 ? 'var(--mes-red)' : 'var(--mes-green)' },
                    ]} />

                    <OrderReceiptPanel
                        purchaseOrders={purchaseOrders}
                        selectedOrder={selectedPurchaseOrder}
                        warehouses={materialWarehouses}
                        activePurchaseOrder={String(filters.purchase_order ?? '')}
                    />

                    <div className="mes-panel">
                        <div className="mes-filterbar">
                            <label className="mes-label">仓库</label>
                            <select value={activeWarehouse} onChange={(event) => applyWarehouse(event.target.value)} className="mes-select w-[180px]">
                                <option value="">全部仓库</option>
                                {warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>)}
                            </select>
                            <input
                                type="search"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="搜索物料、参考号、操作人或批次"
                                aria-label="搜索物料、参考号、操作人或批次"
                                className="mes-input w-[260px] flex-1"
                            />
                            {hasFilter && <button type="button" onClick={clearFilters} className="mes-btn">清除筛选</button>}
                            <div className="push whitespace-nowrap text-[11px] text-[var(--mes-faint)]">
                                到货流水 <b className="font-mono text-[var(--mes-ink)]">{filteredReceipts.length}</b> · 待检/冻结 <b className="font-mono text-[var(--mes-ink)]">{filteredLots.length}</b>
                            </div>
                        </div>
                    </div>

                    <MesSection
                        title="最近到货"
                        meta={`${filteredReceipts.length} / ${receipts.length} 条 · 最多展示 40 条`}
                        actions={(<Link href="/admin/materials" className="mes-btn">查看物料档案</Link>)}
                    >
                        {filteredReceipts.length === 0 ? (
                            <div className="mes-empty">
                                <strong>{hasFilter ? '没有匹配的到货流水' : '还没有到货流水'}</strong>
                                {hasFilter ? '换个关键字或清除筛选，再查看全部收货记录。' : '点“登记到货”录入第一笔手工到货。'}
                            </div>
                        ) : (
                            <div className="mes-table-wrap">
                                <table className="mes-table min-w-[860px]">
                                    <thead>
                                        <tr>
                                            <th className="w-[108px]">到货时间</th>
                                            <th>物料</th>
                                            <th className="w-[128px] num">数量</th>
                                            <th className="w-[170px]">到货参考</th>
                                            <th className="w-[86px]">来源</th>
                                            <th className="w-[160px]">仓库 / 操作人</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredReceipts.map((row) => (
                                            <tr key={row.id}>
                                                <td className="whitespace-nowrap font-mono text-[11px] text-[var(--mes-muted)]">{dateTime(row.received_at, timezone)}</td>
                                                <td>
                                                    <Link href={`/admin/materials/${row.material_id}`} className="font-medium text-[var(--mes-ink)] hover:text-[var(--mes-blue)]">
                                                        {row.material_name}
                                                    </Link>
                                                    <div className="mt-0.5 font-mono text-[10px] text-[var(--mes-faint)]">{row.material_code || '—'}</div>
                                                </td>
                                                <td className="num font-mono font-semibold text-[var(--mes-ink)]">{number(row.quantity)} <span className="text-[10px] font-normal text-[var(--mes-faint)]">{unit(row.uom)}</span></td>
                                                <td className="max-w-[170px] truncate font-mono text-[11px] text-[var(--mes-muted)]" title={row.reference || ''}>{row.reference || '—'}</td>
                                                <td><MesTag tone={row.source_type === 'manual_receipt' ? 'blue' : 'gray'}>{row.source_label}</MesTag></td>
                                                <td className="text-[11px] text-[var(--mes-muted)]">
                                                    <div>{row.warehouse?.name || '未归属仓库'}</div>
                                                    <div className="mt-0.5 text-[10px] text-[var(--mes-faint)]">{row.operator || '系统'}</div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </MesSection>

                    <MesSection
                        title="待检 / 冻结批次"
                        meta={`${filteredLots.length} / ${pendingLots.length} 条 · 批次状态以质量处置为准`}
                        actions={(<Link href="/admin/material-lots" className="mes-btn">批次台账</Link>)}
                    >
                        {filteredLots.length === 0 ? (
                            <div className="mes-empty">
                                <strong>{hasFilter ? '没有匹配的待检批次' : '当前没有待检或冻结批次'}</strong>
                                {hasFilter ? '换个关键字或清除筛选，再查看全部待检批次。' : '物料批由收货或检验环节创建；手工到货流水本身不会生成批次。'}
                            </div>
                        ) : (
                            <div className="mes-table-wrap">
                                <table className="mes-table min-w-[880px]">
                                    <thead>
                                        <tr>
                                            <th className="w-[180px]">批次 / 供应商</th>
                                            <th>物料</th>
                                            <th className="w-[118px] num">到货数量</th>
                                            <th className="w-[104px]">到货时间</th>
                                            <th className="w-[150px]">仓库 / 库位</th>
                                            <th className="w-[120px]">状态</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredLots.map((row) => (
                                            <tr key={row.id}>
                                                <td>
                                                    <div className="font-mono text-[12px] font-semibold text-[var(--mes-ink)]">{row.lot_number || '—'}</div>
                                                    <div className="mt-0.5 truncate text-[10px] text-[var(--mes-faint)]" title={row.supplier_name || ''}>{row.supplier_name || '未填供应商'}{row.supplier_lot_no ? ` · ${row.supplier_lot_no}` : ''}</div>
                                                </td>
                                                <td>
                                                    <Link href={row.material_url} className="font-medium text-[var(--mes-ink)] hover:text-[var(--mes-blue)]">{row.material_name}</Link>
                                                    <div className="mt-0.5 font-mono text-[10px] text-[var(--mes-faint)]">{row.material_code || '—'}</div>
                                                </td>
                                                <td className="num font-mono font-semibold text-[var(--mes-ink)]">{number(row.quantity_received)} <span className="text-[10px] font-normal text-[var(--mes-faint)]">{unit(row.uom)}</span></td>
                                                <td className="whitespace-nowrap font-mono text-[11px] text-[var(--mes-muted)]">{dateTime(row.received_at, timezone)}</td>
                                                <td className="text-[11px] text-[var(--mes-muted)]">
                                                    <div>{row.warehouse || '未归属仓库'}</div>
                                                    <div className="mt-0.5 text-[10px] text-[var(--mes-faint)]">{row.location || '未指定库位'}</div>
                                                </td>
                                                <td>
                                                    <div className="flex items-center gap-2">
                                                        <MesTag tone={row.status_tone}>{row.status_label}</MesTag>
                                                        <Link href={row.lot_url} className="text-[11px] text-[var(--mes-blue)] hover:underline">查看</Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </MesSection>
                </div>
            </MesPage>

            {showReceipt && (
                <ReceiptModal
                    materials={materials}
                    onClose={() => setShowReceipt(false)}
                    onDone={() => {
                        setShowReceipt(false);
                        setNotice('到货已登记，库存流水已更新；如需建批次，请到物料批台账或检验环节处理。');
                    }}
                />
            )}
        </>
    );
}

function OrderReceiptPanel({ purchaseOrders, selectedOrder, warehouses, activePurchaseOrder }) {
    const [quantities, setQuantities] = useState({});
    const [warehouseId, setWarehouseId] = useState(String(warehouses[0]?.id ?? ''));
    const [reference, setReference] = useState('');
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setQuantities({});
        setErrors({});
        setProcessing(false);
    }, [selectedOrder?.id]);

    const chooseOrder = (value) => {
        router.get('/admin/purchasing/receipts', value ? { purchase_order: value } : {}, { preserveState: true, replace: true });
    };
    const setQuantity = (lineId, value) => {
        setQuantities((current) => ({ ...current, [lineId]: value }));
    };
    const lineRows = selectedOrder?.lines ?? [];
    const enteredTotal = lineRows.reduce((sum, line) => sum + Number(quantities[line.id] || 0), 0);
    const canSubmit = Boolean(selectedOrder?.can_receive && warehouseId && reference.trim() && enteredTotal > 0 && !processing);

    const submit = (event) => {
        event.preventDefault();
        if (!selectedOrder || !canSubmit) return;
        setErrors({});
        setProcessing(true);

        const lines = lineRows
            .filter((line) => Number(quantities[line.id] || 0) > 0)
            .map((line) => ({
                purchase_order_line_id: line.id,
                quantity: quantities[line.id],
            }));

        router.post('/admin/purchasing/receipts/order', {
            purchase_order_id: selectedOrder.id,
            warehouse_id: Number(warehouseId),
            reference,
            lines,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setReference('');
                setQuantities({});
            },
            onError: (nextErrors) => {
                setErrors(nextErrors);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <MesSection
            title="按采购订单收货"
            meta={selectedOrder ? `${selectedOrder.number} · ${selectedOrder.supplier_name} · ${lineRows.length} 行待收` : '选择采购订单后按行登记到货，过账即形成仓储入库'}
            actions={(<Link href="/admin/purchasing/orders" className="mes-btn">采购订单台账</Link>)}
        >
            <div className="grid gap-3 px-3 py-3">
                <div className="flex flex-wrap items-end gap-2">
                    <label className="grid gap-1 text-[11px] text-[var(--mes-muted)]">
                        <span>采购订单</span>
                        <select value={activePurchaseOrder} onChange={(event) => chooseOrder(event.target.value)} className="mes-select w-[300px]">
                            <option value="">选择待收采购订单</option>
                            {purchaseOrders.map((order) => (
                                <option key={order.id} value={order.id}>
                                    {order.number} · {order.supplier_name}{order.expected_at ? ` · 预计 ${order.expected_at}` : ''}
                                </option>
                            ))}
                        </select>
                    </label>
                    {selectedOrder && (
                        <div className="flex flex-wrap items-center gap-2 text-[11px] text-[var(--mes-muted)]">
                            <MesTag tone={selectedOrder.can_receive ? 'blue' : 'gray'}>{selectedOrder.can_receive ? '待收' : '不可收货'}</MesTag>
                            <span>下单 {selectedOrder.ordered_at || '—'}</span>
                            <span>预计 {selectedOrder.expected_at || '—'}</span>
                            <span>待收 <b className="font-mono text-[var(--mes-ink)]">{number(selectedOrder.remaining_quantity)}</b></span>
                        </div>
                    )}
                </div>

                {!selectedOrder ? (
                    <div className="mes-empty">
                        <strong>先选择一张采购订单</strong>
                        选中后可按订单行填写到货数量，提交会生成仓储入库单并自动派发来料检验；没有采购订单的零星到货请用右上角“登记到货”。
                    </div>
                ) : (
                    <form onSubmit={submit} className="grid gap-3">
                        {!selectedOrder.can_receive && (
                            <div className="border-l-2 border-l-[var(--mes-orange)] bg-[var(--mes-chip)] px-3 py-2 text-[11px] text-[var(--mes-muted)]">
                                该订单已收齐或已关闭，不能再登记到货。请在采购订单台账中核对状态。
                            </div>
                        )}

                        <div className="mes-table-wrap">
                            <table className="mes-table w-full table-fixed" style={{ minWidth: 0 }}>
                                <thead>
                                    <tr>
                                        <th>物料</th>
                                        <th className="w-[66px] num">订购</th>
                                        <th className="w-[58px] num">已收</th>
                                        <th className="w-[58px] num">待收</th>
                                        <th className="w-[104px] num">本次到货</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lineRows.map((line) => (
                                        <tr key={line.id}>
                                            <td>
                                                <div className="font-medium text-[var(--mes-ink)]">{line.material_name}</div>
                                                <div className="mt-0.5 font-mono text-[10px] text-[var(--mes-faint)]">{line.material_code || '—'}</div>
                                            </td>
                                            <td className="num font-mono">{number(line.quantity_ordered)} <span className="text-[10px] text-[var(--mes-faint)]">{unit(line.unit_of_measure)}</span></td>
                                            <td className="num font-mono text-[var(--mes-muted)]">{number(line.quantity_received)}</td>
                                            <td className="num font-mono font-semibold text-[var(--mes-ink)]">{number(line.quantity_remaining)}</td>
                                            <td>
                                                <div className="flex items-center gap-1.5">
                                                    <input
                                                        type="number"
                                                        min="0.001"
                                                        step="0.001"
                                                        max={line.quantity_remaining > 0 ? line.quantity_remaining : undefined}
                                                        value={quantities[line.id] ?? ''}
                                                        onChange={(event) => setQuantity(line.id, event.target.value)}
                                                        placeholder="0"
                                                        disabled={line.quantity_remaining <= 0 || !selectedOrder.can_receive}
                                                        className="mes-input w-full max-w-[118px] text-right font-mono"
                                                        aria-label={`${line.material_name} 本次到货数量`}
                                                    />
                                                    <span className="text-[10px] text-[var(--mes-faint)]">{unit(line.unit_of_measure)}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                                <span>收货仓库</span>
                                <select value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)} className="mes-select w-full" required>
                                    {warehouses.map((warehouse) => (
                                        <option key={warehouse.id} value={warehouse.id}>{warehouse.code} · {warehouse.name}</option>
                                    ))}
                                </select>
                                {errors.warehouse_id && <span className="text-[10px] text-[var(--mes-red)]">{errors.warehouse_id}</span>}
                            </label>
                            <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                                <span>供方送货单号</span>
                                <input type="text" maxLength={120} value={reference} onChange={(event) => setReference(event.target.value)} className="mes-input w-full" placeholder="送货单 / 到货单编号" required />
                                {errors.reference && <span className="text-[10px] text-[var(--mes-red)]">{errors.reference}</span>}
                            </label>
                        </div>

                        {(errors.lines || errors.purchase_order_id || errors.error) && (
                            <div className="text-[11px] text-[var(--mes-red)]">
                                {errors.lines || errors.purchase_order_id || errors.error}
                            </div>
                        )}

                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--mes-line)] pt-3">
                            <div className="text-[11px] text-[var(--mes-muted)]">
                                本次合计 <b className="font-mono text-[var(--mes-ink)]">{number(enteredTotal)}</b> · 过账后更新仓库库存并派发来料检验
                            </div>
                            <button type="submit" className="mes-btn mes-btn-primary" disabled={!canSubmit}>
                                {processing ? '入账中…' : '确认收货入账'}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </MesSection>
    );
}

function ReceiptModal({ materials, onClose, onDone }) {
    const [materialId, setMaterialId] = useState(String(materials[0]?.id ?? ''));
    const [quantity, setQuantity] = useState('');
    const [reference, setReference] = useState('');
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const selected = materials.find((material) => String(material.id) === String(materialId));
    const uom = selected?.unit_of_measure;

    const submit = (event) => {
        event.preventDefault();
        setErrors({});
        setProcessing(true);
        router.post('/admin/purchasing/receipts', {
            material_id: Number(materialId),
            quantity,
            reference,
        }, {
            preserveScroll: true,
            onSuccess: () => onDone(),
            onError: (nextErrors) => {
                setErrors(nextErrors);
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="receipt-modal-title" onClick={onClose}>
            <form onSubmit={submit} className="max-h-[92vh] w-full max-w-[540px] overflow-y-auto rounded-t-om border border-om-line bg-om-card shadow-xl sm:rounded-om" onClick={(event) => event.stopPropagation()}>
                <div className="mes-modal-head flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                    <div className="min-w-0">
                        <h2 id="receipt-modal-title" className="mes-modal-title text-sm font-bold text-om-ink">登记到货</h2>
                        <p className="mes-modal-sub mt-0.5 text-[10px] text-om-faint">写入库存流水，不自动生成物料批；参考号重复提交会被拦截。</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="关闭" className="mes-modal-close flex size-7 shrink-0 items-center justify-center rounded-om-sm text-om-muted hover:bg-om-chip hover:text-om-ink"><Icon name="x" size={16} /></button>
                </div>

                <div className="mes-modal-body grid gap-3 px-4 py-4">
                    <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                        <span>物料</span>
                        <select value={materialId} onChange={(event) => setMaterialId(event.target.value)} className="mes-select w-full" required>
                            {materials.map((material) => (
                                <option key={material.id} value={material.id}>{material.code} · {material.name}</option>
                            ))}
                        </select>
                        {selected && (
                            <span className="text-[10px] text-[var(--mes-faint)]">
                                当前库存 {number(selected.stock_quantity)} {unit(uom)} · 批次跟踪 {selected.tracking_type === 'none' || !selected.tracking_type ? '不跟踪' : selected.tracking_type}
                            </span>
                        )}
                    </label>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                            <span>到货数量 {uom ? `（${unit(uom)}）` : ''}</span>
                            <input type="number" min="0.001" step="0.001" value={quantity} onChange={(event) => setQuantity(event.target.value)} className="mes-input w-full" placeholder="0" required />
                            {errors.quantity && <span className="text-[10px] text-[var(--mes-red)]">{errors.quantity}</span>}
                        </label>
                        <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                            <span>到货参考号</span>
                            <input type="text" maxLength={120} value={reference} onChange={(event) => setReference(event.target.value)} className="mes-input w-full" placeholder="送货单号 / 到货单号" required />
                            {errors.reference && <span className="text-[10px] text-[var(--mes-red)]">{errors.reference}</span>}
                        </label>
                    </div>

                    {errors.material_id && <div className="text-[11px] text-[var(--mes-red)]">{errors.material_id}</div>}
                    {errors.error && <div className="text-[11px] text-[var(--mes-red)]">{errors.error}</div>}
                    {Object.keys(errors).length > 0 && !errors.quantity && !errors.reference && !errors.material_id && !errors.error && (
                        <div className="text-[11px] text-[var(--mes-red)]">{Object.values(errors)[0]}</div>
                    )}
                </div>

                <div className="mes-modal-foot flex items-center justify-between gap-2 border-t border-om-line2 px-4 py-3">
                    <span className="text-[10px] text-[var(--mes-faint)]">提交后可在“最近到货”中核对流水。</span>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={onClose} className="mes-btn" disabled={processing}>取消</button>
                        <button type="submit" className="mes-btn mes-btn-primary" disabled={processing || !materialId}>{processing ? '提交中…' : '确认登记'}</button>
                    </div>
                </div>
            </form>
        </div>
    );
}

PurchasingReceipts.layout = (page) => <AppLayout>{page}</AppLayout>;
