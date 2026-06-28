import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { __, formatDate } from '../../../../lib/i18n';

// ─── Shared helpers ───────────────────────────────────────────────────────────

function toMin(t) {
    if (!t) return 0;
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
}
function fmtMins(m) {
    return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}

// ─── Tachograph strip ─────────────────────────────────────────────────────────

export function Tacho({ activities, typeMeta, height = 56, showHours = true, isToday = false, nowMinutes = null, highlightId = null }) {
    const totalMin = 24 * 60;
    return (
        <div>
            <div className="relative overflow-hidden rounded-om-sm border border-om-line2 bg-om-panel"
                 style={{ height: `${height}px` }}>
                {/* Hour grid lines */}
                {Array.from({ length: 24 }, (_, h) => (
                    <div key={h} className={`absolute top-0 bottom-0 ${h % 6 === 0 ? 'bg-om-line' : 'bg-om-line2/50'}`}
                         style={{ left: `${(h / 24) * 100}%`, width: '1px' }} />
                ))}
                {/* Activity blocks */}
                {activities.map((a, i) => {
                    const left = (toMin(a.from) / totalMin) * 100;
                    const endMin = a.to === '24:00' ? totalMin : toMin(a.to);
                    const width = a.to === '24:00' ? (100 - left) : ((endMin - toMin(a.from)) / totalMin) * 100;
                    const color = typeMeta[a.type]?.color ?? '#94a3b8';
                    const hl = highlightId !== null && a.id === highlightId;
                    return (
                        <div key={i} className="absolute"
                             title={`${a.label ?? typeMeta[a.type]?.label ?? a.type} · ${a.from} → ${a.to}`}
                             style={{
                                 left: `${left}%`, width: `${width}%`,
                                 top: hl ? '0' : '2px', bottom: hl ? '0' : '2px',
                                 background: color,
                                 ...(hl ? { border: '2px solid #fff', boxShadow: '0 0 0 2px rgba(245,165,36,0.6)' } : {}),
                             }} />
                    );
                })}
                {/* NOW marker */}
                {isToday && nowMinutes !== null && (
                    <div className="absolute -top-1 -bottom-1 w-0.5 bg-om-downtime z-10"
                         style={{ left: `${(nowMinutes / totalMin) * 100}%` }}>
                        <div className="absolute -top-1 -left-1 w-2.5 h-2.5 rounded-full bg-om-card border-2 border-om-downtime" />
                    </div>
                )}
            </div>
            {showHours && (
                <div className="flex justify-between mt-1 font-mono text-[9px] text-om-muted tracking-wider">
                    {[0, 3, 6, 9, 12, 15, 18, 21, 24].map((h) => (
                        <span key={h}>{String(h).padStart(2, '0')}</span>
                    ))}
                </div>
            )}
        </div>
    );
}

// ─── EmployeeTabs header ──────────────────────────────────────────────────────

