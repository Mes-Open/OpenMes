import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useMemo, useRef, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { MesHeader, MesPage, MesPager, MesSection, MesStats, MesTag, useClientPage } from '../../../Components/mes/MesUI';

const STATUS_META = {
    OK: { label: '在库', tone: 'green' },
    HOLD: { label: '冻结', tone: 'red' },
    NOLOT: { label: '缺批次', tone: 'orange' },
    ZERO: { label: '零库存', tone: 'gray' },
};

const TYPES = [
    { key: '', label: '全部类型' },
    { key: 'material', label: '原料' },
    { key: 'packaging', label: '包材' },
    { key: 'product', label: '成品' },
];
const STATUS_TABS = [
    { key: '', label: '全部' },
    { key: 'OK', label: '在库' },
    { key: 'HOLD', label: '冻结' },
    { key: 'NOLOT', label: '缺批次' },
    { key: 'ZERO', label: '零库存' },
];

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));
const unit = (value) => ({ pcs: '件' }[value] ?? value);
const clean = (patch) => Object.fromEntries(Object.entries(patch).filter(([, value]) => value !== '' && value !== null && value !== undefined));

export default function WarehouseStock() {
    const { rows = [], summary = {}, warehouses = [], filters = {} } = usePage().props;
    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState('');
    const [detail, setDetail] = useState(null);
    const [selected, setSelected] = useState([]);
    const searchRef = useRef(null);

    const apply = (patch) => {
        const next = clean({ ...filters, ...patch });
        router.get('/admin/warehouses/stock', next, { preserveState: true, replace: true });
    };

    const clear = () => {
        setQ('');
        setStatus('');
        setSelected([]);
        router.get('/admin/warehouses/stock');
    };

    const statusCounts = useMemo(() => {
        const counts = { '': rows.length, OK: 0, HOLD: 0, NOLOT: 0, ZERO: 0 };
        rows.forEach((row) => { counts[row.status] = (counts[row.status] ?? 0) + 1; });
        return counts;
    }, [rows]);

    const filteredRows = useMemo(
        () => (status ? rows.filter((row) => row.status === status) : rows),
        [rows, status],
    );
    const pager = useClientPage(filteredRows, 20);

    const allVisibleSelected = pager.visible.length > 0 && pager.visible.every((row) => selected.includes(row.id));
    const toggleRow = (id) => setSelected((current) => current.includes(id) ? current.filter((value) => value !== id) : [...current, id]);
    const toggleVisible = () => {
        if (allVisibleSelected) {
            setSelected((current) => current.filter((id) => !pager.visible.some((row) => row.id === id)));
        } else {
            setSelected((current) => [...new Set([...current, ...pager.visible.map((row) => row.id)])]);
        }
    };

    const exportRows = (onlySelected = false) => {
        const source = onlySelected ? rows.filter((row) => selected.includes(row.id)) : filteredRows;
        const header = ['状态', '物料编码', '物料名称', '类型', '仓库编码', '仓库', '库位', '数量', '单位', '批次', '跟踪方式'];
        const lines = source.map((row) => [
            row.status_label, row.item_code, row.item_name, row.item_type, row.warehouse_code,
            row.warehouse_name, row.location_summary ?? '', row.quantity, unit(row.uom),
            row.lot_summary, row.tracking_label,
        ]);
        const csv = [header, ...lines].map((line) => line.map(csvCell).join(',')).join('\r\n');
        const blob = new Blob([`\ufeff${csv}`], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `库存台账_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    };

    const hasFilter = Boolean(filters.q || filters.warehouse || filters.type || status);
    const qtyEntries = Object.entries(summary.qty_by_uom ?? {}).sort((a, b) => Number(b[1]) - Number(a[1]));
    const mainQty = qtyEntries[0];

    return (
        <>
            <Head title="仓储 · 库存台账" />
            <MesPage>
                <MesHeader
                    breadcrumb={['仓储管理', '库存台账']}
                    title="库存台账"
                    tag={{ label: '实时库存', tone: 'blue' }}
                    description="按仓库查看实物库存、批次与冻结状态。数量来自库存余额，批次来自物料批；本页只读，调整库存请走业务单据。"
                    meta={`${warehouses.length} 个启用仓库 · 数据按冻结、缺批次优先排序`}
                    actions={(
                        <>
                            <button type="button" onClick={() => router.reload({ only: ['rows', 'summary'] })} className="mes-btn">刷新</button>
                            <button type="button" onClick={() => exportRows(false)} className="mes-btn">导出当前</button>
                            <Link href="/admin/warehouses/locations" className="mes-btn">库位管理</Link>
                            <Link href="/admin/warehouses/locations/reconcile" className="mes-btn mes-btn-primary">库位对账</Link>
                        </>
                    )}
                />

                <div className="space-y-3">
                    <MesStats
                        items={[
                            { label: '库存项', value: summary.total ?? 0, sub: `原料 ${summary.material ?? 0} · 包材 ${summary.packaging ?? 0} · 成品 ${summary.product ?? 0}`, tone: 'var(--mes-blue)' },
                            { label: '库存数量', value: mainQty ? `${number(mainQty[1])} ${unit(mainQty[0])}` : '0', sub: qtyEntries.slice(1).map(([uom, qty]) => `${number(qty)} ${unit(uom)}`).join(' · ') || '按单位分开统计', tone: 'var(--mes-ink)' },
                            { label: '冻结 / 缺批次', value: (summary.held_lots ?? 0) + (summary.missing_lots ?? 0), sub: `冻结批 ${summary.held_lots ?? 0} · 缺批次 ${summary.missing_lots ?? 0}`, tone: ((summary.held_lots ?? 0) + (summary.missing_lots ?? 0)) > 0 ? 'var(--mes-red)' : 'var(--mes-green)' },
                            { label: '已挂库位', value: `${rows.filter((row) => row.location_summary).length} / ${summary.total ?? 0}`, sub: '库存行已有库位编码', tone: rows.every((row) => row.location_summary) ? 'var(--mes-green)' : 'var(--mes-orange)' },
                        ]}
                    />

                    <div className="mes-panel">
                        <div className="mes-filterbar">
                            <label className="mes-label">仓库</label>
                            <select
                                value={String(filters.warehouse ?? '')}
                                onChange={(event) => apply({ warehouse: event.target.value })}
                                className="mes-select w-[160px]"
                            >
                                <option value="">全部仓库</option>
                                {warehouses.map((warehouse) => (
                                    <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                                ))}
                            </select>

                            <label className="mes-label">类型</label>
                            <select
                                value={filters.type ?? ''}
                                onChange={(event) => apply({ type: event.target.value })}
                                className="mes-select w-[130px]"
                            >
                                {TYPES.map((type) => <option key={type.key} value={type.key}>{type.label}</option>)}
                            </select>

                            <span className="mes-filter-divider" />

                            <form
                                onSubmit={(event) => { event.preventDefault(); apply({ q }); }}
                                className="flex min-w-[280px] flex-1 items-center gap-2"
                            >
                                <input
                                    ref={searchRef}
                                    value={q}
                                    onChange={(event) => setQ(event.target.value)}
                                    placeholder="物料名称 / 编码 / 仓库"
                                    className="mes-input flex-1"
                                />
                                <button type="submit" className="mes-btn mes-btn-primary">查询</button>
                                <button type="button" onClick={clear} className="mes-btn">重置</button>
                            </form>

                            <div className="push flex items-center gap-2 text-[11px] text-[var(--mes-faint)]">
                                共 <b className="font-mono text-[var(--mes-ink)]">{filteredRows.length}</b> 行
                            </div>
                        </div>
                    </div>

                    <MesSection
                        title="库存明细"
                        meta="勾选行可批量导出；点击行末“详情”查看批次"
                        actions={selected.length > 0 ? (
                            <>
                                <span className="text-[11px] text-[var(--mes-muted)]">已选 <b className="font-mono text-[var(--mes-blue)]">{selected.length}</b> 项</span>
                                <button type="button" className="mes-btn" onClick={() => exportRows(true)}>导出选中</button>
                                <button type="button" className="mes-btn mes-btn-soft" onClick={() => setSelected([])}>取消选择</button>
                            </>
                        ) : (
                            <span className="text-[10px] text-[var(--mes-faint)]">共 {summary.total ?? 0} 个库存项</span>
                        )}
                    >
                        <div className="mes-tabs">
                            {STATUS_TABS.map((tab) => (
                                <button
                                    key={tab.key || 'all'}
                                    type="button"
                                    className="mes-tab"
                                    data-active={status === tab.key}
                                    onClick={() => setStatus(tab.key)}
                                >
                                    {tab.label}<span className="count">{statusCounts[tab.key] ?? 0}</span>
                                </button>
                            ))}
                        </div>

                        {filteredRows.length === 0 ? (
                            <div className="mes-empty">
                                <strong>{hasFilter ? '没有符合筛选的库存项' : '仓库里还没有库存余额'}</strong>
                                {hasFilter ? '调整仓库、类型或状态后重试。' : '收料、入库或生产入库过账后，库存余额会出现在这里。'}
                                {hasFilter && <div className="mt-3"><button type="button" onClick={clear} className="mes-btn">清除筛选</button></div>}
                            </div>
                        ) : (
                            <>
                                <div className="mes-table-wrap">
                                    <table className="mes-table min-w-[1010px]">
                                        <thead>
                                            <tr>
                                                <th className="w-[36px]"><input type="checkbox" className="mes-check" checked={allVisibleSelected} onChange={toggleVisible} aria-label="全选当前页" /></th>
                                                <th className="w-[82px]">状态</th>
                                                <th className="w-[150px]">物料编码</th>
                                                <th className="w-[220px]">物料 / 成品名称</th>
                                                <th className="w-[70px]">类型</th>
                                                <th className="w-[116px]">仓库</th>
                                                <th className="w-[96px]">库位</th>
                                                <th className="w-[108px] num">数量</th>
                                                <th className="w-[138px]">批次</th>
                                                <th className="w-[66px]">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {pager.visible.map((row) => (
                                                <StockRow
                                                    key={row.id}
                                                    row={row}
                                                    checked={selected.includes(row.id)}
                                                    onCheck={() => toggleRow(row.id)}
                                                    onOpen={() => setDetail(row)}
                                                />
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <MesPager page={pager.page} onPage={pager.setPage} total={filteredRows.length} pageSize={pager.pageSize} label="行" />
                            </>
                        )}
                    </MesSection>

                    <div className="mes-note">
                        口径说明：批次跟踪物料没有物料批时标记“缺批次”；已收货、待检或拒收的批次会保留在库，但在批次表里单独标出，不混入“可放行”数量。
                    </div>
                </div>
            </MesPage>

            {detail && <StockDrawer stock={detail} onClose={() => setDetail(null)} />}
        </>
    );
}

function StockRow({ row, checked, onCheck, onOpen }) {
    const meta = STATUS_META[row.status] ?? STATUS_META.OK;
    const statusText = row.status === 'HOLD' ? `冻结 ${row.held_lots} 批` : row.status_label;
    return (
        <tr className={row.status === 'HOLD' ? 'is-alert' : row.status === 'NOLOT' ? 'is-warn' : ''}>
            <td><input type="checkbox" className="mes-check" checked={checked} onChange={onCheck} aria-label={`选择 ${row.item_name}`} /></td>
            <td><MesTag tone={meta.tone}>{statusText}</MesTag></td>
            <td className="code">{row.item_code}</td>
            <td>
                <div className="truncate strong" title={row.item_name}>{row.item_name}</div>
                <div className="code mt-0.5">{row.tracking_label}</div>
            </td>
            <td className="muted">{row.item_type}</td>
            <td>
                <div className="font-mono text-[11px] font-bold">{row.warehouse_code}</div>
                <div className="code mt-0.5 truncate" title={row.warehouse_name}>{row.warehouse_name}</div>
            </td>
            <td>
                {row.location_summary
                    ? <span className="mes-tag mes-tag-blue">{row.location_summary}</span>
                    : <span className="text-[10px] text-[var(--mes-faint)]">未指定</span>}
            </td>
            <td className="num">
                <span className="text-[13px] font-bold">{number(row.quantity)}</span>
                <span className="ml-1 text-[10px] text-[var(--mes-muted)]">{unit(row.uom)}</span>
            </td>
            <td>
                <div className="text-[11px] font-semibold">{row.lot_summary}</div>
                <div className="code mt-0.5">
                    {row.lots_count > 0 ? `可用 ${number(row.lots_available)}` : row.next_expiry ? `至 ${row.next_expiry}` : '无批次记录'}
                </div>
            </td>
            <td><button type="button" onClick={onOpen} className="mes-link">详情</button></td>
        </tr>
    );
}

function StockDrawer({ stock, onClose }) {
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/45 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="stock-detail-title" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-[660px] overflow-y-auto rounded-t-[4px] border border-[var(--mes-line)] bg-[var(--mes-panel)] shadow-xl sm:rounded-[4px]" onClick={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-3 border-b border-[var(--mes-line)] px-4 py-3">
                    <div className="min-w-0">
                        <div className="mes-kicker">库存明细 / {stock.warehouse_name}</div>
                        <h2 id="stock-detail-title" className="mt-1 truncate text-[15px] font-bold text-[var(--mes-ink)]">{stock.item_name}</h2>
                        <div className="code mt-0.5">{stock.item_code} · {stock.item_type}</div>
                    </div>
                    <div className="flex items-center gap-2">
                        <MesTag tone={(STATUS_META[stock.status] ?? STATUS_META.OK).tone}>{stock.status_label}</MesTag>
                        <button type="button" onClick={onClose} className="mes-btn">关闭</button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-px border-b border-[var(--mes-line)] bg-[var(--mes-line-soft)] sm:grid-cols-4">
                    {[
                        ['库存数量', `${number(stock.quantity)} ${unit(stock.uom)}`],
                        ['仓库', `${stock.warehouse_code} · ${stock.warehouse_name}`],
                        ['库位', stock.location_summary ?? '未指定'],
                        ['批次规则', stock.tracking_label],
                    ].map(([label, value]) => (
                        <div key={label} className="bg-[var(--mes-panel)] px-4 py-3">
                            <div className="text-[10px] text-[var(--mes-faint)]">{label}</div>
                            <div className="mt-1 truncate text-[12px] font-bold text-[var(--mes-ink)]" title={value}>{value}</div>
                        </div>
                    ))}
                </div>

                <div className="px-4 py-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-[12px] font-bold text-[var(--mes-ink)]">{stock.kind === 'product' ? '库存明细' : '在库批次'}</h3>
                        <span className="text-[10px] text-[var(--mes-faint)]">
                            {stock.lots_count > 0 ? `批次可用合计 ${number(stock.lots_available)} ${unit(stock.uom)}` : '没有批次记录'}
                        </span>
                    </div>

                    {stock.lots.length === 0 ? (
                        <div className="mt-3 border border-[var(--mes-line)] bg-[var(--mes-panel-soft)] px-3 py-4 text-[11px] leading-5 text-[var(--mes-muted)]">
                            {stock.kind === 'product'
                                ? '成品按汇总数量管理，当前不跟踪批次。'
                                : stock.tracking === 'batch'
                                    ? '该物料要求批次跟踪，但当前库存余额没有对应的物料批。先补收料/检验批次，再按批次发料。'
                                    : '该物料不要求批次跟踪，当前只维护汇总数量。'}
                        </div>
                    ) : (
                        <div className="mt-2 overflow-hidden border border-[var(--mes-line)]">
                            <table className="mes-table min-w-[560px]">
                                <thead>
                                    <tr>
                                        <th>批次号</th>
                                        <th>状态</th>
                                        <th>库位</th>
                                        <th className="num">可用 / 收货</th>
                                        <th>有效期</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stock.lots.map((lot) => (
                                        <tr key={lot.id}>
                                            <td className="code">{lot.lot_number}</td>
                                            <td><LotTag lot={lot} /></td>
                                            <td className="code">{lot.location_code ?? '—'}</td>
                                            <td className="num">{number(lot.available)} / {number(lot.received)}</td>
                                            <td className="code">{lot.expiry ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-between gap-2 border-t border-[var(--mes-line)] bg-[var(--mes-panel-soft)] px-4 py-3">
                    <span className="text-[10px] text-[var(--mes-faint)]">库存数量不在本页调整；批次请到物料批台账处理。</span>
                    <div className="flex items-center gap-2">
                        {stock.lots_url && <Link href={stock.lots_url} className="mes-link">物料批台账</Link>}
                        <button type="button" onClick={onClose} className="mes-btn">关闭</button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function LotTag({ lot }) {
    const tone = lot.status === 'rejected' || lot.status === 'expired' ? 'red' : lot.status === 'quarantine' ? 'orange' : lot.status === 'released' ? 'green' : 'gray';
    return <MesTag tone={tone}>{lot.status_label}</MesTag>;
}

function csvCell(value) {
    const text = String(value ?? '');
    return /[",\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
}

WarehouseStock.layout = (page) => <AppLayout>{page}</AppLayout>;
