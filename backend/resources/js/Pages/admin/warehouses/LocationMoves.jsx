import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { MesHeader, MesPage, MesSection, MesStats, MesTag } from '../../../Components/mes/MesUI';

const TYPE = {
    material_lot: { label: '物料批', tone: 'blue' },
    material_stock: { label: '物料库存', tone: 'gray' },
    product_stock: { label: '成品库存', tone: 'green' },
};

const number = (value) => new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 3 }).format(Number(value || 0));

const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false,
    }).format(date).replaceAll('/', '-');
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

    const exportHref = `/admin/warehouses/locations/moves/export${Object.keys(cleanParams(filters)).length ? `?${new URLSearchParams(cleanParams(filters)).toString()}` : ''}`;

    return (
        <>
            <Head title="仓储 · 移库记录" />
            <MesPage>
                <MesHeader
                    breadcrumb={['仓储管理', '库位管理', '移库记录']}
                    title="移库记录"
                    tag={{ label: '审计留痕', tone: 'gray' }}
                    description="记录物料批、物料库存和成品库存的整项库位调整。只改物理位置，不拆数量、不改库存余额。"
                    meta={`当前筛选 ${summary.total ?? 0} 条 · 按移库时间倒序`}
                    actions={(
                        <>
                            <Link href="/admin/warehouses/locations" className="mes-btn">库位管理</Link>
                            <a href={exportHref} className="mes-btn mes-btn-primary">导出 CSV</a>
                        </>
                    )}
                />

                <div className="space-y-3">
                    <MesStats
                        items={[
                            { label: '记录总数', value: summary.total ?? 0, sub: '当前筛选范围', tone: 'var(--mes-blue)' },
                            { label: '物料移库', value: (summary.material_lot ?? 0) + (summary.material_stock ?? 0), sub: `物料批 ${summary.material_lot ?? 0} · 库存行 ${summary.material_stock ?? 0}`, tone: 'var(--mes-ink)' },
                            { label: '成品移库', value: summary.product_stock ?? 0, sub: '按成品汇总行移动', tone: 'var(--mes-ink)' },
                            { label: '操作人', value: summary.operators ?? 0, sub: `最近 ${summary.last_moved_at ? formatDate(summary.last_moved_at) : '—'}`, tone: 'var(--mes-green)' },
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
                                <option value="">全部类型</option>
                                <option value="material_lot">物料批</option>
                                <option value="material_stock">物料库存</option>
                                <option value="product_stock">成品库存</option>
                            </select>

                            <span className="mes-filter-divider" />

                            <form
                                onSubmit={(event) => { event.preventDefault(); apply({ q }); }}
                                className="flex min-w-[280px] flex-1 items-center gap-2"
                            >
                                <input
                                    value={q}
                                    onChange={(event) => setQ(event.target.value)}
                                    placeholder="物料 / 库位编码 / 备注 / 操作人"
                                    className="mes-input flex-1"
                                />
                                <button type="submit" className="mes-btn mes-btn-primary">查询</button>
                                <button type="button" onClick={clear} className="mes-btn">重置</button>
                            </form>

                            <div className="push text-[11px] text-[var(--mes-faint)]">
                                共 <b className="font-mono text-[var(--mes-ink)]">{moves.total ?? 0}</b> 条
                            </div>
                        </div>
                    </div>

                    <MesSection
                        title="移库明细"
                        meta="同一对象可以多次往返；库位编码为只读凭证"
                        footer={moves.last_page > 1 ? <Pagination moves={moves} /> : (
                            <div className="mes-footer">
                                <span>共 <b className="font-mono text-[var(--mes-ink)]">{moves.total ?? 0}</b> 条</span>
                                <span className="text-[var(--mes-faint)]">当前 {moves.from ?? 0}-{moves.to ?? 0}</span>
                            </div>
                        )}
                    >
                        {rows.length === 0 ? (
                            <div className="mes-empty">
                                <strong>{hasFilter ? '没有符合筛选的移库记录' : '还没有移库记录'}</strong>
                                {hasFilter ? '清除筛选后再查看全部记录。' : '从库位管理调整物料批或成品库存的库位后，这里会留下记录。'}
                                {hasFilter && <div className="mt-3"><button type="button" onClick={clear} className="mes-btn">清除筛选</button></div>}
                            </div>
                        ) : (
                            <div className="mes-table-wrap">
                                <table className="mes-table min-w-[980px]">
                                    <thead>
                                        <tr>
                                            <th className="w-[128px]">移库时间</th>
                                            <th className="w-[80px]">类型</th>
                                            <th className="w-[220px]">对象</th>
                                            <th className="w-[100px] num">数量</th>
                                            <th className="w-[176px]">移出 → 移入</th>
                                            <th className="w-[90px]">操作人</th>
                                            <th>备注</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((move) => (
                                            <tr key={move.id}>
                                                <td className="code">{formatDate(move.moved_at)}</td>
                                                <td><MesTag tone={(TYPE[move.item_type] ?? TYPE.material_lot).tone}>{(TYPE[move.item_type] ?? TYPE.material_lot).label}</MesTag></td>
                                                <td>
                                                    <div className="truncate strong" title={move.item_name}>{move.item_name}</div>
                                                    <div className="code mt-0.5">{move.item_code}</div>
                                                </td>
                                                <td className="num">
                                                    <span className="text-[13px] font-bold">{number(move.quantity)}</span>
                                                    <span className="ml-1 text-[10px] text-[var(--mes-muted)]">{move.unit_of_measure}</span>
                                                </td>
                                                <td>
                                                    <div className="flex flex-wrap items-center gap-1">
                                                        <LocationPill location={move.from_location} />
                                                        <span className="text-[var(--mes-blue)]">→</span>
                                                        <LocationPill location={move.to_location} />
                                                    </div>
                                                </td>
                                                <td className="muted">{move.moved_by?.name ?? '系统'}</td>
                                                <td className="truncate muted" title={move.note ?? ''}>{move.note ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </MesSection>

                    <div className="mes-note">
                        审计口径：库位记录与库存台账分开。这里追溯“谁把什么从哪儿移到哪儿”，库存数量仍以仓库余额为准。
                    </div>
                </div>
            </MesPage>
        </>
    );
}

function LocationPill({ location }) {
    return (
        <span className="mes-tag mes-tag-gray font-mono" title={location?.name ?? ''}>
            {location?.code ?? '—'}
        </span>
    );
}

function Pagination({ moves }) {
    const links = (moves.links ?? []).filter((link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;');
    return (
        <div className="mes-footer">
            <span>共 <b className="font-mono text-[var(--mes-ink)]">{moves.total ?? 0}</b> 条 · 当前 {moves.from ?? 0}-{moves.to ?? 0}</span>
            <div className="mes-pager">
                {links.map((link, index) => (
                    link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll
                            data-active={link.active}
                        >
                            {link.label}
                        </Link>
                    ) : (
                        <span key={`${link.label}-${index}`} className="px-1 text-[var(--mes-faint)]">{link.label}</span>
                    )
                ))}
            </div>
        </div>
    );
}

LocationMoves.layout = (page) => <AppLayout>{page}</AppLayout>;
