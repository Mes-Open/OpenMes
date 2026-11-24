import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import { Icon } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime } from '../../../lib/i18n';

const STATUS = {
    LOCKED: { label: '已锁定', className: 'bg-om-blocked-bg text-om-blocked', icon: 'lock', tone: 'text-om-blocked' },
    OVERDUE: { label: '已过期', className: 'bg-om-blocked-bg text-om-blocked', icon: 'alert-triangle', tone: 'text-om-blocked' },
    DUE_SOON: { label: '即将到期', className: 'bg-om-downtime-bg text-om-downtime', icon: 'clock', tone: 'text-om-downtime' },
    ACTIVE: { label: '正常', className: 'bg-om-running-bg text-om-running', icon: 'check', tone: 'text-om-running' },
};

const FILTERS = [
    { key: '', label: '全部' },
    { key: 'locked', label: '锁定' },
    { key: 'due_soon', label: '7 天内到期' },
    { key: 'active', label: '正常' },
];

export default function CalibrationIndex() {
    const { calibrations, filters = {}, summary = {} } = usePage().props;
    const rows = calibrations?.data ?? [];
    const [q, setQ] = useState(filters.q ?? '');
    const hasFilter = Boolean(filters.q || filters.status);

    const apply = (patch) => {
        const next = { ...filters, ...patch };
        const params = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined));
        router.get('/admin/calibrations', params, { preserveState: true, replace: true });
    };

    const submitSearch = (event) => {
        event.preventDefault();
        apply({ q });
    };

    const countFor = (key) => {
        if (key === '') return summary.total ?? 0;
        if (key === 'locked') return summary.locked ?? 0;
        if (key === 'due_soon') return summary.due_soon ?? 0;
        return summary.active ?? 0;
    };

    return (
        <>
            <Head title="设备校准" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">设备校准</h1>
                        <p className="mt-1 max-w-[700px] text-sm leading-6 text-om-muted">
                            到期即锁。先看哪台秤或仪器已经不能用于判定，再看证书和剩余天数；解锁要完成校准并留下证书。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            共 {summary.total ?? rows.length} 台设备 · 锁定 {summary.locked ?? 0} · 7 天内到期 {summary.due_soon ?? 0} · 正常 {summary.active ?? 0}
                        </p>
                    </div>
                    <Link href="/admin/maintenance-schedules" className="btn-touch btn-secondary text-sm">维护计划</Link>
                </div>

                <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="锁定中" value={summary.locked ?? 0} sub="含已过期与手动锁定" tone={(summary.locked ?? 0) > 0 ? 'text-om-blocked' : 'text-om-ink'} icon="lock" />
                    <Metric label="已过期" value={summary.overdue ?? 0} sub="到期未校准，称重被拦截" tone={(summary.overdue ?? 0) > 0 ? 'text-om-blocked' : 'text-om-ink'} icon="alert-triangle" />
                    <Metric label="7 天内到期" value={summary.due_soon ?? 0} sub="需要安排校准窗口" tone={(summary.due_soon ?? 0) > 0 ? 'text-om-downtime' : 'text-om-ink'} icon="clock" />
                    <Metric label="正常" value={summary.active ?? 0} sub="到期日在 7 天之外" tone="text-om-running" icon="check" />
                </div>

                <div className="rounded-om-sm border border-om-line bg-om-chip/40 px-4 py-3 text-xs leading-5 text-om-muted">
                    锁定秤在批次控制台登记称重时会被直接拒绝；这里只负责暴露状态和解锁条件，校准动作仍在维护计划中完成。
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {FILTERS.map((item) => {
                        const active = (filters.status ?? '') === item.key;
                        return (
                            <button
                                key={item.key || 'all'}
                                type="button"
                                onClick={() => apply({ status: item.key })}
                                className={`btn-touch text-xs ${active ? 'bg-om-accent text-white hover:bg-om-accent/90' : 'bg-om-chip text-om-muted hover:text-om-ink'}`}
                            >
                                {item.label} <span className={`ml-1 font-mono ${active ? 'text-white/80' : 'text-om-faint'}`}>{countFor(item.key)}</span>
                            </button>
                        );
                    })}
                    {hasFilter && (
                        <button type="button" onClick={() => { setQ(''); router.get('/admin/calibrations'); }} className="text-xs font-bold text-om-muted hover:text-om-ink">
                            清除筛选
                        </button>
                    )}
                </div>

                <form onSubmit={submitSearch} className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="搜设备编码、名称、类型、位置"
                        className="h-10 min-w-0 rounded-om-sm border border-om-line bg-om-card px-3 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-2 focus:ring-om-accent/20"
                    />
                    <button type="submit" className="btn-touch btn-secondary text-sm">搜索</button>
                </form>

                <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                        <h2 className="text-sm font-bold text-om-ink">校准台账</h2>
                        <span className="text-xs text-om-faint">锁定与过期优先，其次按到期日</span>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-4 py-12 text-center">
                            <p className="text-sm font-bold text-om-ink">{hasFilter ? '没有符合筛选的校准记录。' : '还没有校准记录。'}</p>
                            <p className="mx-auto mt-2 max-w-[520px] text-xs leading-5 text-om-muted">
                                {hasFilter ? '清除筛选后再看全部设备。' : '先登记一台秤或温度计，再到批次控制台登记称重；没有校准记录的设备不会自动获得“正常”结论。'}
                            </p>
                            <div className="mt-4 flex flex-wrap justify-center gap-2">
                                {hasFilter && <button type="button" onClick={() => { setQ(''); router.get('/admin/calibrations'); }} className="btn-touch btn-secondary text-xs">清除筛选</button>}
                                <Link href="/admin/maintenance-schedules" className="btn-touch btn-secondary text-xs">打开维护计划</Link>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="hidden lg:block">
                                <DesktopTable rows={rows} />
                            </div>
                            <div className="divide-y divide-om-line2 lg:hidden">
                                {rows.map((row) => <MobileRow key={row.id} row={row} />)}
                            </div>
                        </>
                    )}

                    {calibrations?.links?.length > 3 && (
                        <div className="flex flex-wrap gap-1 border-t border-om-line2 px-4 py-3">
                            {calibrations.links.map((link, index) => (
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        className={`rounded px-3 py-1.5 text-xs font-medium ${link.active ? 'bg-om-accent text-white' : 'bg-om-chip text-om-muted hover:text-om-ink'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={index} className="rounded px-3 py-1.5 text-xs text-om-faint" dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
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
            <div className="mt-0.5 text-[11px] text-om-muted">{sub}</div>
        </div>
    );
}

function DesktopTable({ rows }) {
    return (
        <table className="w-full table-fixed text-sm">
            <thead>
                <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-[11px] font-bold tracking-wide text-om-muted">
                    <th className="w-[24%] px-4 py-2.5">设备编码 / 名称</th>
                    <th className="w-[14%] px-2 py-2.5">类型 / 位置</th>
                    <th className="w-[12%] px-2 py-2.5">上次校准</th>
                    <th className="w-[14%] px-2 py-2.5">下次到期</th>
                    <th className="w-[12%] px-2 py-2.5">证书</th>
                    <th className="w-[24%] px-4 py-2.5">状态 / 解锁条件</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id} className={`border-b border-om-line2 last:border-0 hover:bg-om-chip/20 ${row.is_locked ? 'bg-om-blocked-bg/5' : ''}`}>
                        <td className="px-4 py-3">
                            <div className="font-mono text-sm font-bold text-om-ink">{row.equipment_code}</div>
                            <div className="mt-0.5 truncate text-sm font-semibold text-om-ink">{row.equipment_name}</div>
                        </td>
                        <td className="px-2 py-3">
                            <div className="text-xs font-medium text-om-ink">{row.equipment_kind}</div>
                            <div className="mt-0.5 truncate text-[11px] text-om-muted">{row.location ?? '—'}</div>
                        </td>
                        <td className="px-2 py-3">
                            <div className="text-xs text-om-ink">{row.last_calibrated_at ? formatDateTime(row.last_calibrated_at) : '未校准'}</div>
                            <div className={`mt-0.5 text-[10px] font-bold ${row.last_result === 'FAIL' ? 'text-om-blocked' : 'text-om-faint'}`}>
                                {row.last_result === 'FAIL' ? '上次不合格' : row.last_result === 'PASS' ? '上次合格' : '无结果'}
                            </div>
                        </td>
                        <td className="px-2 py-3">
                            <div className="text-xs text-om-ink">{row.next_due_at ? formatDateTime(row.next_due_at) : '未设到期'}</div>
                            <Remaining row={row} />
                        </td>
                        <td className="px-2 py-3">
                            <div className="break-all font-mono text-[11px] text-om-ink">{row.certificate_no ?? '缺失'}</div>
                            <div className="mt-0.5 text-[10px] text-om-faint">{row.responsible_name ?? '未指定负责人'}</div>
                        </td>
                        <td className="px-4 py-3">
                            <StatusPill status={row.status} />
                            <div className="mt-1.5 line-clamp-2 text-[11px] leading-4 text-om-muted" title={row.lock_reason ?? ''}>
                            {row.is_locked ? (row.lock_reason || '已锁定，不能用于称重。') : '证书与到期日正常。'}
                            </div>
                            {row.is_locked && (
                                <div className="mt-1 line-clamp-2 text-[10px] leading-4 text-om-faint" title={row.unlock_condition}>
                                    解锁：{row.unlock_condition}
                                </div>
                            )}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function MobileRow({ row }) {
    return (
        <div className={`px-4 py-3 ${row.is_locked ? 'bg-om-blocked-bg/5' : ''}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="font-mono text-sm font-bold text-om-ink">{row.equipment_code}</div>
                    <div className="mt-0.5 truncate text-sm font-semibold text-om-ink">{row.equipment_name}</div>
                </div>
                <StatusPill status={row.status} />
            </div>
            <div className="mt-1 truncate text-[11px] text-om-muted">{row.equipment_kind} · {row.location ?? '未填位置'} · {row.responsible_name ?? '未指定负责人'}</div>
            <div className="mt-2 grid grid-cols-3 gap-2">
                <MiniMetric label="上次校准" value={row.last_calibrated_at ? formatDateTime(row.last_calibrated_at) : '未校准'} />
                <MiniMetric label="下次到期" value={row.next_due_at ? formatDateTime(row.next_due_at) : '未设到期'} />
                <MiniMetric label="证书" value={row.certificate_no ?? '缺失'} mono />
            </div>
            <div className="mt-2 flex items-center justify-between gap-2">
                <Remaining row={row} />
                <span className={`text-[10px] font-bold ${row.last_result === 'FAIL' ? 'text-om-blocked' : 'text-om-faint'}`}>
                    {row.last_result === 'FAIL' ? '上次不合格' : row.last_result === 'PASS' ? '上次合格' : '无结果'}
                </span>
            </div>
            {row.is_locked && (
                <div className="mt-2 rounded-om-sm bg-om-blocked-bg/60 px-3 py-2 text-[11px] leading-4 text-om-blocked">
                    <div className="font-bold">不能称重：{row.lock_reason}</div>
                    <div className="mt-1 text-om-muted">解锁：{row.unlock_condition}</div>
                </div>
            )}
        </div>
    );
}

function Remaining({ row }) {
    if (row.status === 'LOCKED') {
        return <div className="mt-0.5 text-[10px] font-bold text-om-blocked">手动锁定</div>;
    }
    if (row.days_left === null) {
        return <div className="mt-0.5 text-[10px] font-bold text-om-faint">未设到期日</div>;
    }
    const overdue = row.days_left < 0;
    const soon = row.days_left >= 0 && row.days_left <= 7;
    return (
        <div className={`mt-0.5 font-mono text-[10px] font-bold ${overdue ? 'text-om-blocked' : soon ? 'text-om-downtime' : 'text-om-running'}`}>
            {overdue ? `已过期 ${Math.abs(row.days_left)} 天` : row.days_left === 0 ? '今天到期' : `剩 ${row.days_left} 天`}
        </div>
    );
}

function StatusPill({ status }) {
    const meta = STATUS[status] ?? STATUS.ACTIVE;
    return (
        <span className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-bold ${meta.className}`}>
            <Icon name={meta.icon} size={11} />
            {meta.label}
        </span>
    );
}

function MiniMetric({ label, value, mono = false }) {
    return (
        <div className="min-w-0 rounded border border-om-line2 bg-om-bg px-2 py-1.5">
            <div className="text-[10px] font-bold text-om-faint">{label}</div>
            <div className={`mt-0.5 break-all text-[11px] text-om-ink ${mono ? 'font-mono' : ''}`}>{value}</div>
        </div>
    );
}
