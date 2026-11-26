import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { MesHeader, MesPage, MesSection, MesStats, MesTag } from '../../../Components/mes/MesUI';

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const money = (value) => `¥${new Intl.NumberFormat('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0))}`;
const dateTime = (value) => {
    if (!value) return '—';
    try {
        return new Intl.DateTimeFormat('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(value.replace(' ', 'T')));
    } catch {
        return value;
    }
};

export default function PurchaseReconciliation() {
    const page = usePage();
    const { rows = [], summary = {}, suppliers = [], filters = {}, generatedAt } = page.props;
    const [query, setQuery] = useState(filters.search ?? '');
    const [supplier, setSupplier] = useState(String(filters.supplier ?? ''));
    const [status, setStatus] = useState(filters.status ?? 'open');

    const paramsFor = (next = {}) => {
        const params = {};
        const search = next.search ?? query;
        const supplierId = next.supplier ?? supplier;
        const statusValue = next.status ?? status;
        if (search.trim()) params.search = search.trim();
        if (supplierId) params.supplier = supplierId;
        if (statusValue) params.status = statusValue;
        return params;
    };
    const applyFilters = (next = {}) => {
        router.get('/admin/purchasing/reconciliation', paramsFor(next), { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setQuery('');
        setSupplier('');
        setStatus('open');
        router.get('/admin/purchasing/reconciliation', { status: 'open' }, { preserveState: true, replace: true });
    };
    const hasFilter = Boolean(query.trim()) || Boolean(supplier) || status !== 'open';
    const exportUrl = `/admin/purchasing/reconciliation/export?${new URLSearchParams(paramsFor()).toString()}`;

    return (
        <>
            <Head title="采购 · 供应商对账" />
            <MesPage>
                <MesHeader
                    breadcrumb={['采购与来料', '供应商对账']}
                    title="供应商对账"
                    tag={{ label: '按行对账 · 未收金额', tone: 'blue' }}
                    description="按采购订单行核对订购、已收和未收数量，已收金额按实际过账收货计算；可导出当前筛选结果交给采购或财务复核。"
                    meta={`${rows.length} 条订单行 · ${summary.open_lines ?? 0} 条仍有未收 · 最近 ${dateTime(generatedAt)} 刷新`}
                    actions={(
                        <>
                            <a href={exportUrl} className="mes-btn">导出 CSV</a>
                            <Link href="/admin/purchasing/orders" className="mes-btn">采购订单</Link>
                            <Link href="/admin/purchasing/receipts" className="mes-btn mes-btn-primary">到货登记</Link>
                        </>
                    )}
                />

                <div className="space-y-3">
                    <MesStats items={[
                        { label: '待收订单', value: number(summary.open_orders ?? 0), sub: '状态为待收的采购订单', tone: 'var(--mes-blue)' },
                        { label: '逾期订单', value: number(summary.overdue_orders ?? 0), sub: '预计到货日已过且仍有未收', tone: (summary.overdue_orders ?? 0) > 0 ? 'var(--mes-red)' : 'var(--mes-green)' },
                        { label: '未收金额', value: money(summary.outstanding_value ?? 0), sub: '按未收数量 × 单价', tone: 'var(--mes-orange)' },
                        { label: '已收金额', value: money(summary.received_value ?? 0), sub: '按实际到货数量 × 单价', tone: 'var(--mes-green)' },
                    ]} />

                    <div className="mes-panel">
                        <div className="mes-filterbar">
                            <label className="mes-label">供应商</label>
                            <select value={supplier} onChange={(event) => { setSupplier(event.target.value); applyFilters({ supplier: event.target.value }); }} className="mes-select w-[190px]">
                                <option value="">全部供应商</option>
                                {suppliers.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                            </select>
                            <select value={status} onChange={(event) => { setStatus(event.target.value); applyFilters({ status: event.target.value }); }} className="mes-select w-[130px]" aria-label="订单状态">
                                <option value="open">待收订单</option>
                                <option value="closed">已关闭订单</option>
                                <option value="all">全部订单</option>
                            </select>
                            <input
                                type="search"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={(event) => { if (event.key === 'Enter') applyFilters(); }}
                                placeholder="搜索订单号、供应商或物料"
                                className="mes-input w-[270px] flex-1"
                                aria-label="搜索订单号、供应商或物料"
                            />
                            {hasFilter && <button type="button" onClick={clearFilters} className="mes-btn">清除筛选</button>}
                            <div className="push whitespace-nowrap text-[11px] text-[var(--mes-faint)]">
                                订单行 <b className="font-mono text-[var(--mes-ink)]">{rows.length}</b> 条
                            </div>
                        </div>
                    </div>

                    <MesSection title="订单行对账" meta={`${rows.length} 条 · 未收行 ${summary.open_lines ?? 0} 条 · 最多展示 200 条`}>
                        {rows.length === 0 ? (
                            <div className="mes-empty">
                                <strong>当前筛选没有订单行</strong>
                                调整供应商或状态筛选，或先到“采购订单”创建采购承诺。
                            </div>
                        ) : (
                            <div className="mes-table-wrap">
                                <table className="mes-table min-w-[1160px]">
                                    <thead>
                                        <tr>
                                            <th className="w-[190px]">供应商 / 订单</th>
                                            <th>物料</th>
                                            <th className="w-[96px]">对账状态</th>
                                            <th className="w-[100px] num">订购</th>
                                            <th className="w-[100px] num">已收</th>
                                            <th className="w-[100px] num">未收</th>
                                            <th className="w-[100px] num">单价</th>
                                            <th className="w-[116px] num">未收金额</th>
                                            <th className="w-[150px]">预计 / 最近到货</th>
                                            <th className="w-[92px]">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => (
                                            <tr key={row.id} className={row.overdue && row.quantity_remaining > 0 ? 'bg-[var(--mes-red)]/[0.04]' : undefined}>
                                                <td>
                                                    <div className="font-mono text-[12px] font-semibold text-[var(--mes-ink)]">{row.order_number}</div>
                                                    <div className="mt-0.5 truncate text-[11px] text-[var(--mes-muted)]">{row.supplier_name}</div>
                                                </td>
                                                <td>
                                                    <div className="font-medium text-[var(--mes-ink)]">{row.material_name}</div>
                                                    <div className="mt-0.5 font-mono text-[10px] text-[var(--mes-faint)]">{row.material_code || '—'}</div>
                                                </td>
                                                <td>
                                                    <div className="flex flex-col items-start gap-1">
                                                        <MesTag tone={row.state_tone}>{row.state}</MesTag>
                                                        <span className="text-[10px] text-[var(--mes-faint)]">{row.order_status_label}</span>
                                                    </div>
                                                </td>
                                                <td className="num font-mono">{number(row.quantity_ordered)} <span className="text-[10px] text-[var(--mes-faint)]">{row.unit_of_measure || ''}</span></td>
                                                <td className="num font-mono text-[var(--mes-muted)]">{number(row.quantity_received)}</td>
                                                <td className="num font-mono font-semibold text-[var(--mes-ink)]">{number(row.quantity_remaining)}</td>
                                                <td className="num font-mono">{money(row.unit_price)}</td>
                                                <td className="num font-mono font-semibold text-[var(--mes-ink)]">{money(row.outstanding_value)}</td>
                                                <td className="whitespace-nowrap font-mono text-[11px] text-[var(--mes-muted)]">
                                                    <div>{row.expected_at || '—'}</div>
                                                    <div className="mt-0.5 text-[10px] text-[var(--mes-faint)]">{row.last_received_at ? dateTime(row.last_received_at) : '未到货'}</div>
                                                </td>
                                                <td>
                                                    {row.order_status === 'open' && row.quantity_remaining > 0 ? (
                                                        <Link href={`/admin/purchasing/receipts?purchase_order=${row.purchase_order_id}`} className="text-[11px] font-medium text-[var(--mes-blue)] hover:underline">去收货</Link>
                                                    ) : (
                                                        <span className="text-[11px] text-[var(--mes-faint)]">已核对</span>
                                                    )}
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
        </>
    );
}

PurchaseReconciliation.layout = (page) => <AppLayout>{page}</AppLayout>;
