import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { MesHeader, MesPage, MesSection, MesStats, MesTag } from '../../../Components/mes/MesUI';

const STOCK_STATUS = {
    OK: { label: '已对平', tone: 'green' },
    UNLOCATED: { label: '未指定库位', tone: 'orange' },
    PARTIAL: { label: '部分定位', tone: 'orange' },
    OVER: { label: '超出账面', tone: 'red' },
};

const KIND = {
    material: { label: '物料批', tone: 'blue' },
    product: { label: '成品', tone: 'green' },
    stock: { label: '物料', tone: 'gray' },
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
    const exportHref = `/admin/warehouses/locations/reconcile?export=1${activeWarehouse ? `&warehouse=${activeWarehouse}` : ''}`;

    const applyWarehouse = (id) => {
        if (id) {
            router.get('/admin/warehouses/locations/reconcile', { warehouse: id }, { preserveState: true, replace: true });
        } else {
            router.get('/admin/warehouses/locations/reconcile', {}, { preserveState: true, replace: true });
        }
    };

    const gapCount = (summary.unlocated_lines ?? 0) + (summary.variance_lines ?? 0);
    const locationCount = useMemo(
        () => warehouses.reduce((sum, warehouse) => sum + warehouse.locations.length, 0),
        [warehouses],
    );

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
            <MesPage>
                <MesHeader
                    breadcrumb={['仓储管理', '库位管理', '库位对账']}
                    title="库位对账"
                    tag={{ label: '只核对，不改库存', tone: 'gray' }}
                    description="用账面库存行对照库位占用，定位“有账无位”和“有位无账”。本页只补库位、不拆数量、不改库存余额。"
                    meta={`${warehouses.length} 个仓库 · ${summary.located_items ?? 0} 项库位占用`}
                    actions={(
                        <>
                            <Link href="/admin/warehouses/locations" className="mes-btn">库位管理</Link>
                            <Link href="/admin/warehouses/stock" className="mes-btn">库存台账</Link>
                            <a href={exportHref} className="mes-btn mes-btn-primary">导出 CSV</a>
                        </>
                    )}
                />

                <div className="space-y-3">
                    <MesStats
                        items={[
                            { label: '库存行已对平', value: `${summary.located_lines ?? 0} / ${summary.stock_lines ?? 0}`, sub: '库位合计与账面一致', tone: 'var(--mes-green)' },
                            { label: '未指定库位', value: summary.unlocated_lines ?? 0, sub: '账面有量，未挂库位', tone: (summary.unlocated_lines ?? 0) > 0 ? 'var(--mes-orange)' : 'var(--mes-green)' },
                            { label: '数量差异', value: summary.variance_lines ?? 0, sub: '部分定位或超出账面', tone: (summary.variance_lines ?? 0) > 0 ? 'var(--mes-red)' : 'var(--mes-green)' },
                            { label: '冻结库位', value: summary.frozen_bins ?? 0, sub: '有拒收或待检批次', tone: (summary.frozen_bins ?? 0) > 0 ? 'var(--mes-red)' : 'var(--mes-ink)' },
                        ]}
                    />

                    <div className="mes-panel">
                        <div className="mes-filterbar">
                            <label className="mes-label">仓库</label>
                            <select
                                value={activeWarehouse}
                                onChange={(event) => applyWarehouse(event.target.value)}
                                className="mes-select w-[180px]"
                            >
                                <option value="">全部仓库</option>
                                {warehouses.map((warehouse) => (
                                    <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                                ))}
                            </select>

                            <span className="mes-filter-divider" />

                            <div className="flex items-center gap-1">
                                <button type="button" className="mes-btn" data-active={view === 'location'} style={view === 'location' ? { borderColor: 'var(--mes-blue)', color: 'var(--mes-blue)', background: 'var(--mes-blue-soft)' } : undefined} onClick={() => setView('location')}>按库位</button>
                                <button type="button" className="mes-btn" style={view === 'stock' ? { borderColor: 'var(--mes-blue)', color: 'var(--mes-blue)', background: 'var(--mes-blue-soft)' } : undefined} onClick={() => setView('stock')}>按库存行</button>
                            </div>

                            {view === 'stock' && (
                                <>
                                    <span className="mes-filter-divider" />
                                    <button
                                        type="button"
                                        onClick={() => setOnlyGap((value) => !value)}
                                        className={`mes-btn ${onlyGap ? 'mes-btn-primary' : ''}`}
                                    >
                                        {onlyGap ? '显示全部' : `只看未对平（${gapCount}）`}
                                    </button>
                                </>
                            )}

                            <div className="push text-[11px] text-[var(--mes-faint)]">
                                库位 <b className="font-mono text-[var(--mes-ink)]">{locationCount}</b> 个 · 未对平 <b className="font-mono text-[var(--mes-orange)]">{gapCount}</b> 行
                            </div>
                        </div>
                    </div>

                    {view === 'location' ? (
                        <LocationView warehouses={warehouses} onAssign={openAssign} />
                    ) : (
                        <StockView warehouses={warehouses} onlyGap={onlyGap} onAssign={openAssign} />
                    )}

                    <div className="mes-note">
                        对账口径：物料批按可用量计入库位，拒收批可用量为 0 但仍占库位可见；成品按成品仓汇总行计入。未指定库位可从本页指定；不拆数量、不改库存余额。
                    </div>
                </div>
            </MesPage>

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

function LocationView({ warehouses, onAssign }) {
    if (warehouses.length === 0) {
        return <div className="mes-panel mes-empty"><strong>当前没有可对账的仓库</strong>请先启用仓库并建立库位。</div>;
    }
    return (
        <div className="space-y-3">
            {warehouses.map((warehouse) => (
                <WarehouseLocationTable key={warehouse.id} warehouse={warehouse} onAssign={onAssign} />
            ))}
        </div>
    );
}

function WarehouseLocationTable({ warehouse, onAssign }) {
    const [expanded, setExpanded] = useState({});
    const toggle = (id) => setExpanded((current) => ({ ...current, [id]: !current[id] }));

    return (
        <MesSection
            title={warehouse.name}
            meta={`${warehouse.code} · ${warehouse.locations.length} 个库位 · ${warehouse.located_count} / ${warehouse.stock_count} 行已对平`}
            actions={warehouse.unlocated_count > 0 ? <MesTag tone="orange">{warehouse.unlocated_count} 行未指定库位</MesTag> : <MesTag tone="green">全部已指定</MesTag>}
        >
            <div className="mes-table-wrap">
                <table className="mes-table min-w-[980px]">
                    <thead>
                        <tr>
                            <th className="w-[120px]">库位编码</th>
                            <th className="w-[98px]">库区 / 类型</th>
                            <th className="w-[82px]">状态</th>
                            <th>占用项</th>
                            <th className="w-[140px] num">库位合计</th>
                            <th className="w-[86px] num">冻结 / 批况</th>
                            <th className="w-[80px]">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        {warehouse.locations.map((location) => {
                            const open = Boolean(expanded[location.id]);
                            return (
                                <React.Fragment key={location.id}>
                                    <tr className={location.held_count > 0 ? 'is-alert' : ''}>
                                        <td>
                                            {location.item_count > 0 ? (
                                                <button type="button" className="mes-tree-toggle" onClick={() => toggle(location.id)}>
                                                    <span className="chev" style={{ transform: open ? 'rotate(90deg)' : 'none' }}>▶</span>
                                                    <span className="font-mono">{location.code}</span>
                                                </button>
                                            ) : <span className="font-mono font-semibold text-[var(--mes-muted)]">{location.code}</span>}
                                            <div className="code mt-0.5 truncate" title={location.name}>{location.name}</div>
                                        </td>
                                        <td className="muted">{location.zone_name}</td>
                                        <td>
                                            {location.held_count > 0
                                                ? <MesTag tone="red">冻结</MesTag>
                                                : location.item_count > 0
                                                    ? <MesTag tone="green">有料</MesTag>
                                                    : <MesTag tone="gray">空库位</MesTag>}
                                        </td>
                                        <td><span className="font-mono font-bold">{location.item_count ?? 0}</span> <span className="muted">项</span></td>
                                        <td className="num">{unitsText(location.units) || '0'}</td>
                                        <td className="num">{location.held_count > 0 ? <span className="text-[var(--mes-red)]">{location.held_count}</span> : '—'}</td>
                                        <td>{location.item_count > 0 ? <button type="button" className="mes-link" onClick={() => toggle(location.id)}>{open ? '收起' : '展开'}</button> : <span className="text-[10px] text-[var(--mes-faint)]">—</span>}</td>
                                    </tr>
                                    {open && location.items.map((item) => (
                                        <tr key={item.key} className="mes-subrow">
                                            <td><span className="pl-5 text-[var(--mes-faint)]">└</span></td>
                                            <td><KindBadge kind={item.kind} /></td>
                                            <td>{item.is_held ? <MesTag tone="red">冻结</MesTag> : item.kind === 'stock' ? <MesTag tone="orange">汇总</MesTag> : <MesTag tone="green">可用</MesTag>}</td>
                                            <td>
                                                <div className="truncate font-semibold text-[var(--mes-ink)]">{item.name}</div>
                                                <div className="code">{item.lot_number || item.code}</div>
                                            </td>
                                            <td className="num">{number(item.quantity)} <span className="text-[10px]">{item.uom}</span></td>
                                            <td className="num">{item.is_held ? 1 : '—'}</td>
                                            <td className="text-[10px]">{item.status_label}</td>
                                        </tr>
                                    ))}
                                </React.Fragment>
                            );
                        })}
                        {warehouse.unassigned.map((line) => (
                            <tr key={`unassigned-${line.id}`} className="is-warn">
                                <td><span className="font-mono font-bold text-[var(--mes-orange)]">未指定</span><div className="code mt-0.5">账面有量，未挂库位</div></td>
                                <td className="muted">—</td>
                                <td><MesTag tone="orange">待指定</MesTag></td>
                                <td>
                                    <div className="truncate font-semibold">{line.name}</div>
                                    <div className="code mt-0.5">{line.code} · 账面 {number(line.balance)} {line.uom}</div>
                                </td>
                                <td className="num text-[var(--mes-orange)]">{number(line.unlocated)} {line.uom}</td>
                                <td className="num">—</td>
                                <td>
                                    {line.lot_count === 0
                                        ? <button type="button" onClick={() => onAssign(line, warehouse)} className="mes-link">指定库位</button>
                                        : <span className="text-[10px] text-[var(--mes-faint)]">按批次调整</span>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </MesSection>
    );
}

function StockView({ warehouses, onlyGap, onAssign }) {
    const visible = (warehouse) => onlyGap
        ? warehouse.stock_lines.filter((line) => line.status !== 'OK')
        : warehouse.stock_lines;

    const total = warehouses.reduce((sum, warehouse) => sum + visible(warehouse).length, 0);

    return (
        <div className="space-y-3">
            {warehouses.map((warehouse) => {
                const lines = visible(warehouse);
                return (
                    <MesSection
                        key={warehouse.id}
                        title={warehouse.name}
                        meta={`${warehouse.code} · ${warehouse.stock_count} 行账面库存 · 对平 ${warehouse.located_count} · 未定位 ${warehouse.unlocated_count} · 差异 ${warehouse.variance_count}`}
                    >
                        {lines.length === 0 ? (
                            <div className="mes-empty"><strong>没有需要显示的库存行</strong>这个仓库当前筛选下全部已对平。</div>
                        ) : (
                            <div className="mes-table-wrap">
                                <table className="mes-table min-w-[1040px]">
                                    <thead>
                                        <tr>
                                            <th className="w-[80px]">类型</th>
                                            <th className="w-[230px]">库存项</th>
                                            <th className="w-[110px] num">账面</th>
                                            <th className="w-[110px] num">库位合计</th>
                                            <th className="w-[90px] num">差异</th>
                                            <th className="w-[160px]">库位</th>
                                            <th className="w-[102px]">状态</th>
                                            <th className="w-[72px]">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lines.map((line) => (
                                            <tr key={line.id} className={line.status === 'UNLOCATED' || line.status === 'PARTIAL' ? 'is-warn' : line.status === 'OVER' ? 'is-alert' : ''}>
                                                <td><KindBadge kind={line.kind === 'material' ? 'stock' : 'product'} /></td>
                                                <td>
                                                    <div className="truncate strong" title={line.name}>{line.name}</div>
                                                    <div className="code mt-0.5">{line.code}{line.lot_count > 1 ? ` · ${line.lot_count} 批` : ''}</div>
                                                </td>
                                                <td className="num">{number(line.balance)} <span className="text-[10px]">{line.uom}</span></td>
                                                <td className="num">{number(line.located)} <span className="text-[10px]">{line.uom}</span></td>
                                                <td className={`num font-bold ${line.variance !== 0 ? 'text-[var(--mes-orange)]' : 'text-[var(--mes-muted)]'}`}>
                                                    {line.located <= 0 ? '未定位' : `${line.variance > 0 ? '+' : ''}${number(line.variance)}`}
                                                </td>
                                                <td>
                                                    {line.location_codes.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {line.location_codes.map((code) => <span key={code} className="mes-tag mes-tag-blue font-mono">{code}</span>)}
                                                        </div>
                                                    ) : <span className="text-[10px] text-[var(--mes-faint)]">未指定</span>}
                                                </td>
                                                <td><StatusBadge status={line.status} /></td>
                                                <td>
                                                    {line.kind === 'material' && line.status === 'UNLOCATED' && line.lot_count === 0
                                                        ? <button type="button" onClick={() => onAssign(line, warehouse)} className="mes-link">指定库位</button>
                                                        : <span className="text-[10px] text-[var(--mes-faint)]">—</span>}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </MesSection>
                );
            })}
            <div className="mes-footer border border-[var(--mes-line)]">
                <span>按库存行 · 共 <b className="font-mono text-[var(--mes-ink)]">{total}</b> 行</span>
                <span className="text-[var(--mes-faint)]">未对平优先显示</span>
            </div>
        </div>
    );
}

function KindBadge({ kind }) {
    const meta = KIND[kind] ?? KIND.stock;
    return <MesTag tone={meta.tone}>{meta.label}</MesTag>;
}

function StatusBadge({ status }) {
    const meta = STOCK_STATUS[status] ?? STOCK_STATUS.OK;
    return <MesTag tone={meta.tone}>{meta.label}</MesTag>;
}

function AssignDialog({ assign, target, setTarget, note, setNote, errors, onClose, onSubmit }) {
    const { item, warehouse } = assign;
    const targets = warehouse.locations ?? [];
    const error = errors.to_location_id || errors.item_id || errors.note;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/45 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="assign-location-title" onClick={onClose}>
            <form onSubmit={onSubmit} className="max-h-[92vh] w-full max-w-[560px] overflow-y-auto rounded-t-[4px] border border-[var(--mes-line)] bg-[var(--mes-panel)] shadow-xl sm:rounded-[4px]" onClick={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-3 border-b border-[var(--mes-line)] px-4 py-3">
                    <div className="min-w-0">
                        <div className="mes-kicker">库位对账 / 指定库位</div>
                        <h2 id="assign-location-title" className="mt-1 truncate text-[15px] font-bold text-[var(--mes-ink)]">{item.name}</h2>
                        <div className="code mt-0.5">{item.code} · {warehouse.name}</div>
                    </div>
                    <button type="button" onClick={onClose} className="mes-btn">关闭</button>
                </div>

                <div className="grid grid-cols-2 gap-px border-b border-[var(--mes-line)] bg-[var(--mes-line-soft)]">
                    <div className="bg-[var(--mes-panel)] px-4 py-3">
                        <div className="text-[10px] text-[var(--mes-faint)]">当前库位</div>
                        <div className="mt-1 text-[12px] font-bold text-[var(--mes-orange)]">未指定</div>
                    </div>
                    <div className="bg-[var(--mes-panel)] px-4 py-3 text-right">
                        <div className="text-[10px] text-[var(--mes-faint)]">账面数量</div>
                        <div className="mt-1 font-mono text-[16px] font-bold text-[var(--mes-ink)]">{number(item.unlocated)} <span className="text-[10px] font-normal text-[var(--mes-muted)]">{item.uom}</span></div>
                    </div>
                </div>

                <div className="space-y-3 px-4 py-4">
                    <label className="block">
                        <span className="mb-1 block text-[11px] font-bold text-[var(--mes-muted)]">目标库位 <span className="text-[var(--mes-red)]">*</span></span>
                        <select value={target} onChange={(event) => setTarget(event.target.value)} className="mes-select h-[34px] w-full" required>
                            <option value="">选择同仓库位</option>
                            {targets.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.code} · {location.name}{location.item_count > 0 ? ` · 已有 ${location.item_count} 项` : ' · 空'}
                                </option>
                            ))}
                        </select>
                        {!targets.length && <div className="mt-1 text-[10px] text-[var(--mes-red)]">这个仓库还没有启用库位，请先在库位管理新增。</div>}
                    </label>

                    <label className="block">
                        <span className="mb-1 block text-[11px] font-bold text-[var(--mes-muted)]">备注（可选）</span>
                        <textarea
                            rows={2}
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            placeholder="例如：糖浆整桶上架到辅料位"
                            className="min-h-[68px] w-full resize-none rounded-[3px] border border-[var(--mes-line)] bg-[var(--mes-panel)] px-3 py-2 text-[12px] text-[var(--mes-ink)] outline-none placeholder:text-[var(--mes-faint)] focus:border-[var(--mes-blue)]"
                            maxLength={160}
                        />
                    </label>

                    {error && <div className="border border-[var(--mes-red)] bg-[var(--mes-red-soft)] px-3 py-2 text-[11px] text-[var(--mes-red)]">{error}</div>}

                    <div className="mes-note">
                        只给这一行账面库存指定库位，不拆数量、不改库存余额。已有批次的物料请回库位管理按批次调整。
                    </div>
                </div>

                <div className="flex items-center justify-between gap-2 border-t border-[var(--mes-line)] bg-[var(--mes-panel-soft)] px-4 py-3">
                    <span className="text-[10px] text-[var(--mes-faint)]">会追加一条库位记录。</span>
                    <div className="flex gap-2">
                        <button type="button" onClick={onClose} className="mes-btn">取消</button>
                        <button type="submit" disabled={!target} className="mes-btn mes-btn-primary disabled:cursor-not-allowed disabled:opacity-50">确认指定</button>
                    </div>
                </div>
            </form>
        </div>
    );
}

LocationReconcile.layout = (page) => <AppLayout>{page}</AppLayout>;
