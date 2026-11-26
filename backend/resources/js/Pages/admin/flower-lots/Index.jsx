import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { formatNumber } from '../../../lib/i18n';
import { FLOWER_STATUS, FLOWER_URGENCY, clock, remainingLabel, secondsLeft, urgencyFromSeconds, useNow } from './flowerTime';

export default function FlowerLotIndex() {
    const { lots = [], summary = {} } = usePage().props;
    const now = useNow();

    return (
        <>
            <Head title="鲜花批次" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">鲜花批次</h1>
                        <p className="mt-1 max-w-[700px] text-sm leading-6 text-om-muted">
                            鲜花的时间窗比表格更重要：到厂、养护、可用、付窨。先看还有多久，再决定分给哪个批次。
                        </p>
                        <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span className="text-om-muted">今日到厂 <span className="font-mono font-bold text-om-ink">{summary.total ?? lots.length}</span></span>
                            <span className="text-om-running">可用 {summary.available ?? 0}</span>
                            <span className="text-om-downtime">养护 {summary.curing ?? 0}</span>
                            <span className={summary.expired ? 'text-om-blocked' : 'text-om-faint'}>超窗 {summary.expired ?? 0}</span>
                            <span className="text-om-muted">已付窨 {summary.allocated ?? 0}</span>
                            {(summary.disposed ?? 0) > 0 && <span className="text-om-muted">已处置 {summary.disposed}</span>}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/tea-bases" className="btn-touch btn-secondary text-sm">茶底档案</Link>
                        <Link href="/admin/coa" className="btn-touch btn-secondary text-sm">批次放行</Link>
                    </div>
                </div>

                <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-om-line2 px-4 py-3">
                        <div>
                            <h2 className="text-sm font-bold text-om-ink">今日到厂</h2>
                            <p className="mt-0.5 text-xs text-om-muted">按可用截止时间从紧到松，超窗批次排在最前</p>
                        </div>
                        <span className="text-xs text-om-faint">剩余时间实时刷新</span>
                    </div>

                    {lots.length === 0 ? (
                        <div className="px-4 py-12 text-center">
                            <p className="text-sm text-om-faint">今天没有到厂鲜花。</p>
                            <Link href="/admin/material-lots" className="btn-touch btn-secondary mt-4 inline-flex text-xs">打开物料批</Link>
                        </div>
                    ) : (
                        <div className="divide-y divide-om-line2">
                            {lots.map((lot) => <FlowerRow key={lot.id} lot={lot} now={now} />)}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function FlowerRow({ lot, now }) {
    const remaining = secondsLeft(lot.usable_until, lot.remaining_seconds, now);
    const urgency = urgencyFromSeconds(remaining, lot.status);
    const terminal = ['SCRAPPED', 'DOWNGRADED'].includes(lot.status);
    const tone = terminal ? { text: 'text-om-muted', bar: 'bg-om-faintest' } : (FLOWER_URGENCY[urgency] ?? FLOWER_URGENCY.safe);
    const status = FLOWER_STATUS[lot.status] ?? FLOWER_STATUS.AVAILABLE;

    return (
        <div className="px-4 py-4 transition-colors hover:bg-om-chip/20">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href={`/admin/flower-lots/${lot.id}`} className="font-mono text-sm font-bold text-om-ink hover:text-om-accent">{lot.code}</Link>
                        <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-bold ${status.className}`}>{status.label}</span>
                    </div>
                    <div className="mt-1 truncate text-sm font-semibold text-om-ink">{lot.variety}</div>
                    <div className="mt-0.5 truncate text-[11px] text-om-muted">{lot.origin ?? '产地未登记'} · {lot.supplier_name ?? '供应商未登记'}</div>
                </div>
                <div className="shrink-0 text-right">
                    <div className={`font-mono text-lg font-bold ${tone.text}`}>{remainingLabel(remaining, lot.status)}</div>
                    <div className="mt-0.5 font-mono text-[10px] text-om-faint">可用至 {clock(lot.usable_until)}</div>
                </div>
            </div>

            <MiniTimeBar lot={lot} now={now} urgency={urgency} />

            <div className="mt-3 grid grid-cols-3 gap-2">
                <MiniCell label="到厂 / 重量" value={`${clock(lot.arrived_at)} · ${formatNumber(lot.weight_kg)} kg`} />
                <MiniCell label="开放率" value={lot.opening_rate != null ? `${lot.opening_rate}%` : '—'} />
                <MiniCell
                    label="已付窨"
                    value={`${formatNumber(lot.allocated_weight ?? 0)} kg`}
                    note={lot.status === 'EXPIRED' ? '不可付窨' : ['SCRAPPED', 'DOWNGRADED'].includes(lot.status) ? '已处置' : lot.remaining_weight > 0 ? `剩 ${formatNumber(lot.remaining_weight)} kg` : '已分配完'}
                />
            </div>

            <div className="mt-3 flex items-center justify-between gap-2">
                <span className="text-[11px] text-om-faint">
                    {lot.curing_temperature != null ? `养护 ${lot.curing_temperature}°C` : '未记录养护温度'}
                </span>
                <Link href={`/admin/flower-lots/${lot.id}`} className="text-[11px] font-bold text-om-accent hover:underline">打开 →</Link>
            </div>
        </div>
    );
}

function MiniTimeBar({ lot, now, urgency }) {
    const start = Date.parse(lot.arrived_at);
    const end = Date.parse(lot.usable_until);
    const current = now;
    const progress = end > start ? Math.max(0, Math.min(100, ((current - start) / (end - start)) * 100)) : 100;
    const tone = FLOWER_URGENCY[urgency] ?? FLOWER_URGENCY.safe;

    return (
        <div className="mt-3">
            <div className="relative h-2 overflow-hidden rounded-full bg-om-chip">
                <div className={`h-full rounded-full ${tone.bar}`} style={{ width: `${progress}%` }} />
            </div>
            <div className="mt-1 flex items-center justify-between text-[10px] text-om-faint">
                <span>到厂 {clock(lot.arrived_at)}</span>
                <span className="font-mono">{lot.status === 'EXPIRED' ? '窗口已结束' : `已过 ${Math.round(progress)}%`}</span>
                <span>截止 {clock(lot.usable_until)}</span>
            </div>
        </div>
    );
}

function MiniCell({ label, value, note }) {
    return (
        <div className="min-w-0 rounded border border-om-line2 bg-om-bg px-2 py-1.5">
            <div className="text-[10px] font-bold text-om-faint">{label}</div>
            <div className="mt-0.5 truncate font-mono text-[11px] font-bold text-om-ink">{value}</div>
            {note && <div className="mt-0.5 truncate text-[10px] text-om-muted">{note}</div>}
        </div>
    );
}

FlowerLotIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
