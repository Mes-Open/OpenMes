import { Head, Link, useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime, formatNumber } from '../../../lib/i18n';

const TEA_BASE_STATUS = {
    PENDING: { label: '待检', className: 'bg-om-downtime-bg text-om-downtime' },
    AVAILABLE: { label: '可用', className: 'bg-om-running-bg text-om-running' },
    FROZEN: { label: '冻结', className: 'bg-om-blocked-bg text-om-blocked' },
    RETIRED: { label: '停用', className: 'bg-om-chip text-om-muted' },
};

const QUALITY_STATUS = {
    PASS: { label: '合格', className: 'bg-om-running-bg text-om-running' },
    FAIL: { label: '不合格', className: 'bg-om-blocked-bg text-om-blocked' },
    PENDING: { label: '待检', className: 'bg-om-downtime-bg text-om-downtime' },
};

const LOT_STATUS = {
    received: { label: '待检', className: 'bg-om-downtime-bg text-om-downtime' },
    released: { label: '已放行', className: 'bg-om-running-bg text-om-running' },
    quarantine: { label: '冻结', className: 'bg-om-blocked-bg text-om-blocked' },
    consumed: { label: '已耗尽', className: 'bg-om-chip text-om-muted' },
    expired: { label: '已过期', className: 'bg-om-chip text-om-muted' },
    rejected: { label: '拒收', className: 'bg-om-blocked-bg text-om-blocked' },
};

const INSPECTION_STATUS = {
    pass: { label: '合格', className: 'text-om-running' },
    fail: { label: '不合格', className: 'text-om-blocked' },
    conditional_pass: { label: '让步接收', className: 'text-om-downtime' },
    pending: { label: '待检', className: 'text-om-downtime' },
};

