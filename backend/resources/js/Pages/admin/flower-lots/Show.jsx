import { Head, Link, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { formatNumber } from '../../../lib/i18n';
import { FLOWER_STATUS, FLOWER_URGENCY, clock, duration, remainingLabel, secondsLeft, urgencyFromSeconds, useNow } from './flowerTime';

export default function FlowerLotShow({ flowerLot, allocations = [], batches = [] }) {
    const now = useNow();
    const [panel, setPanel] = useState(null);
    const remaining = secondsLeft(flowerLot.usable_until, flowerLot.remaining_seconds, now);
    const urgency = urgencyFromSeconds(remaining, flowerLot.status);
    const terminal = ['SCRAPPED', 'DOWNGRADED'].includes(flowerLot.status);
    const tone = terminal
        ? { text: 'text-om-muted', bar: 'bg-om-faintest' }
        : (FLOWER_URGENCY[urgency] ?? FLOWER_URGENCY.safe);
    const status = FLOWER_STATUS[flowerLot.status] ?? FLOWER_STATUS.AVAILABLE;

    return (
        <>
            <Head title={`鲜花批次 ${flowerLot.code}`} />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="mb-1 flex items-center gap-1 text-xs text-om-faint">
                            <Link href="/admin/flower-lots" className="hover:text-om-accent">鲜花批次</Link>
                            <span>/</span>
                            <span className="font-mono text-om-muted">{flowerLot.code}</span>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-bold tracking-tight text-om-ink">{flowerLot.code}</h1>
                            <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-bold ${status.className}`}>{status.label}</span>
                        </div>
                        <p className="mt-1 text-sm leading-6 text-om-muted">
                            {flowerLot.variety}
                            <span className="mx-1.5 text-om-faintest">·</span>
                            {flowerLot.origin ?? '产地未登记'}
                            <span className="mx-1.5 text-om-faintest">·</span>
                            {flowerLot.supplier_name ?? '供应商未登记'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {flowerLot.can_mark_available && (
                            <button type="button" onClick={() => setPanel(panel === 'mark' ? null : 'mark')} className="btn-touch btn-secondary text-sm">
                                标记可用
                            </button>
                        )}
                        {flowerLot.can_allocate && (
                            <button type="button" onClick={() => setPanel(panel === 'allocate' ? null : 'allocate')} className="btn-touch btn-primary text-sm">
                                付窨
                            </button>
                        )}
                        {flowerLot.can_dispose && (
                            <button type="button" onClick={() => setPanel(panel === 'dispose' ? null : 'dispose')} className="btn-touch btn-secondary text-sm text-om-blocked">
                                处置
                            </button>
                        )}
                    </div>
                </div>

                {panel === 'mark' && <MarkAvailablePanel lot={flowerLot} onClose={() => setPanel(null)} />}
                {panel === 'allocate' && <AllocatePanel lot={flowerLot} batches={batches} onClose={() => setPanel(null)} />}
                {panel === 'dispose' && <DisposePanel lot={flowerLot} onClose={() => setPanel(null)} />}

                <TimeWindowHero lot={flowerLot} remaining={remaining} urgency={urgency} tone={tone} />
                <StepRail lot={flowerLot} />

                {flowerLot.status === 'EXPIRED' && (
                    <div className="rounded-om-sm border border-om-blocked/30 bg-om-blocked-bg px-4 py-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="text-sm font-bold text-om-blocked">已超过可用截止，不能付窨</p>
                                <p className="mt-0.5 text-xs leading-5 text-om-blocked/80">
                                    超窗 {duration(Math.abs(remaining))}。这个批次只能报废或降级，不能进入下一批窨制。
                                </p>
                            </div>
                            {flowerLot.can_dispose && (
                                <button type="button" onClick={() => setPanel('dispose')} className="btn-touch btn-danger text-xs">处置这个批次</button>
                            )}
                        </div>
                    </div>
                )}

                {flowerLot.status === 'CURING' && (
                    <div className="rounded-om-sm border border-om-downtime/30 bg-om-downtime-bg px-4 py-3 text-xs leading-5 text-om-downtime">
                        还在养护。记录养护温度和开放率后标记可用，窗口才会开始被付窨消耗。
                    </div>
                )}

                <div className="grid grid-cols-2 gap-px overflow-hidden rounded-om-sm border border-om-line bg-om-line sm:grid-cols-4">
                    <Fact label="到厂" value={dateTime(flowerLot.arrived_at)} />
                    <Fact label="采摘" value={dateTime(flowerLot.picked_at)} />
                    <Fact label="可用起" value={dateTime(flowerLot.usable_from)} />
                    <Fact label="可用截止" value={dateTime(flowerLot.usable_until)} tone="text-om-ink" />
                    <Fact label="到厂重量" value={`${formatNumber(flowerLot.weight_kg)} kg`} />
                    <Fact label="已付窨" value={`${formatNumber(flowerLot.allocated_weight)} kg`} />
                    <Fact label="剩余可付窨" value={`${formatNumber(flowerLot.remaining_weight)} kg`} tone={flowerLot.remaining_weight > 0 ? 'text-om-ink' : 'text-om-faint'} />
                    <Fact label="库位" value={flowerLot.storage_location ?? '未登记'} />
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.35fr_1fr]">
                    <section className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                            <div>
                                <h2 className="text-sm font-bold text-om-ink">分配批次</h2>
                                <p className="mt-0.5 text-xs text-om-muted">付窨的重量、窨次和去向</p>
                            </div>
                            {flowerLot.can_allocate && (
                                <button type="button" onClick={() => setPanel('allocate')} className="text-xs font-bold text-om-accent hover:underline">继续付窨 →</button>
                            )}
                        </div>

                        {allocations.length === 0 ? (
                            <div className="px-4 py-8 text-center text-sm text-om-faint">
                                还没有付窨。
                                {flowerLot.can_allocate && <span className="text-om-muted"> 在窗口结束前分给一个批次。</span>}
                            </div>
                        ) : (
                            <div className="divide-y divide-om-line2">
                                {allocations.map((allocation) => (
                                    <div key={allocation.id} className="px-4 py-3">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                {allocation.batch ? (
                                                    <Link href={`/admin/coa/${allocation.batch.id}`} className="font-mono text-sm font-bold text-om-ink hover:text-om-accent">
                                                        {allocation.batch.batch_number}
                                                    </Link>
                                                ) : (
                                                    <span className="text-sm text-om-faint">批次已删除</span>
                                                )}
                                                <div className="mt-0.5 truncate text-xs text-om-muted">
                                                    {allocation.batch?.product ?? '产品未登记'}
                                                    {allocation.batch?.customer ? ` · ${allocation.batch.customer}` : ''}
                                                </div>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <div className="font-mono text-sm font-bold text-om-ink">{formatNumber(allocation.weight_kg)} kg</div>
                                                <div className="mt-0.5 text-[11px] text-om-muted">窨次 {allocation.seep_count}</div>
                                            </div>
                                        </div>
                                        <div className="mt-1.5 text-[11px] text-om-faint">
                                            {dateTime(allocation.allocated_at)} 由 {allocation.allocated_by ?? '未记录'} 登记
                                        </div>
                                        {allocation.notes && <p className="mt-1 text-xs leading-5 text-om-muted">{allocation.notes}</p>}
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="border-t border-om-line2 px-4 py-2 text-[11px] text-om-muted">
                            已付窨 <span className="font-mono font-bold text-om-ink">{formatNumber(flowerLot.allocated_weight)}</span> / {formatNumber(flowerLot.weight_kg)} kg
                            <span className="mx-1.5 text-om-faintest">·</span>
                            {flowerLot.status === 'EXPIRED'
                                ? '窗口已结束，不可付窨'
                                : ['SCRAPPED', 'DOWNGRADED'].includes(flowerLot.status)
                                    ? '已处置，不可付窨'
                                    : `剩余 ${formatNumber(flowerLot.remaining_weight)} kg`}
                        </div>
                    </section>

                    <div className="space-y-4">
                        <section className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                            <div className="border-b border-om-line2 px-4 py-3">
                                <h2 className="text-sm font-bold text-om-ink">养护与开放率</h2>
                                <p className="mt-0.5 text-xs text-om-muted">标记可用时要留下这两个数</p>
                            </div>
                            <div className="px-4 py-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <div className="text-[10px] font-bold text-om-faint">养护温度</div>
                                        <div className="mt-1 font-mono text-2xl font-bold text-om-ink">
                                            {flowerLot.curing_temperature != null ? `${flowerLot.curing_temperature}°C` : '—'}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-[10px] font-bold text-om-faint">开放率</div>
                                        <div className="mt-1 font-mono text-2xl font-bold text-om-ink">
                                            {flowerLot.opening_rate != null ? `${flowerLot.opening_rate}%` : '—'}
                                        </div>
                                    </div>
                                </div>
                                <OpenRateBar value={flowerLot.opening_rate} />
                                <p className="mt-2 text-[11px] leading-5 text-om-faint">
                                    开放率过低，花香还没打开；过高，花已经老了。先看数字，再付窨。
                                </p>
                            </div>
                        </section>

                        <section className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                            <div className="border-b border-om-line2 px-4 py-3">
                                <h2 className="text-sm font-bold text-om-ink">处置记录</h2>
                            </div>
                            {flowerLot.disposed_at ? (
                                <div className="px-4 py-3">
                                    <div className="text-xs font-bold text-om-ink">
                                        {FLOWER_STATUS[flowerLot.status]?.label ?? flowerLot.status_label}
                                        <span className="ml-2 font-normal text-om-faint">{dateTime(flowerLot.disposed_at)} · {flowerLot.disposed_by ?? '未记录'}</span>
                                    </div>
                                    <p className="mt-1 text-xs leading-5 text-om-muted">{flowerLot.disposition_reason}</p>
                                </div>
                            ) : (
                                <div className="px-4 py-5 text-xs text-om-faint">未处置。</div>
                            )}
                        </section>
                    </div>
                </div>

                <section className="rounded-om-sm border border-om-line bg-om-card px-4 py-3">
                    <h2 className="text-sm font-bold text-om-ink">档案备注</h2>
                    <p className="mt-1 text-xs leading-5 text-om-muted">{flowerLot.notes ?? '没有备注。'}</p>
                </section>

                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <Link href="/admin/flower-lots" className="btn-touch btn-secondary">返回鲜花批次</Link>
                    <Link href="/admin/tea-bases" className="btn-touch btn-secondary">茶底档案</Link>
                </div>
            </div>
        </>
    );
}

function TimeWindowHero({ lot, remaining, urgency, tone }) {
    const terminal = ['SCRAPPED', 'DOWNGRADED'].includes(lot.status);
    return (
        <section className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
            <div className="flex flex-wrap items-start justify-between gap-3 px-4 pb-3 pt-4">
                <div className="min-w-0">
                    <div className="text-[10px] font-bold uppercase tracking-[0.18em] text-om-faint">可用窗口</div>
                    <div className={`mt-1 font-mono text-3xl font-bold tracking-tight ${terminal ? 'text-om-muted' : tone.text}`}>
                        {terminal ? (FLOWER_STATUS[lot.status]?.label ?? '已处置') : remainingLabel(remaining, lot.status)}
                    </div>
                    <div className="mt-1 font-mono text-xs text-om-muted">
                        可用至 {dateTime(lot.usable_until)}
                        <span className="mx-1.5 text-om-faintest">·</span>
                        到厂 {clock(lot.arrived_at)}
                    </div>
                </div>
                <div className="text-right">
                    <div className="text-[10px] font-bold text-om-faint">状态</div>
                    <div className="mt-1 font-mono text-sm font-bold text-om-ink">{lot.status_label}</div>
                    <div className="mt-1 text-[11px] text-om-muted">开放率 {lot.opening_rate != null ? `${lot.opening_rate}%` : '待检'}</div>
                </div>
            </div>

            <div className="px-4 pb-4">
                <TimeWindowBar lot={lot} barrier={tone.bar} />
                <div className="mt-2 flex items-center justify-between gap-2 text-[10px] text-om-faint">
                    <span>到厂 {dateTime(lot.arrived_at)}</span>
                    <span className="text-center">可用起 {lot.usable_from ? dateTime(lot.usable_from) : '—'}</span>
                    <span className="text-right">截止 {dateTime(lot.usable_until)}</span>
                </div>
                {!terminal && (
                    <p className="mt-2 text-[11px] leading-5 text-om-muted">
                        {urgency === 'expired'
                            ? '窗口已经结束，不能再付窨。'
                            : urgency === 'critical'
                                ? '不到 4 小时，先处理这个批次，别让它压在养护间。'
                                : urgency === 'warning'
                                    ? '还剩不到 8 小时，可以安排到下一批窨制。'
                                    : '窗口还宽裕，但仍按截止时间排序。'}
                    </p>
                )}
            </div>
        </section>
    );
}

function TimeWindowBar({ lot, barrier }) {
    const start = Date.parse(lot.arrived_at);
    const ready = Date.parse(lot.usable_from);
    const end = Date.parse(lot.usable_until);
    const now = Date.now();
    const total = end - start;
    const progress = total > 0 ? Math.max(0, Math.min(100, ((now - start) / total) * 100)) : 100;
    const readyPct = total > 0 && !Number.isNaN(ready) ? Math.max(0, Math.min(100, ((ready - start) / total) * 100)) : 0;

    return (
        <div>
            <div className="relative h-3 rounded-full bg-om-chip">
                <div className={`absolute inset-y-0 left-0 rounded-full ${barrier}`} style={{ width: `${progress}%` }} />
                {readyPct > 0 && readyPct < 100 && (
                    <div className="absolute inset-y-0 w-px bg-om-card/80" style={{ left: `${readyPct}%` }} />
                )}
                <div className="absolute inset-y-0 w-0.5 bg-om-ink/70" style={{ left: `${progress}%` }} />
            </div>
        </div>
    );
}

function StepRail({ lot }) {
    const steps = lot.workflow ?? [];
    const stopped = ['SCRAPPED', 'DOWNGRADED'].includes(lot.status);
    return (
        <div className="rounded-om-sm border border-om-line bg-om-card px-4 py-3">
            <div className="flex items-center">
                {steps.map((step, index) => {
                    const isReady = step.key === 'ready';
                    const expired = isReady && lot.status === 'EXPIRED';
                    const stoppedStep = stopped && index >= 2;
                    const done = step.done && !stoppedStep;
                    return (
                        <React.Fragment key={step.key}>
                            {index > 0 && <div className={`h-px flex-1 ${done ? 'bg-om-line' : 'bg-om-line2'}`} />}
                            <div className="flex w-16 shrink-0 flex-col items-center">
                                <div className={`flex h-7 w-7 items-center justify-center rounded-full border text-[11px] font-bold ${
                                    expired
                                        ? 'border-om-blocked bg-om-blocked-bg text-om-blocked'
                                        : done
                                            ? 'border-om-running bg-om-running-bg text-om-running'
                                            : 'border-om-line2 bg-om-bg text-om-faint'
                                }`}>
                                    {stoppedStep ? '×' : expired ? '!' : done ? '✓' : index + 1}
                                </div>
                                <div className={`mt-1 text-[10px] font-bold ${stoppedStep ? 'text-om-faint' : expired ? 'text-om-blocked' : done ? 'text-om-ink' : 'text-om-faint'}`}>
                                    {expired ? '超窗' : step.label}
                                </div>
                                {step.at && <div className="mt-0.5 font-mono text-[9px] text-om-faint">{clock(step.at)}</div>}
                            </div>
                        </React.Fragment>
                    );
                })}
            </div>
        </div>
    );
}

function OpenRateBar({ value }) {
    const rate = value == null ? 0 : Math.max(0, Math.min(100, Number(value)));
    const tone = rate < 75 ? 'bg-om-downtime' : 'bg-om-running';
    return (
        <div className="mt-4">
            <div className="relative h-2 rounded-full bg-om-chip">
                <div className={`h-full rounded-full ${tone}`} style={{ width: `${rate}%` }} />
                <div className="absolute inset-y-0 w-px bg-om-card/80" style={{ left: '75%' }} />
            </div>
            <div className="mt-1 flex justify-between text-[10px] text-om-faint">
                <span>0%</span>
                <span>付窨建议 75%</span>
                <span>100%</span>
            </div>
        </div>
    );
}

function Fact({ label, value, tone = 'text-om-ink' }) {
    return (
        <div className="min-w-0 bg-om-card px-3 py-2.5">
            <div className="text-[10px] font-bold text-om-faint">{label}</div>
            <div className={`mt-0.5 truncate font-mono text-xs font-bold ${tone}`}>{value}</div>
        </div>
    );
}

function MarkAvailablePanel({ lot, onClose }) {
    const form = useForm({
        curing_temperature: lot.curing_temperature ?? '',
        opening_rate: lot.opening_rate ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(`/admin/flower-lots/${lot.id}/mark-available`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <form onSubmit={submit} className="rounded-om-sm border border-om-line bg-om-card p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-sm font-bold text-om-ink">标记可用</h2>
                    <p className="mt-0.5 text-xs text-om-muted">留下养护温度和开放率，窗口从此刻开始。</p>
                </div>
                <button type="button" onClick={onClose} className="text-xs text-om-faint hover:text-om-ink">关闭</button>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-3">
                <label className="block">
                    <span className="text-[11px] font-bold text-om-muted">养护温度 °C</span>
                    <input type="number" step="0.1" min="0" max="50" value={form.data.curing_temperature} onChange={(e) => form.setData('curing_temperature', e.target.value)} className="mt-1 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 font-mono text-sm text-om-ink outline-none focus:border-om-accent" />
                    {form.errors.curing_temperature && <span className="mt-1 block text-[11px] text-om-blocked">{form.errors.curing_temperature}</span>}
                </label>
                <label className="block">
                    <span className="text-[11px] font-bold text-om-muted">开放率 %</span>
                    <input type="number" step="0.1" min="0" max="100" value={form.data.opening_rate} onChange={(e) => form.setData('opening_rate', e.target.value)} className="mt-1 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 font-mono text-sm text-om-ink outline-none focus:border-om-accent" />
                    {form.errors.opening_rate && <span className="mt-1 block text-[11px] text-om-blocked">{form.errors.opening_rate}</span>}
                </label>
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <button type="submit" disabled={form.processing} className="btn-touch btn-primary text-xs disabled:opacity-50">确认可用</button>
                <span className="text-[11px] text-om-faint">标记后不能撤销，除非走处置。</span>
            </div>
        </form>
    );
}

function AllocatePanel({ lot, batches, onClose }) {
    const form = useForm({
        batch_id: '',
        weight_kg: lot.remaining_weight > 0 ? String(lot.remaining_weight) : '',
        seep_count: '1',
        notes: '',
    });
    const selected = batches.find((batch) => String(batch.id) === String(form.data.batch_id));

    const submit = (event) => {
        event.preventDefault();
        form.post(`/admin/flower-lots/${lot.id}/allocate`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <form onSubmit={submit} className="rounded-om-sm border border-om-line bg-om-card p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-sm font-bold text-om-ink">付窨</h2>
                    <p className="mt-0.5 text-xs text-om-muted">把鲜花按重量和窨次分给一个茶叶批次。剩余 {formatNumber(lot.remaining_weight)} kg。</p>
                </div>
                <button type="button" onClick={onClose} className="text-xs text-om-faint hover:text-om-ink">关闭</button>
            </div>

            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <label className="block sm:col-span-2">
                    <span className="text-[11px] font-bold text-om-muted">批次</span>
                    <select value={form.data.batch_id} onChange={(e) => form.setData('batch_id', e.target.value)} className="mt-1 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent">
                        <option value="">选择一个批次</option>
                        {batches.map((batch) => (
                            <option key={batch.id} value={batch.id}>
                                {batch.batch_number} · {batch.product ?? '产品未登记'} · {batch.customer ?? '无客户'}
                            </option>
                        ))}
                    </select>
                    {form.errors.batch_id && <span className="mt-1 block text-[11px] text-om-blocked">{form.errors.batch_id}</span>}
                    {selected && (
                        <div className="mt-2 rounded border border-om-line2 bg-om-bg px-3 py-2 text-[11px] leading-5 text-om-muted">
                            批次 {selected.batch_number} · 该批计划 {formatNumber(selected.planned_qty)} {selected.unit_of_measure} · 窨次按本次填写
                        </div>
                    )}
                </label>
                <label className="block">
                    <span className="text-[11px] font-bold text-om-muted">本次付窨 kg</span>
                    <input type="number" step="0.1" min="0.1" max={lot.remaining_weight} value={form.data.weight_kg} onChange={(e) => form.setData('weight_kg', e.target.value)} className="mt-1 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 font-mono text-sm text-om-ink outline-none focus:border-om-accent" />
                    {form.errors.weight_kg && <span className="mt-1 block text-[11px] text-om-blocked">{form.errors.weight_kg}</span>}
                </label>
                <label className="block">
                    <span className="text-[11px] font-bold text-om-muted">窨次</span>
                    <input type="number" step="1" min="1" max="12" value={form.data.seep_count} onChange={(e) => form.setData('seep_count', e.target.value)} className="mt-1 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 font-mono text-sm text-om-ink outline-none focus:border-om-accent" />
                    {form.errors.seep_count && <span className="mt-1 block text-[11px] text-om-blocked">{form.errors.seep_count}</span>}
                </label>
                <label className="block sm:col-span-2">
                    <span className="text-[11px] font-bold text-om-muted">备注</span>
                    <textarea rows="2" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className="mt-1 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent" placeholder="可选：第几窨、花层、交接说明" />
                    {form.errors.notes && <span className="mt-1 block text-[11px] text-om-blocked">{form.errors.notes}</span>}
                </label>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-2">
                <button type="submit" disabled={form.processing} className="btn-touch btn-primary text-xs disabled:opacity-50">确认付窨</button>
                <span className="text-[11px] text-om-faint">超过截止或重量超出剩余都会被拦截。</span>
            </div>
        </form>
    );
}

function DisposePanel({ lot, onClose }) {
    const form = useForm({ action: 'scrap', reason: '' });

    const submit = (event) => {
        event.preventDefault();
        form.post(`/admin/flower-lots/${lot.id}/dispose`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <form onSubmit={submit} className="rounded-om-sm border border-om-line bg-om-card p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-sm font-bold text-om-ink">处置鲜花批次</h2>
                    <p className="mt-0.5 text-xs text-om-muted">超窗、开放率不合格或养护异常时使用。处置后不能付窨。</p>
                </div>
                <button type="button" onClick={onClose} className="text-xs text-om-faint hover:text-om-ink">关闭</button>
            </div>
            <div className="mt-3 flex flex-wrap gap-4">
                {[
                    ['scrap', '报废'],
                    ['downgrade', '降级'],
                ].map(([value, label]) => (
                    <label key={value} className="flex items-center gap-2 text-xs text-om-ink">
                        <input type="radio" name="action" value={value} checked={form.data.action === value} onChange={() => form.setData('action', value)} />
                        {label}
                    </label>
                ))}
            </div>
            {form.errors.action && <p className="mt-1 text-[11px] text-om-blocked">{form.errors.action}</p>}
            <label className="mt-3 block">
                <span className="text-[11px] font-bold text-om-muted">原因</span>
                <textarea rows="3" required value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} className="mt-1 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent" placeholder="例如：超过可用截止 2 小时，开放率仍不足。" />
                {form.errors.reason && <span className="mt-1 block text-[11px] text-om-blocked">{form.errors.reason}</span>}
            </label>
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <button type="submit" disabled={form.processing} className="btn-touch btn-danger text-xs disabled:opacity-50">确认处置</button>
                <span className="text-[11px] text-om-faint">处置会写入记录，不能撤销。</span>
            </div>
        </form>
    );
}

function dateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('zh-CN', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);
}

FlowerLotShow.layout = (page) => <AppLayout>{page}</AppLayout>;
