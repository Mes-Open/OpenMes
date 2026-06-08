import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { formatDate } from '../../../../lib/i18n';

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
            <div className="relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-zinc-900"
                 style={{ height: `${height}px` }}>
                {/* Hour grid lines */}
                {Array.from({ length: 24 }, (_, h) => (
                    <div key={h} className={`absolute top-0 bottom-0 ${h % 6 === 0 ? 'bg-gray-300 dark:bg-zinc-700' : 'bg-gray-200/50 dark:bg-zinc-800'}`}
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
                    <div className="absolute -top-1 -bottom-1 w-0.5 bg-amber-500 z-10"
                         style={{ left: `${(nowMinutes / totalMin) * 100}%` }}>
                        <div className="absolute -top-1 -left-1 w-2.5 h-2.5 rounded-full bg-white border-2 border-amber-500" />
                    </div>
                )}
            </div>
            {showHours && (
                <div className="flex justify-between mt-1 font-mono text-[9px] text-gray-500 tracking-wider dark:text-gray-400">
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
        { key: 'day', label: 'Day plan' },
        { key: 'team', label: 'Team day' },
        { key: 'month', label: 'Month' },
    ];

    const navTo = (params) => router.get('/admin/schedule/employees', params, { preserveState: false });

    return (
        <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <div className="font-mono text-[11px] tracking-wider font-bold uppercase text-amber-600 dark:text-amber-400">
                    Employee day planner · Tacho view
                </div>
                <h1 className="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-100 mt-0.5">
                    {view === 'team'
                        ? `Team day · ${formatDate(new Date(date), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}`
                        : view === 'month'
                            ? `Month overview · ${formatDate(new Date(date), { month: 'long', year: 'numeric' })}`
                            : <>{selectedWorker?.name ?? 'Day plan'} <span className="text-gray-400 dark:text-gray-500 font-normal text-lg">· {formatDate(new Date(date), { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}</span></>
                    }
                </h1>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <div className="inline-flex p-1 rounded-lg bg-gray-200 dark:bg-zinc-800">
                    {tabs.map(({ key, label }) => (
                        <button key={key}
                                onClick={() => navTo({ view: key, date, worker_id: selectedWorkerId })}
                                className={`px-3 py-1.5 rounded-md font-mono text-[11px] font-bold tracking-wider uppercase transition-colors ${view === key ? 'bg-amber-500 text-amber-950' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white'}`}>
                            {label}
                        </button>
                    ))}
                </div>

                <Link href="/admin/schedule" className="px-3 py-2 text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                    &larr; Production schedule
                </Link>

                {selectedWorkerId && (
                    <Link href={`/admin/schedule/employees/add?worker_id=${selectedWorkerId}&date=${date}`}
                       className="inline-flex items-center gap-2 px-3 h-9 rounded-lg bg-amber-500 hover:bg-amber-400 text-amber-950 font-mono text-xs font-bold tracking-wider uppercase">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.4"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add activity
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
        if (!confirm('Remove this activity?')) return;
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
            <Head title="Employee Day Plan" />

            <EmployeeTabs view={view} date={date} selectedWorkerId={selectedWorkerId} selectedWorker={selectedWorker} workers={workers} />

            <div className="grid grid-cols-1 lg:grid-cols-[260px_1fr_360px] gap-4">

                {/* LEFT: Worker list */}
                <aside className="hidden lg:flex flex-col bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-2xl p-4 min-h-[60vh]">
                    <div className="mb-3">
                        <div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 dark:bg-zinc-800">
                            <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" placeholder="Search worker"
                                   className="bg-transparent w-full text-sm text-gray-700 dark:text-gray-200 placeholder-gray-400 outline-none font-mono"
                                   onChange={(e) => {/* could filter locally */}} />
                        </div>
                    </div>
                    <div className="font-mono text-[10px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mt-1 mb-2">
                        Workers · {workers.length}
                    </div>
                    <div className="flex flex-col gap-1.5 overflow-y-auto flex-1">
                        {workers.map((w) => {
                            const on = w.id === selectedWorkerId;
                            const parts = (w.name ?? '').trim().split(' ');
                            const initials = ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
                            return (
                                <button key={w.id}
                                        onClick={() => navTo({ view: 'day', date, worker_id: w.id })}
                                        className={`flex items-center gap-3 p-2.5 rounded-lg border transition-colors text-left ${on ? 'bg-amber-50 border-amber-400 dark:bg-amber-500/10 dark:border-amber-500' : 'bg-gray-50 border-gray-200 hover:bg-gray-100 dark:bg-zinc-800 dark:border-zinc-700 dark:hover:bg-zinc-700'}`}>
                                    <div className={`w-8 h-8 rounded-lg font-mono text-[10px] font-bold flex items-center justify-center flex-shrink-0 ${on ? 'bg-amber-500 text-amber-950' : 'bg-gray-200 text-gray-700 dark:bg-zinc-700 dark:text-gray-200'}`}>
                                        {initials}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{w.name}</div>
                                        <div className="font-mono text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                            {w.code}{w.personnel_class_code ? ` · ${w.personnel_class_code}` : ''}
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                    <div className="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-zinc-800">
                        <div className="font-mono text-[9px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Shift coverage</div>
                        <div className="font-mono text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
                            {workers.length}<span className="text-sm text-gray-400 dark:text-gray-500">/{workers.length}</span>
                        </div>
                    </div>
                </aside>

                {/* CENTER: Timeline */}
                <section className="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-2xl p-4 md:p-5 flex flex-col gap-4 min-w-0">
                    {/* Date strip */}
                    <div className="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
                        {dayStrip.map((d) => {
                            const on = d === date;
                            return (
                                <button key={d} onClick={() => navTo({ view: 'day', date: d, worker_id: selectedWorkerId })}
                                        className={`flex-shrink-0 px-3 py-2 rounded-lg font-mono text-[11px] font-bold tracking-wider uppercase border ${on ? 'bg-amber-500 border-amber-500 text-amber-950' : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100 dark:bg-zinc-800 dark:border-zinc-700 dark:text-gray-300'}`}>
                                    {formatDate(new Date(d), { weekday: 'short', day: 'numeric' })}
                                </button>
                            );
                        })}
                    </div>

                    {/* Summary band */}
                    <div className="rounded-xl bg-gradient-to-br from-gray-50 to-white border border-gray-200 p-4 dark:from-zinc-800 dark:to-zinc-900 dark:border-zinc-700">
                        <div className="flex flex-wrap justify-between items-start gap-3">
                            <div>
                                <div className="font-mono text-[10px] tracking-wider font-bold uppercase text-amber-600 dark:text-amber-400">
                                    {formatDate(dateObj, { weekday: 'short', day: 'numeric', month: 'short' })} · A-shift
                                </div>
                                <div className="text-lg md:text-xl font-bold text-gray-800 dark:text-gray-100 mt-1">
                                    {fmtMins(totalWork + totalBreaks + (sums.maint ?? 0) + (sums.meeting ?? 0) + (sums.training ?? 0) + (sums.travel ?? 0))} planned
                                </div>
                                <div className="font-mono text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                    {activities.length} activities
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="font-mono text-xl md:text-2xl font-bold text-emerald-600 dark:text-emerald-400">{fmtMins(totalWork)}</div>
                                <div className="font-mono text-[9px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mt-0.5">Productive</div>
                            </div>
                        </div>
                        <div className="mt-4">
                            <Tacho activities={activities} typeMeta={typeMeta} height={56} isToday={isToday} nowMinutes={nowMin} />
                        </div>
                        <div className="flex flex-wrap justify-between gap-3 mt-4 font-mono text-[10.5px] text-gray-500 dark:text-gray-400">
                            <span>Σ <span className="font-bold text-gray-800 dark:text-gray-100">{fmtMins(totalWork)}</span> work</span>
                            <span><span className="font-bold text-gray-800 dark:text-gray-100">{fmtMins(totalBreaks)}</span> breaks</span>
                            <span><span className="font-bold text-rose-600 dark:text-rose-400">{fmtMins(sums.maint ?? 0)}</span> maint</span>
                            <span><span className="font-bold text-gray-800 dark:text-gray-100">{fmtMins(sums.off ?? 0)}</span> off</span>
                        </div>
                    </div>

                    {/* Type legend pills */}
                    <div className="flex flex-wrap gap-1.5">
                        {Object.entries(sums).map(([type, mins]) => {
                            const def = typeMeta[type];
                            if (!def) return null;
                            return (
                                <div key={type} className="flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-white dark:bg-zinc-900"
                                     style={{ borderColor: `${def.color}80` }}>
                                    <span className="w-2 h-2 rounded-sm" style={{ background: def.color }} />
                                    <span className="text-xs font-semibold text-gray-700 dark:text-gray-200">{def.label}</span>
                                    <span className="font-mono text-[10px] font-bold text-gray-500 dark:text-gray-400">{fmtMins(mins)}</span>
                                </div>
                            );
                        })}
                    </div>

                    {/* Activity list */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mb-2">
                            Activities · {activities.length}
                        </div>
                        <div className="flex flex-col gap-1">
                            {activities.length === 0 ? (
                                <div className="p-6 text-center text-sm text-gray-400 dark:text-gray-500 border border-dashed border-gray-200 dark:border-zinc-700 rounded-xl">
                                    No activities planned for this day.
                                </div>
                            ) : activities.map((a, i) => {
                                const def = typeMeta[a.type] ?? typeMeta.off ?? { color: '#94a3b8', label: a.type, short: '??' };
                                const hl = isToday && nowMin !== null && nowMin >= toMin(a.from) && nowMin < toMin(a.to === '24:00' ? '23:59' : a.to);
                                return (
                                    <div key={i} className={`flex items-center gap-2.5 p-2.5 rounded-xl border ${hl ? 'bg-amber-50 border-amber-400 dark:bg-amber-500/10 dark:border-amber-500' : 'bg-gray-50 border-gray-200 dark:bg-zinc-800 dark:border-zinc-700'}`}>
                                        <div className="w-1 self-stretch rounded-sm" style={{ background: def.color }} />
                                        <div className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style={{ background: `${def.color}25` }}>
                                            <span className="font-mono text-[9px] font-bold tracking-wider" style={{ color: def.color }}>{def.short}</span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">{a.label ?? def.label}</span>
                                                {hl && <span className="font-mono text-[8.5px] px-1.5 py-0.5 rounded bg-amber-500 text-amber-950 font-bold tracking-wider">NOW</span>}
                                            </div>
                                            {a.wo && (
                                                <div className="font-mono text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {a.wo}{a.step ? ` · ${a.step}` : ''}
                                                </div>
                                            )}
                                        </div>
                                        <div className="text-right min-w-[80px]">
                                            <div className="font-mono text-[11px] text-gray-700 dark:text-gray-200 font-semibold">{a.from} &rarr; {a.to}</div>
                                            <div className="font-mono text-[10px] mt-0.5 font-bold tracking-wider" style={{ color: def.color }}>{fmtMins(a.duration)}</div>
                                        </div>
                                        {a.id && (
                                            <button onClick={() => handleDelete(a.id)} className="p-1 text-gray-400 hover:text-rose-500" title="Delete">
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
                           className="h-11 rounded-xl border border-dashed border-gray-300 dark:border-zinc-700 text-amber-600 dark:text-amber-400 font-mono text-[11.5px] font-bold tracking-wider uppercase flex items-center justify-center gap-2 hover:bg-amber-50 dark:hover:bg-amber-500/5">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.4"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add activity
                        </Link>
                    )}
                </section>

                {/* RIGHT: Spotlight panel */}
                <aside className="hidden lg:flex flex-col gap-3 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-2xl p-4">
                    {spotlight && (() => {
                        const def = typeMeta[spotlight.type] ?? { color: '#94a3b8', label: spotlight.type, short: '??' };
                        return (
                            <>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-sm" style={{ background: def.color }} />
                                        <span className="font-mono text-[10px] tracking-wider font-bold uppercase text-amber-600 dark:text-amber-400">
                                            Selected · {def.short}
                                        </span>
                                    </div>
                                    <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mt-1.5">{spotlight.label ?? def.label}</h2>
                                    <div className="font-mono text-[10.5px] text-gray-500 dark:text-gray-400 mt-0.5">{def.label.toUpperCase()}</div>
                                </div>
                                <div className="rounded-xl bg-gray-50 dark:bg-zinc-800 p-3.5">
                                    <div className="flex justify-between items-baseline">
                                        <div>
                                            <div className="font-mono text-[9.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Duration</div>
                                            <div className="font-mono text-3xl font-bold mt-1 -tracking-wide" style={{ color: def.color }}>{fmtMins(spotlight.duration)}</div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-mono text-[9.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Window</div>
                                            <div className="font-mono text-sm font-bold mt-1 text-gray-800 dark:text-gray-100">{spotlight.from} &rarr; {spotlight.to}</div>
                                        </div>
                                    </div>
                                </div>
                                {spotlight.wo && (
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="p-2.5 rounded-lg bg-gray-50 dark:bg-zinc-800">
                                            <div className="font-mono text-[9px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Work Order</div>
                                            <div className="font-mono text-xs font-bold mt-1 text-gray-800 dark:text-gray-100">{spotlight.wo}</div>
                                        </div>
                                        <div className="p-2.5 rounded-lg bg-gray-50 dark:bg-zinc-800">
                                            <div className="font-mono text-[9px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Step</div>
                                            <div className="font-mono text-xs font-bold mt-1 text-gray-800 dark:text-gray-100">{spotlight.step ?? '—'}</div>
                                        </div>
                                    </div>
                                )}
                                {spotlight.notes && (
                                    <div>
                                        <div className="font-mono text-[10.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Notes</div>
                                        <div className="mt-1.5 p-3 rounded-lg bg-gray-50 dark:bg-zinc-800 text-xs italic text-gray-600 dark:text-gray-300 leading-relaxed">
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
                        <div className="p-2.5 rounded-lg bg-gray-50 dark:bg-zinc-800">
                            <div className="font-mono text-[9px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Activities</div>
                            <div className="font-mono text-lg font-bold mt-1 text-gray-800 dark:text-gray-100">{activities.length}</div>
                        </div>
                        <div className="p-2.5 rounded-lg bg-gray-50 dark:bg-zinc-800">
                            <div className="font-mono text-[9px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Maint</div>
                            <div className="font-mono text-lg font-bold mt-1 text-rose-600 dark:text-rose-400">{fmtMins(sums.maint ?? 0)}</div>
                        </div>
                    </div>
                </aside>
            </div>
        </>
    );
}

EmployeeDay.layout = (page) => <AppLayout>{page}</AppLayout>;
