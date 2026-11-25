import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { Icon } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { MesHeader, MesPage, MesSection, MesStats, MesTag } from '../../../Components/mes/MesUI';

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const money = (value) => `¥${new Intl.NumberFormat('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0))}`;
const unit = (value) => ({ pcs: '件', kg: 'kg', g: 'g', l: 'L' }[value] ?? value ?? '');
const today = () => new Date().toISOString().slice(0, 10);

const statusTone = (order) => {
    if (order.status === 'cancelled') return 'gray';
    if (order.status === 'closed') return 'gray';
    if (order.delivery_status === '已收齐') return 'green';
    if (order.delivery_status === '部分到货') return 'blue';
    return order.overdue ? 'red' : 'orange';
};

export default function PurchaseOrders() {
    const page = usePage();
    const { orders = [], summary = {}, suppliers = [], materials = [], filters = {}, errors = {} } = page.props;
    const [query, setQuery] = useState(filters.search ?? '');
    const [supplier, setSupplier] = useState(String(filters.supplier ?? ''));
    const [status, setStatus] = useState(filters.status ?? '');
    const [showCreate, setShowCreate] = useState(false);
    const [detailOrder, setDetailOrder] = useState(null);

    const applyFilters = (next = {}) => {
        const params = {};
        const search = next.search ?? query;
        const supplierId = next.supplier ?? supplier;
        const statusValue = next.status ?? status;
        if (search.trim()) params.search = search.trim();
        if (supplierId) params.supplier = supplierId;
        if (statusValue) params.status = statusValue;
        router.get('/admin/purchasing/orders', params, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setQuery('');
        setSupplier('');
        setStatus('');
        router.get('/admin/purchasing/orders', {}, { preserveState: true, replace: true });
    };
    const hasFilter = Boolean(query.trim()) || Boolean(supplier) || Boolean(status);

    const closeOrder = (order) => {
        router.post(`/admin/purchasing/orders/${order.id}/close`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="采购 · 采购订单" />
            <MesPage>
                <MesHeader
                    breadcrumb={['采购与来料', '采购订单']}
                    title="采购订单"
                    tag={{ label: '承诺 · 到货 · 对账', tone: 'blue' }}
                    description="向供应商下达物料采购承诺，按订单行跟踪已收与待收数量；收货过账后自动进入仓库库存和来料检验，供应商对账按行核对。"
                    meta={`${summary.open_orders ?? 0} 张待收订单 · ${summary.overdue_orders ?? 0} 张逾期 · 最近 50 张`}
                    actions={(
                        <>
                            <button type="button" onClick={() => setShowCreate(true)} className="mes-btn mes-btn-primary" disabled={!suppliers.length || !materials.length}>
                                新建采购订单
                            </button>
                            <Link href="/admin/purchasing/receipts" className="mes-btn">到货登记</Link>
                            <Link href="/admin/purchasing/reconciliation" className="mes-btn">供应商对账</Link>
                        </>
                    )}
                />

                <div className="space-y-3">
                    <MesStats items={[
                        { label: '待收订单', value: number(summary.open_orders ?? 0), sub: '状态为待收的采购承诺', tone: 'var(--mes-blue)' },
                        { label: '逾期订单', value: number(summary.overdue_orders ?? 0), sub: '预计到货日已过且未收齐', tone: (summary.overdue_orders ?? 0) > 0 ? 'var(--mes-red)' : 'var(--mes-green)' },
                        { label: '待收金额', value: money(summary.open_value ?? 0), sub: '按订购数量与单价计算', tone: 'var(--mes-ink)' },
                        { label: '供应商', value: number(summary.suppliers ?? 0), sub: '启用供应商档案', tone: 'var(--mes-muted)' },
                    ]} />

                    <div className="mes-panel">
                        <div className="mes-filterbar">
                            <label className="mes-label">供应商</label>
                            <select value={supplier} onChange={(event) => { setSupplier(event.target.value); applyFilters({ supplier: event.target.value }); }} className="mes-select w-[190px]">
                                <option value="">全部供应商</option>
                                {suppliers.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                            </select>
                            <select value={status} onChange={(event) => { setStatus(event.target.value); applyFilters({ status: event.target.value }); }} className="mes-select w-[130px]" aria-label="订单状态">
                                <option value="">全部状态</option>
                                <option value="open">待收</option>
                                <option value="closed">已关闭</option>
                                <option value="cancelled">已取消</option>
                            </select>
                            <input
                                type="search"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={(event) => { if (event.key === 'Enter') applyFilters(); }}
                                placeholder="搜索订单号或供应商"
                                className="mes-input w-[260px] flex-1"
                                aria-label="搜索订单号或供应商"
                            />
                            {hasFilter && <button type="button" onClick={clearFilters} className="mes-btn">清除筛选</button>}
                            <div className="push whitespace-nowrap text-[11px] text-[var(--mes-faint)]">
                                订单 <b className="font-mono text-[var(--mes-ink)]">{orders.length}</b> 张
                            </div>
                        </div>
                    </div>

                    {errors.error && <div className="mes-panel border-l-2 border-l-[var(--mes-red)] px-3 py-2 text-[11px] text-[var(--mes-red)]">{errors.error}</div>}

                    <MesSection title="采购订单台账" meta={`${orders.length} 张 · 按最新下单排序`}>
                        {orders.length === 0 ? (
                            <div className="mes-empty">
                                <strong>{hasFilter ? '没有匹配的采购订单' : '还没有采购订单'}</strong>
                                {hasFilter ? '换个关键字或清除筛选后再查看。' : '点“新建采购订单”录入第一张采购承诺，再按订单到货。'}
                            </div>
                        ) : (
                            <div className="mes-table-wrap">
                                <table className="mes-table min-w-[1040px]">
                                    <thead>
                                        <tr>
                                            <th className="w-[205px]">订单 / 供应商</th>
                                            <th className="w-[96px]">状态</th>
                                            <th className="w-[126px]">下单 / 预计</th>
                                            <th className="w-[84px] num">行数</th>
                                            <th className="w-[150px] num">订购 / 已收</th>
                                            <th className="w-[126px] num">金额</th>
                                            <th className="w-[150px]">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {orders.map((order) => (
                                            <tr key={order.id}>
                                                <td>
                                                    <div className="flex items-center gap-2">
                                                        <button type="button" onClick={() => setDetailOrder(order)} className="font-mono text-[12px] font-semibold text-[var(--mes-ink)] hover:text-[var(--mes-blue)]" title="查看订单明细">
                                                            {order.number}
                                                        </button>
                                                        {order.overdue && <span className="rounded-sm bg-[var(--mes-red)]/10 px-1.5 py-0.5 text-[10px] font-semibold text-[var(--mes-red)]">逾期</span>}
                                                    </div>
                                                    <div className="mt-0.5 truncate text-[11px] text-[var(--mes-muted)]">{order.supplier_name}</div>
                                                </td>
                                                <td><MesTag tone={statusTone(order)}>{order.delivery_status}</MesTag></td>
                                                <td className="whitespace-nowrap font-mono text-[11px] text-[var(--mes-muted)]">
                                                    <div>{order.ordered_at || '—'}</div>
                                                    <div className={order.overdue ? 'mt-0.5 text-[var(--mes-red)]' : 'mt-0.5 text-[var(--mes-faint)]'}>预计 {order.expected_at || '—'}</div>
                                                </td>
                                                <td className="num font-mono">{order.lines.length}</td>
                                                <td className="num font-mono">
                                                    <div className="text-[var(--mes-muted)]">订 {number(order.ordered_quantity)}</div>
                                                    <div className="mt-0.5 font-semibold text-[var(--mes-ink)]">收 {number(order.received_quantity)}</div>
                                                </td>
                                                <td className="num font-mono font-semibold text-[var(--mes-ink)]">{money(order.ordered_value)}</td>
                                                <td>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        {order.status === 'open' && (
                                                            <Link href={`/admin/purchasing/receipts?purchase_order=${order.id}`} className="text-[11px] font-medium text-[var(--mes-blue)] hover:underline">收货</Link>
                                                        )}
                                                        {order.status === 'open' && (
                                                            <button type="button" onClick={() => closeOrder(order)} className="text-[11px] text-[var(--mes-muted)] hover:text-[var(--mes-ink)]">关闭</button>
                                                        )}
                                                        <button type="button" onClick={() => setDetailOrder(order)} className="text-[11px] text-[var(--mes-muted)] hover:text-[var(--mes-blue)]">明细</button>
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

            {showCreate && (
                <CreateOrderModal
                    suppliers={suppliers}
                    materials={materials}
                    onClose={() => setShowCreate(false)}
                />
            )}

            {detailOrder && (
                <OrderDetailModal order={detailOrder} onClose={() => setDetailOrder(null)} />
            )}
        </>
    );
}

function CreateOrderModal({ suppliers, materials, onClose }) {
    const defaultMaterial = materials[0]?.id ?? '';
    const [supplierId, setSupplierId] = useState(String(suppliers[0]?.id ?? ''));
    const [orderedAt, setOrderedAt] = useState(today());
    const [expectedAt, setExpectedAt] = useState('');
    const [notes, setNotes] = useState('');
    const [lines, setLines] = useState([{ material_id: String(defaultMaterial), quantity_ordered: '', unit_price: '' }]);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const updateLine = (index, field, value) => {
        setLines((current) => current.map((line, lineIndex) => lineIndex === index ? { ...line, [field]: value } : line));
    };
    const addLine = () => setLines((current) => [...current, { material_id: String(defaultMaterial), quantity_ordered: '', unit_price: '' }]);
    const removeLine = (index) => setLines((current) => current.length > 1 ? current.filter((_, lineIndex) => lineIndex !== index) : current);
    const total = lines.reduce((sum, line) => sum + (Number(line.quantity_ordered || 0) * Number(line.unit_price || 0)), 0);
    const materialLabel = (materialId) => {
        const material = materials.find((item) => String(item.id) === String(materialId));
        return material ? `${material.code} · ${material.name}` : '选择物料';
    };

    const submit = (event) => {
        event.preventDefault();
        setErrors({});
        setProcessing(true);
        router.post('/admin/purchasing/orders', {
            supplier_id: Number(supplierId),
            ordered_at: orderedAt || null,
            expected_at: expectedAt || null,
            notes: notes || null,
            lines: lines.map((line) => ({
                material_id: Number(line.material_id),
                quantity_ordered: line.quantity_ordered,
                unit_price: line.unit_price || 0,
            })),
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (nextErrors) => {
                setErrors(nextErrors);
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="create-order-title" onClick={onClose}>
            <form onSubmit={submit} className="max-h-[92vh] w-full max-w-[760px] overflow-y-auto rounded-t-om border border-om-line bg-om-card shadow-xl sm:rounded-om" onClick={(event) => event.stopPropagation()}>
                <div className="mes-modal-head flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                    <div>
                        <h2 id="create-order-title" className="mes-modal-title text-sm font-bold text-om-ink">新建采购订单</h2>
                        <p className="mes-modal-sub mt-0.5 text-[10px] text-om-faint">保存即形成采购承诺；后续按订单收货，已收数量由过账收货单自动汇总。</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="关闭" className="mes-modal-close flex size-7 shrink-0 items-center justify-center rounded-om-sm text-om-muted hover:bg-om-chip hover:text-om-ink"><Icon name="x" size={16} /></button>
                </div>

                <div className="mes-modal-body grid gap-4 px-4 py-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                            <span>供应商</span>
                            <select value={supplierId} onChange={(event) => setSupplierId(event.target.value)} className="mes-select w-full" required>
                                {suppliers.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}
                            </select>
                            {errors.supplier_id && <span className="text-[10px] text-[var(--mes-red)]">{errors.supplier_id}</span>}
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                                <span>下单日期</span>
                                <input type="date" value={orderedAt} onChange={(event) => setOrderedAt(event.target.value)} className="mes-input w-full" />
                            </label>
                            <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                                <span>预计到货</span>
                                <input type="date" value={expectedAt} onChange={(event) => setExpectedAt(event.target.value)} className="mes-input w-full" />
                            </label>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center justify-between">
                            <div className="text-[12px] font-semibold text-[var(--mes-ink)]">订单明细</div>
                            <button type="button" onClick={addLine} className="mes-btn">加一行</button>
                        </div>
                        <div className="mes-table-wrap">
                            <table className="mes-table w-full table-fixed" style={{ minWidth: 0 }}>
                                <thead>
                                    <tr>
                                        <th>物料</th>
                                        <th className="w-[86px] num">订购数量</th>
                                        <th className="w-[88px] num">含税单价</th>
                                        <th className="w-[74px] num">小计</th>
                                        <th className="w-[32px]"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.map((line, index) => (
                                        <tr key={index}>
                                            <td className="min-w-0">
                                                <select value={line.material_id} onChange={(event) => updateLine(index, 'material_id', event.target.value)} className="mes-select w-full min-w-0" required>
                                                    {materials.map((material) => <option key={material.id} value={material.id}>{material.code} · {material.name}</option>)}
                                                </select>
                                                {errors[`lines.${index}.material_id`] && <div className="mt-1 text-[10px] text-[var(--mes-red)]">{errors[`lines.${index}.material_id`]}</div>}
                                            </td>
                                            <td>
                                                <input type="number" min="0.001" step="0.001" value={line.quantity_ordered} onChange={(event) => updateLine(index, 'quantity_ordered', event.target.value)} className="mes-input w-full text-right font-mono" placeholder="0" required />
                                                {errors[`lines.${index}.quantity_ordered`] && <div className="mt-1 text-[10px] text-[var(--mes-red)]">{errors[`lines.${index}.quantity_ordered`]}</div>}
                                            </td>
                                            <td>
                                                <input type="number" min="0" step="0.0001" value={line.unit_price} onChange={(event) => updateLine(index, 'unit_price', event.target.value)} className="mes-input w-full text-right font-mono" placeholder="0.00" />
                                                {errors[`lines.${index}.unit_price`] && <div className="mt-1 text-[10px] text-[var(--mes-red)]">{errors[`lines.${index}.unit_price`]}</div>}
                                            </td>
                                            <td className="num font-mono text-[11px] text-[var(--mes-ink)]">{money(Number(line.quantity_ordered || 0) * Number(line.unit_price || 0))}</td>
                                            <td>
                                                <button type="button" onClick={() => removeLine(index)} disabled={lines.length === 1} aria-label="删除此行" className="flex size-7 items-center justify-center text-[var(--mes-faint)] hover:text-[var(--mes-red)] disabled:opacity-30"><Icon name="x" size={14} /></button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex items-center justify-between text-[11px] text-[var(--mes-muted)]">
                            <span>{materialLabel(lines[0]?.material_id)} 等 {lines.length} 行</span>
                            <span>订单金额 <b className="font-mono text-[var(--mes-ink)]">{money(total)}</b></span>
                        </div>
                    </div>

                    <label className="grid gap-1 text-[12px] text-[var(--mes-muted)]">
                        <span>备注</span>
                        <textarea value={notes} onChange={(event) => setNotes(event.target.value)} rows={2} className="mes-input w-full resize-y" placeholder="交货要求、合同号等" />
                    </label>

                    {errors.lines && <div className="text-[11px] text-[var(--mes-red)]">{errors.lines}</div>}
                    {errors.error && <div className="text-[11px] text-[var(--mes-red)]">{errors.error}</div>}
                </div>

                <div className="mes-modal-foot flex items-center justify-between gap-2 border-t border-om-line2 px-4 py-3">
                    <span className="text-[10px] text-[var(--mes-faint)]">保存后可到“到货登记”按订单行收货。</span>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={onClose} className="mes-btn" disabled={processing}>取消</button>
                        <button type="submit" className="mes-btn mes-btn-primary" disabled={processing || !lines.length}>{processing ? '保存中…' : '创建订单'}</button>
                    </div>
                </div>
            </form>
        </div>
    );
}

function OrderDetailModal({ order, onClose }) {
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="order-detail-title" onClick={onClose}>
            <div className="max-h-[92vh] w-full max-w-[820px] overflow-y-auto rounded-t-om border border-om-line bg-om-card shadow-xl sm:rounded-om" onClick={(event) => event.stopPropagation()}>
                <div className="mes-modal-head flex items-start justify-between gap-3 border-b border-om-line2 px-4 py-3">
                    <div>
                        <h2 id="order-detail-title" className="mes-modal-title font-mono text-sm font-bold text-om-ink">{order.number}</h2>
                        <p className="mes-modal-sub mt-0.5 text-[10px] text-om-faint">{order.supplier_name} · 下单 {order.ordered_at || '—'} · 预计 {order.expected_at || '—'}</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="关闭" className="mes-modal-close flex size-7 shrink-0 items-center justify-center rounded-om-sm text-om-muted hover:bg-om-chip hover:text-om-ink"><Icon name="x" size={16} /></button>
                </div>
                <div className="grid gap-3 px-4 py-4">
                    <div className="flex flex-wrap items-center gap-2 text-[11px] text-[var(--mes-muted)]">
                        <MesTag tone={statusTone(order)}>{order.delivery_status}</MesTag>
                        <span>订购 {number(order.ordered_quantity)}</span>
                        <span>已收 {number(order.received_quantity)}</span>
                        <span>待收 {number(order.remaining_quantity)}</span>
                        <span>金额 {money(order.ordered_value)}</span>
                    </div>
                    <div className="mes-table-wrap">
                        <table className="mes-table min-w-[680px]">
                            <thead>
                                <tr>
                                    <th>物料</th>
                                    <th className="w-[100px] num">订购</th>
                                    <th className="w-[100px] num">已收</th>
                                    <th className="w-[100px] num">待收</th>
                                    <th className="w-[110px] num">单价</th>
                                    <th className="w-[120px] num">行金额</th>
                                    <th className="w-[100px]">预计</th>
                                </tr>
                            </thead>
                            <tbody>
                                {order.lines.map((line) => (
                                    <tr key={line.id}>
                                        <td>
                                            <div className="font-medium text-[var(--mes-ink)]">{line.material_name}</div>
                                            <div className="mt-0.5 font-mono text-[10px] text-[var(--mes-faint)]">{line.material_code || '—'}</div>
                                        </td>
                                        <td className="num font-mono">{number(line.quantity_ordered)} <span className="text-[10px] text-[var(--mes-faint)]">{unit(line.unit_of_measure)}</span></td>
                                        <td className="num font-mono text-[var(--mes-muted)]">{number(line.quantity_received)}</td>
                                        <td className="num font-mono font-semibold text-[var(--mes-ink)]">{number(line.quantity_remaining)}</td>
                                        <td className="num font-mono">{money(line.unit_price)}</td>
                                        <td className="num font-mono">{money(line.line_total)}</td>
                                        <td className="whitespace-nowrap font-mono text-[11px] text-[var(--mes-muted)]">{line.expected_at || order.expected_at || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {order.notes && <div className="rounded-sm bg-[var(--mes-chip)] px-3 py-2 text-[11px] text-[var(--mes-muted)]">备注：{order.notes}</div>}
                </div>
                <div className="mes-modal-foot flex items-center justify-between gap-2 border-t border-om-line2 px-4 py-3">
                    <span className="text-[10px] text-[var(--mes-faint)]">已收数量来自已过账的收货单，取消收货会同步减少。</span>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={onClose} className="mes-btn">关闭</button>
                        {order.status === 'open' && (
                            <Link href={`/admin/purchasing/receipts?purchase_order=${order.id}`} className="mes-btn mes-btn-primary">按订单收货</Link>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

PurchaseOrders.layout = (page) => <AppLayout>{page}</AppLayout>;