export default function TeaBaseShow() {
    const { teaBase, lots = [], available_lot_count = 0, can_blend = false } = usePage().props;
    const [freezeOpen, setFreezeOpen] = useState(false);
    const [unfreezeOpen, setUnfreezeOpen] = useState(false);
    const freezeForm = useForm({ reason: '' });
    const unfreezeForm = useForm({ reason: '' });

    const submitFreeze = (event) => {
        event.preventDefault();
        freezeForm.post(`/admin/tea-bases/${teaBase.id}/freeze`, {
            preserveScroll: true,
            onSuccess: () => {
                setFreezeOpen(false);
                freezeForm.reset();
            },
        });
    };

    const submitUnfreeze = (event) => {
        event.preventDefault();
        unfreezeForm.post(`/admin/tea-bases/${teaBase.id}/unfreeze`, {
            preserveScroll: true,
            onSuccess: () => {
                setUnfreezeOpen(false);
                unfreezeForm.reset();
            },
        });
    };

    return (
        <>
            <Head title={`${teaBase.code} — 茶底档案`} />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Link href="/admin/tea-bases" className="text-xs font-bold text-om-muted hover:text-om-accent">← 茶底档案</Link>
                    <div className="flex items-center gap-2">
                        <TeaBasePill status={teaBase.status} />
                        {teaBase.status === 'FROZEN' && (
                            <button type="button" onClick={() => { setUnfreezeOpen((open) => !open); setFreezeOpen(false); }} className="btn-touch btn-secondary text-xs">
                                {unfreezeOpen ? '收起' : '解除冻结'}
                            </button>
                        )}
                        {teaBase.status !== 'FROZEN' && teaBase.status !== 'RETIRED' && (
                            <button type="button" onClick={() => { setFreezeOpen((open) => !open); setUnfreezeOpen(false); }} className="btn-touch btn-danger text-xs">
                                {freezeOpen ? '收起' : '冻结茶底'}
                            </button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="font-mono text-2xl font-bold tracking-tight text-om-ink">{teaBase.code}</div>
                        <h1 className="mt-0.5 text-xl font-bold tracking-tight text-om-ink">{teaBase.name}</h1>
                        <p className="mt-1 text-sm text-om-muted">
                            {teaBase.origin ?? '产地未登记'} · {teaBase.variety ?? '品种未登记'} · {teaBase.grade ?? '等级未登记'} · {teaBase.season ?? '季节未登记'}
                        </p>
                    </div>
                </div>

                {teaBase.status === 'FROZEN' && (
                    <div className="rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-4 py-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="text-xs font-bold text-om-blocked">茶底已冻结，拼配入口关闭</div>
                            <div className="text-[11px] text-om-muted">
                                {teaBase.frozen_by ?? '未记录责任人'} · {teaBase.frozen_at ? formatDateTime(teaBase.frozen_at) : '—'}
                            </div>
                        </div>
                        <p className="mt-1 text-sm leading-6 text-om-ink">{teaBase.freeze_reason ?? '未填写冻结原因。'}</p>
                    </div>
                )}

                {teaBase.status === 'PENDING' && (
                    <div className="rounded-om-sm border border-om-downtime/30 bg-om-downtime-bg px-4 py-3">
                        <div className="text-xs font-bold text-om-downtime">茶底待检，暂时不能进入拼配</div>
                        <p className="mt-1 text-sm leading-6 text-om-ink">先把感官或缺失的检验项补齐；在状态变成“可用”前，入库批次只能留在仓库。</p>
                    </div>
                )}

                {(freezeOpen || unfreezeOpen) && (
                    <form onSubmit={freezeOpen ? submitFreeze : submitUnfreeze} className="rounded-om-sm border border-om-line bg-om-card p-4">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 className="text-sm font-bold text-om-ink">{freezeOpen ? '冻结这份茶底' : '解除冻结'}</h2>
                                <p className="mt-0.5 text-xs leading-5 text-om-muted">
                                    {freezeOpen ? '冻结后不能选入拼配；需要写清原因，责任人为当前操作人。' : '解除后按质量指纹重新判断：全部通过回到可用，仍有待检则回到待检。'}
                                </p>
                            </div>
                            <span className="text-[11px] text-om-faint">原因必填</span>
                        </div>
                        <textarea
                            value={freezeOpen ? freezeForm.data.reason : unfreezeForm.data.reason}
                            onChange={(event) => (freezeOpen ? freezeForm.setData('reason', event.target.value) : unfreezeForm.setData('reason', event.target.value))}
                            rows={3}
                            placeholder={freezeOpen ? '例如：含水率超出拼配窗口，需复检' : '例如：复检通过，恢复可用'}
                            className="mt-3 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 text-sm text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-2 focus:ring-om-accent/20"
                        />
                        {((freezeOpen && freezeForm.errors.reason) || (!freezeOpen && unfreezeForm.errors.reason)) && (
                            <p className="mt-2 text-xs font-medium text-om-blocked">
                                {freezeOpen ? freezeForm.errors.reason : unfreezeForm.errors.reason}
                            </p>
                        )}
                        <div className="mt-3 flex items-center justify-end gap-2">
                            <button type="button" onClick={() => (freezeOpen ? setFreezeOpen(false) : setUnfreezeOpen(false))} className="btn-touch btn-secondary text-xs">取消</button>
                            <button type="submit" disabled={freezeOpen ? freezeForm.processing : unfreezeForm.processing} className={`btn-touch text-xs ${freezeOpen ? 'btn-danger' : 'btn-primary'} disabled:opacity-50`}>
                                {freezeOpen ? '确认冻结' : '确认解除'}
                            </button>
                        </div>
                    </form>
                )}

                <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-3">
                    <IdentityCell label="产地" value={teaBase.origin} />
                    <IdentityCell label="品种" value={teaBase.variety} />
                    <IdentityCell label="等级 / 季节" value={`${teaBase.grade ?? '—'} / ${teaBase.season ?? '—'}`} />
                    <IdentityCell label="筛号" value={teaBase.screen_size} />
                    <IdentityCell label="供应商" value={teaBase.supplier_name} />
                    <IdentityCell label="最近检验" value={teaBase.last_inspected_at ? formatDateTime(teaBase.last_inspected_at) : '—'} mono />
                </div>

                <Panel
                    title="质量指纹"
                    subtitle="实测点贴着标准线和限量线，不用雷达图"
                    action={<QualityPill status={teaBase.quality_overall} />}
                >
                    <div className="grid gap-3 p-4 md:grid-cols-2">
                        {teaBase.fingerprint.map((item) => <FingerprintMetric key={item.key} item={item} />)}
                    </div>
                </Panel>

                <Panel
                    title="可用批次"
                    subtitle={`${available_lot_count} 个批次可进入拼配；冻结、待检或已耗尽批次只做追溯`}
                    action={
                        <button
                            type="button"
                            disabled
                            title={can_blend ? '拼配版本工作台下一阶段上线' : '没有可进入拼配的批次'}
                            className="btn-touch btn-secondary text-xs disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            进入拼配（下一阶段）
                        </button>
                    }
                >
                    {teaBase.status !== 'AVAILABLE' && (
                        <div className="border-b border-om-line2 bg-om-chip/30 px-4 py-2 text-xs text-om-muted">
                            当前茶底状态为“{TEA_BASE_STATUS[teaBase.status]?.label ?? teaBase.status}”，入库批次暂不能进入拼配。
                        </div>
                    )}
                    {lots.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-om-faint">还没有入库批次。先完成来料检验并放行。</p>
                    ) : (
                        <div className="overflow-hidden">
                            <table className="w-full table-fixed text-xs">
                                <thead>
                                    <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-[10px] font-bold tracking-wide text-om-muted">
                                        <th className="w-[34%] px-4 py-2">物料批号</th>
                                        <th className="w-[20%] px-2 py-2">可用 / 到货</th>
                                        <th className="w-[18%] px-2 py-2">检验</th>
                                        <th className="hidden w-[14%] px-2 py-2 md:table-cell">到厂 / 有效期</th>
                                        <th className="w-[14%] px-2 py-2">状态</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lots.map((lot) => (
                                        <tr key={lot.id} className="border-b border-om-line2 last:border-0">
                                            <td className="px-4 py-2.5">
                                                <div className="truncate font-mono font-bold text-om-ink" title={lot.lot_number}>{lot.lot_number}</div>
                                                <div className="mt-0.5 text-[10px] text-om-faint">{lot.unit_of_measure}</div>
                                            </td>
                                            <td className="px-2 py-2.5">
                                                <div className="font-mono font-bold text-om-ink">{formatNumber(lot.quantity_available)}</div>
                                                <div className="mt-0.5 text-[10px] text-om-faint">/ {formatNumber(lot.quantity_received)}</div>
                                            </td>
                                            <td className="px-2 py-2.5">
                                                {lot.inspection ? (
                                                    <>
                                                        <div className={`font-bold ${(INSPECTION_STATUS[lot.inspection.status] ?? INSPECTION_STATUS.pending).className}`}>
                                                            {(INSPECTION_STATUS[lot.inspection.status] ?? INSPECTION_STATUS.pending).label}
                                                        </div>
                                                        <div className="mt-0.5 text-[10px] text-om-faint">#{lot.inspection.id}{lot.inspection.completed_at ? ` · ${lot.inspection.completed_at}` : ''}</div>
                                                    </>
                                                ) : <span className="text-om-faint">未检验</span>}
                                            </td>
                                            <td className="hidden px-2 py-2.5 md:table-cell">
                                                <div className="font-mono text-[10px] text-om-muted">{lot.received_at ?? '—'}</div>
                                                <div className="mt-0.5 font-mono text-[10px] text-om-faint">{lot.expiry_date ?? '—'}</div>
                                            </td>
                                            <td className="px-2 py-2.5"><LotPill status={lot.status} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {can_blend && (
                        <p className="border-t border-om-line2 px-4 py-2 text-[11px] text-om-faint">
                            拼配版本工作台下一阶段上线；当前先把可用批次跑通，进入拼配按钮暂锁。
                        </p>
                    )}
                </Panel>

                {teaBase.notes && (
                    <div className="rounded-om-sm border border-om-line bg-om-card px-4 py-3">
                        <h2 className="text-xs font-bold text-om-ink">档案备注</h2>
                        <p className="mt-1 whitespace-pre-line text-sm leading-6 text-om-muted">{teaBase.notes}</p>
                    </div>
                )}
            </div>
        </>
    );
}

function IdentityCell({ label, value, mono = false }) {
    return (
        <div className="min-w-0 border-b border-om-line2 px-4 py-3 sm:border-r sm:last:border-r-0 xl:border-b-0">
            <div className="text-[10px] font-bold tracking-wide text-om-faint">{label}</div>
            <div className={`mt-0.5 truncate text-sm text-om-ink ${mono ? 'font-mono text-xs' : ''}`}>{value ?? '—'}</div>
        </div>
    );
}

function FingerprintMetric({ item }) {
    const quality = QUALITY_STATUS[item.status] ?? QUALITY_STATUS.PENDING;
    const value = item.value !== null && item.value !== undefined ? Number(item.value) : null;

    const domainMax = item.key === 'sensory'
        ? 100
        : item.key === 'moisture'
            ? Math.max(8, Number(item.max ?? 8))
            : Math.max(Number(item.limit ?? item.max ?? 1) * 1.5, (value ?? 0) * 1.2, 1);

    const bandStart = item.min !== null && item.min !== undefined ? Number(item.min) : 0;
    const bandEnd = item.max !== null && item.max !== undefined ? Number(item.max) : domainMax;
    const bandLeft = Math.max(0, Math.min(100, (bandStart / domainMax) * 100));
    const bandWidth = Math.max(0, Math.min(100 - bandLeft, ((bandEnd - bandStart) / domainMax) * 100));
    const pointLeft = value !== null ? Math.max(0, Math.min(100, (value / domainMax) * 100)) : null;

    return (
        <div className="min-w-0 rounded-om-sm border border-om-line2 bg-om-bg px-3 py-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="text-[11px] font-bold text-om-muted">{item.label}</div>
                    <div className="mt-0.5 flex items-baseline gap-1">
                        <span className={`font-mono text-lg font-bold ${value === null ? 'text-om-faint' : 'text-om-ink'}`}>
                            {value === null ? '—' : formatMetric(value, item.key)}
                        </span>
                        <span className="text-[10px] text-om-faint">{value === null ? '未检' : item.unit}</span>
                    </div>
                </div>
                <span className={`shrink-0 rounded px-2 py-0.5 text-[10px] font-bold ${quality.className}`}>{quality.label}</span>
            </div>

            <div className="relative mt-3 h-2 rounded-full bg-om-chip">
                <div className="absolute inset-y-0 rounded-full bg-om-running/20" style={{ left: `${bandLeft}%`, width: `${bandWidth}%` }} />
                {item.limit !== null && item.limit !== undefined && item.key !== 'moisture' && item.key !== 'sensory' && (
                    <div className="absolute inset-y-0 w-px bg-om-running/70" style={{ left: `${Math.min(100, (Number(item.limit) / domainMax) * 100)}%` }} />
                )}
                {pointLeft !== null && (
                    <div
                        className={`absolute top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-om-card ${item.status === 'FAIL' ? 'bg-om-blocked' : item.status === 'PENDING' ? 'bg-om-downtime' : 'bg-om-running'}`}
                        style={{ left: `${pointLeft}%` }}
                    />
                )}
            </div>
            <div className="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-[10px] text-om-faint">
                <span>{item.caption}</span>
                {value !== null && <span>{item.key === 'moisture' ? `${formatMetric(value, item.key)}%` : `${formatMetric(value, item.key)} ${item.unit}`}</span>}
            </div>
        </div>
    );
}

function formatMetric(value, key) {
    if (key === 'moisture') return Number(value).toFixed(1);
    if (key === 'sensory') return Number(value).toFixed(0);
    return Number(value).toFixed(3);
}

function Panel({ title, subtitle, action, children }) {
    return (
        <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
            <div className="flex flex-wrap items-start justify-between gap-2 border-b border-om-line2 px-4 py-3">
                <div>
                    <h2 className="text-sm font-bold text-om-ink">{title}</h2>
                    {subtitle && <p className="mt-0.5 text-xs leading-5 text-om-muted">{subtitle}</p>}
                </div>
                {action}
            </div>
            {children}
        </div>
    );
}

function QualityPill({ status, label }) {
    const item = QUALITY_STATUS[status] ?? QUALITY_STATUS.PENDING;
    return (
        <span className={`inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-[11px] font-bold ${item.className}`}>
            <span className="h-1.5 w-1.5 rounded-full bg-current" />
            {label ? `${label} · ` : ''}{item.label}
        </span>
    );
}

function TeaBasePill({ status }) {
    const item = TEA_BASE_STATUS[status] ?? TEA_BASE_STATUS.PENDING;
    return <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-bold ${item.className}`}>{item.label}</span>;
}

function LotPill({ status }) {
    const item = LOT_STATUS[status] ?? LOT_STATUS.received;
    return <span className={`inline-block rounded px-2 py-0.5 text-[10px] font-bold ${item.className}`}>{item.label}</span>;
}

TeaBaseShow.layout = (page) => <AppLayout>{page}</AppLayout>;