export function EmployeeTabs({ view, date, selectedWorkerId, selectedWorker, workers }) {
    const tabs = [
        { key: 'day', label: __('Day plan') },
        { key: 'team', label: __('Team day') },
        { key: 'month', label: __('Month') },
    ];

    const navTo = (params) => router.get('/admin/schedule/employees', params, { preserveState: false });

    return (
        <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <div className="font-mono text-[11px] tracking-wider font-bold uppercase text-om-downtime">
                    {__('Employee day planner · Tacho view')}
                </div>
                <h1 className="text-2xl md:text-3xl font-bold text-om-ink mt-0.5">
                    {view === 'team'
                        ? `${__('Team day')} · ${formatDate(new Date(date), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}`
                        : view === 'month'
                            ? `${__('Month overview')} · ${formatDate(new Date(date), { month: 'long', year: 'numeric' })}`
                            : <>{selectedWorker?.name ?? __('Day plan')} <span className="text-om-faint font-normal text-lg">· {formatDate(new Date(date), { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}</span></>
                    }
                </h1>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <div className="inline-flex p-1 rounded-om-sm bg-om-line2">
                    {tabs.map(({ key, label }) => (
                        <button key={key}
                                onClick={() => navTo({ view: key, date, worker_id: selectedWorkerId })}
                                className={`px-3 py-1.5 rounded-md font-mono text-[11px] font-bold tracking-wider uppercase transition-colors ${view === key ? 'bg-om-downtime text-white' : 'text-om-muted hover:text-om-ink'}`}>
                            {label}
                        </button>
                    ))}
                </div>

                <Link href="/admin/schedule" className="px-3 py-2 text-xs font-medium text-om-muted hover:text-om-ink">
                    &larr; {__('Production schedule')}
                </Link>

                {selectedWorkerId && (
                    <Link href={`/admin/schedule/employees/add?worker_id=${selectedWorkerId}&date=${date}`}
                       className="inline-flex items-center gap-2 px-3 h-9 rounded-om-sm bg-om-downtime hover:brightness-95 text-white font-mono text-xs font-bold tracking-wider uppercase">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.4"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4"/></svg>
                        {__('Add activity')}
                    </Link>
                )}
            </div>
        </div>
    );
}

// ─── Day Page ─────────────────────────────────────────────────────────────────

export default function EmployeeDay() {
    const {
        view, date, workers = [], selectedWorker, selectedWorkerId,
        activities = [], customTypes = [], typeMeta = {},
    } = usePage().props;

    const dateObj = new Date(date);
    const isToday = dateObj.toISOString().slice(0, 10) === new Date().toISOString().slice(0, 10);
    const nowMin = isToday ? (new Date().getHours() * 60 + new Date().getMinutes()) : null;

    const sums = {};
    activities.forEach((a) => { sums[a.type] = (sums[a.type] ?? 0) + (a.duration ?? 0); });
    const totalWork = (sums.work ?? 0) + (sums.setup ?? 0) + (sums.qc ?? 0);
    const totalBreaks = (sums.break ?? 0) + (sums.rest ?? 0);

    // 7-day strip: 3 days before and 3 days after
    const dayStrip = Array.from({ length: 7 }, (_, i) => {
        const d = new Date(date);
        d.setDate(d.getDate() + (i - 3));
        return d.toISOString().slice(0, 10);
    });

    const navTo = (params) => router.get('/admin/schedule/employees', params, { preserveState: false });

    const handleDelete = async (actId) => {
        if (!confirm(__('Remove this activity?'))) return;
        const csrfToken = document.querySelector('meta[name=csrf-token]')?.content ?? '';
        await fetch(`/admin/schedule/employees/${actId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        router.reload({ preserveState: false });
    };

    // Spotlight: "now" activity or first non-off
    let spotlight = null;
    if (isToday && nowMin !== null) {
        spotlight = activities.find(a => nowMin >= toMin(a.from) && nowMin < toMin(a.to === '24:00' ? '23:59' : a.to)) ?? null;
    }
    if (!spotlight) {
        spotlight = activities.find(a => a.type !== 'off') ?? null;
    }

    return (
        <>
            <Head title={__('Employee Day Plan')} />

            <EmployeeTabs view={view} date={date} selectedWorkerId={selectedWorkerId} selectedWorker={selectedWorker} workers={workers} />

            <div className="grid grid-cols-1 lg:grid-cols-[260px_1fr_360px] gap-4">

                {/* LEFT: Worker list */}
                <aside className="hidden lg:flex flex-col bg-om-card border border-om-line2 rounded-2xl p-4 min-h-[60vh]">
                    <div className="mb-3">
                        <div className="flex items-center gap-2 px-3 py-2 rounded-om-sm bg-om-chip">
                            <svg className="w-4 h-4 text-om-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" placeholder={__('Search worker')}
                                   className="bg-transparent w-full text-sm text-om-muted placeholder-om-faint outline-none font-mono"
                                   onChange={(e) => {/* could filter locally */}} />
                        </div>
                    </div>
                    <div className="font-mono text-[10px] tracking-wider text-om-muted uppercase mt-1 mb-2">
                        {__('Workers')} · {workers.length}
                    </div>
                    <div className="flex flex-col gap-1.5 overflow-y-auto flex-1">
                        {workers.map((w) => {
                            const on = w.id === selectedWorkerId;
                            const parts = (w.name ?? '').trim().split(' ');
                            const initials = ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
                            return (
                                <button key={w.id}
                                        onClick={() => navTo({ view: 'day', date, worker_id: w.id })}
                                        className={`flex items-center gap-3 p-2.5 rounded-om-sm border transition-colors text-left ${on ? 'bg-om-downtime-bg border-om-downtime' : 'bg-om-panel border-om-line2 hover:bg-om-chip'}`}>
                                    <div className={`w-8 h-8 rounded-om-sm font-mono text-[10px] font-bold flex items-center justify-center flex-shrink-0 ${on ? 'bg-om-downtime text-white' : 'bg-om-line2 text-om-muted'}`}>
                                        {initials}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="text-sm font-semibold text-om-ink truncate">{w.name}</div>
                                        <div className="font-mono text-[10px] text-om-muted mt-0.5 truncate">
                                            {w.code}{w.personnel_class_code ? ` · ${w.personnel_class_code}` : ''}
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                    <div className="mt-3 p-3 rounded-om-sm bg-om-panel">
                        <div className="font-mono text-[9px] tracking-wider text-om-muted uppercase">{__('Shift coverage')}</div>
                        <div className="font-mono text-2xl font-bold text-emerald-600 mt-0.5">
                            {workers.length}<span className="text-sm text-om-faint">/{workers.length}</span>
                        </div>
                    </div>
                </aside>

                {/* CENTER: Timeline */}
                <section className="bg-om-card border border-om-line2 rounded-2xl p-4 md:p-5 flex flex-col gap-4 min-w-0">
                    {/* Date strip */}
                    <div className="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
                        {dayStrip.map((d) => {
                            const on = d === date;
                            return (
                                <button key={d} onClick={() => navTo({ view: 'day', date: d, worker_id: selectedWorkerId })}
                                        className={`flex-shrink-0 px-3 py-2 rounded-om-sm font-mono text-[11px] font-bold tracking-wider uppercase border ${on ? 'bg-om-downtime border-om-downtime text-white' : 'bg-om-panel border-om-line2 text-om-muted hover:bg-om-chip'}`}>
                                    {formatDate(new Date(d), { weekday: 'short', day: 'numeric' })}
                                </button>
                            );
                        })}
                    </div>

                    {/* Summary band */}
                    <div className="rounded-om bg-gradient-to-br from-gray-50 to-white border border-om-line2 p-4">
                        <div className="flex flex-wrap justify-between items-start gap-3">
                            <div>
                                <div className="font-mono text-[10px] tracking-wider font-bold uppercase text-om-downtime">
                                    {formatDate(dateObj, { weekday: 'short', day: 'numeric', month: 'short' })} · {__('A-shift')}
                                </div>
                                <div className="text-lg md:text-xl font-bold text-om-ink mt-1">
                                    {__(':duration planned', { duration: fmtMins(totalWork + totalBreaks + (sums.maint ?? 0) + (sums.meeting ?? 0) + (sums.training ?? 0) + (sums.travel ?? 0)) })}
                                </div>
                                <div className="font-mono text-[11px] text-om-muted mt-0.5">
                                    {__(':count activities', { count: activities.length })}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="font-mono text-xl md:text-2xl font-bold text-emerald-600">{fmtMins(totalWork)}</div>
                                <div className="font-mono text-[9px] tracking-wider text-om-muted uppercase mt-0.5">{__('Productive')}</div>
                            </div>
                        </div>
                        <div className="mt-4">
                            <Tacho activities={activities} typeMeta={typeMeta} height={56} isToday={isToday} nowMinutes={nowMin} />
                        </div>
                        <div className="flex flex-wrap justify-between gap-3 mt-4 font-mono text-[10.5px] text-om-muted">
                            <span>Σ <span className="font-bold text-om-ink">{fmtMins(totalWork)}</span> {__('work')}</span>
                            <span><span className="font-bold text-om-ink">{fmtMins(totalBreaks)}</span> {__('breaks')}</span>
                            <span><span className="font-bold text-rose-600">{fmtMins(sums.maint ?? 0)}</span> {__('maint')}</span>
                            <span><span className="font-bold text-om-ink">{fmtMins(sums.off ?? 0)}</span> {__('off')}</span>
                        </div>
                    </div>

                    {/* Type legend pills */}
                    <div className="flex flex-wrap gap-1.5">
                        {Object.entries(sums).map(([type, mins]) => {
                            const def = typeMeta[type];
                            if (!def) return null;
                            return (
                                <div key={type} className="flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-om-card"
                                     style={{ borderColor: `${def.color}80` }}>
                                    <span className="w-2 h-2 rounded-sm" style={{ background: def.color }} />
                                    <span className="text-xs font-semibold text-om-muted">{def.label}</span>
                                    <span className="font-mono text-[10px] font-bold text-om-muted">{fmtMins(mins)}</span>
                                </div>
                            );
                        })}
                    </div>

                    {/* Activity list */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-om-muted uppercase mb-2">
                            {__('Activities')} · {activities.length}
                        </div>
                        <div className="flex flex-col gap-1">
                            {activities.length === 0 ? (
                                <div className="p-6 text-center text-sm text-om-faint border border-dashed border-om-line2 rounded-om">
                                    {__('No activities planned for this day.')}
                                </div>
                            ) : activities.map((a, i) => {
                                const def = typeMeta[a.type] ?? typeMeta.off ?? { color: '#94a3b8', label: a.type, short: '??' };
                                const hl = isToday && nowMin !== null && nowMin >= toMin(a.from) && nowMin < toMin(a.to === '24:00' ? '23:59' : a.to);
                                return (
                                    <div key={i} className={`flex items-center gap-2.5 p-2.5 rounded-om border ${hl ? 'bg-om-downtime-bg border-om-downtime' : 'bg-om-panel border-om-line2'}`}>
                                        <div className="w-1 self-stretch rounded-sm" style={{ background: def.color }} />
                                        <div className="w-8 h-8 rounded-om-sm flex items-center justify-center flex-shrink-0" style={{ background: `${def.color}25` }}>
                                            <span className="font-mono text-[9px] font-bold tracking-wider" style={{ color: def.color }}>{def.short}</span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="text-sm font-semibold text-om-ink">{a.label ?? def.label}</span>
                                                {hl && <span className="font-mono text-[8.5px] px-1.5 py-0.5 rounded bg-om-downtime text-white font-bold tracking-wider">NOW</span>}
                                            </div>
                                            {a.wo && (
                                                <div className="font-mono text-[10px] text-om-muted mt-0.5">
                                                    {a.wo}{a.step ? ` · ${a.step}` : ''}
                                                </div>
                                            )}
                                        </div>
                                        <div className="text-right min-w-[80px]">
                                            <div className="font-mono text-[11px] text-om-muted font-semibold">{a.from} &rarr; {a.to}</div>
                                            <div className="font-mono text-[10px] mt-0.5 font-bold tracking-wider" style={{ color: def.color }}>{fmtMins(a.duration)}</div>
                                        </div>
                                        {a.id && (
                                            <button onClick={() => handleDelete(a.id)} className="p-1 text-om-faint hover:text-rose-500" title={__('Delete')}>
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22"/></svg>
                                            </button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {selectedWorkerId && (
                        <Link href={`/admin/schedule/employees/add?worker_id=${selectedWorkerId}&date=${date}`}
                           className="h-11 rounded-om border border-dashed border-om-line text-om-downtime font-mono text-[11.5px] font-bold tracking-wider uppercase flex items-center justify-center gap-2 hover:bg-om-downtime-bg">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.4"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4"/></svg>
                            {__('Add activity')}
                        </Link>
                    )}
                </section>

                {/* RIGHT: Spotlight panel */}
                <aside className="hidden lg:flex flex-col gap-3 bg-om-card border border-om-line2 rounded-2xl p-4">
                    {spotlight && (() => {
                        const def = typeMeta[spotlight.type] ?? { color: '#94a3b8', label: spotlight.type, short: '??' };
                        return (
                            <>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-sm" style={{ background: def.color }} />
                                        <span className="font-mono text-[10px] tracking-wider font-bold uppercase text-om-downtime">
                                            {__('Selected')} · {def.short}
                                        </span>
                                    </div>
                                    <h2 className="text-lg font-bold text-om-ink mt-1.5">{spotlight.label ?? def.label}</h2>
                                    <div className="font-mono text-[10.5px] text-om-muted mt-0.5">{def.label.toUpperCase()}</div>
                                </div>
                                <div className="rounded-om bg-om-panel p-3.5">
                                    <div className="flex justify-between items-baseline">
                                        <div>
                                            <div className="font-mono text-[9.5px] tracking-wider text-om-muted uppercase">{__('Duration')}</div>
                                            <div className="font-mono text-3xl font-bold mt-1 -tracking-wide" style={{ color: def.color }}>{fmtMins(spotlight.duration)}</div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-mono text-[9.5px] tracking-wider text-om-muted uppercase">{__('Window')}</div>
                                            <div className="font-mono text-sm font-bold mt-1 text-om-ink">{spotlight.from} &rarr; {spotlight.to}</div>
                                        </div>
                                    </div>
                                </div>
                                {spotlight.wo && (
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="p-2.5 rounded-om-sm bg-om-panel">
                                            <div className="font-mono text-[9px] tracking-wider text-om-muted uppercase">{__('Work Order')}</div>
                                            <div className="font-mono text-xs font-bold mt-1 text-om-ink">{spotlight.wo}</div>
                                        </div>
                                        <div className="p-2.5 rounded-om-sm bg-om-panel">
                                            <div className="font-mono text-[9px] tracking-wider text-om-muted uppercase">{__('Step')}</div>
                                            <div className="font-mono text-xs font-bold mt-1 text-om-ink">{spotlight.step ?? '—'}</div>
                                        </div>
                                    </div>
                                )}
                                {spotlight.notes && (
                                    <div>
                                        <div className="font-mono text-[10.5px] tracking-wider text-om-muted uppercase">{__('Notes')}</div>
                                        <div className="mt-1.5 p-3 rounded-om-sm bg-om-panel text-xs italic text-om-muted leading-relaxed">
                                            &ldquo;{spotlight.notes}&rdquo;
                                        </div>
                                    </div>
                                )}
                            </>
                        );
                    })()}
                    <div className="flex-1" />
                    {/* Day KPI strip */}
                    <div className="grid grid-cols-2 gap-2">
                        <div className="p-2.5 rounded-om-sm bg-om-panel">
                            <div className="font-mono text-[9px] tracking-wider text-om-muted uppercase">{__('Activities')}</div>
                            <div className="font-mono text-lg font-bold mt-1 text-om-ink">{activities.length}</div>
                        </div>
                        <div className="p-2.5 rounded-om-sm bg-om-panel">
                            <div className="font-mono text-[9px] tracking-wider text-om-muted uppercase">{__('Maint')}</div>
                            <div className="font-mono text-lg font-bold mt-1 text-rose-600">{fmtMins(sums.maint ?? 0)}</div>
                        </div>
                    </div>
                </aside>
            </div>
        </>
    );
}

EmployeeDay.layout = (page) => <AppLayout>{page}</AppLayout>;
