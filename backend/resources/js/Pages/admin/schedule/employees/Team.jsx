import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { Tacho, EmployeeTabs } from './Day';

function fmtMins(m) {
    return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}

const ON_DUTY_TYPES = ['work', 'setup', 'qc', 'maint', 'training', 'meeting'];

export default function EmployeeTeam() {
    const {
        view, date, workers = [], selectedWorker, selectedWorkerId,
        teamActivities = {}, customTypes = [], typeMeta = {},
    } = usePage().props;

    const isToday = date === new Date().toISOString().slice(0, 10);
    const nowMin = isToday ? (new Date().getHours() * 60 + new Date().getMinutes()) : null;

    const onDutyByWorker = {};
    Object.entries(teamActivities).forEach(([wId, acts]) => {
        onDutyByWorker[wId] = acts.filter(a => ON_DUTY_TYPES.includes(a.type)).reduce((s, a) => s + (a.duration ?? 0), 0);
    });

    const navTo = (params) => router.get('/admin/schedule/employees', params, { preserveState: false });

    const hourLabels = [0, 3, 6, 9, 12, 15, 18, 21];

    return (
        <>
            <Head title="Team Day" />
            <EmployeeTabs view={view} date={date} selectedWorkerId={selectedWorkerId} selectedWorker={selectedWorker} workers={workers} />

            <div className="flex flex-col gap-3">
                {/* Hour ruler */}
                <div className="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl p-3.5">
                    <div className="grid gap-3.5 items-center" style={{ gridTemplateColumns: '160px 1fr 80px' }}>
                        <div className="font-mono text-[9.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">Worker</div>
                        <div className="relative h-4">
                            {hourLabels.map((h) => (
                                <div key={h} className="absolute font-mono text-[9px] font-bold tracking-wider text-gray-500 dark:text-gray-400"
                                     style={{ left: `${(h / 24) * 100}%` }}>
                                    {String(h).padStart(2, '0')}:00
                                </div>
                            ))}
                        </div>
                        <div className="font-mono text-[9.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase text-right">On duty</div>
                    </div>
                </div>

                {/* Worker rows */}
                <div className="flex flex-col gap-2">
                    {workers.length === 0 ? (
                        <div className="p-8 text-center text-sm text-gray-400 dark:text-gray-500 border border-dashed border-gray-200 dark:border-zinc-700 rounded-xl">
                            No workers configured.
                        </div>
                    ) : workers.map((w) => {
                        const acts = teamActivities[w.id] ?? [];
                        const onDuty = onDutyByWorker[w.id] ?? 0;
                        const primary = w.id === selectedWorkerId;
                        const parts = (w.name ?? '').trim().split(' ');
                        const initials = ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
                        return (
                            <button key={w.id}
                                    onClick={() => navTo({ view: 'day', date, worker_id: w.id })}
                                    className={`grid gap-3.5 items-center p-3 rounded-xl border transition-colors text-left ${primary ? 'bg-amber-50 border-amber-400 dark:bg-amber-500/10 dark:border-amber-500' : 'bg-white border-gray-200 hover:bg-gray-50 dark:bg-zinc-900 dark:border-zinc-700 dark:hover:bg-zinc-800'}`}
                                    style={{ gridTemplateColumns: '160px 1fr 80px' }}>
                                <div className="flex items-center gap-2.5 min-w-0">
                                    <div className={`w-9 h-9 rounded-lg font-mono text-[11px] font-bold flex items-center justify-center flex-shrink-0 ${primary ? 'bg-amber-500 text-amber-950' : 'bg-gray-200 text-gray-700 dark:bg-zinc-700 dark:text-gray-200'}`}>
                                        {initials}
                                    </div>
                                    <div className="min-w-0">
                                        <div className="text-sm font-bold text-gray-800 dark:text-gray-100 truncate">{w.name}</div>
                                        <div className={`font-mono text-[9px] mt-0.5 truncate ${primary ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400'}`}>
                                            {w.code}{w.personnel_class_code ? ` · ${w.personnel_class_code}` : ''}
                                        </div>
                                    </div>
                                </div>
                                <div className="min-w-0">
                                    <Tacho activities={acts} typeMeta={typeMeta} height={42} showHours={false} isToday={isToday} nowMinutes={nowMin} />
                                </div>
                                <div className="text-right">
                                    <div className="font-mono text-base font-bold text-emerald-600 dark:text-emerald-400 -tracking-wide">{fmtMins(onDuty)}</div>
                                    <div className="font-mono text-[8.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mt-0.5">On duty</div>
                                </div>
                            </button>
                        );
                    })}
                </div>

                {/* Legend */}
                <div className="flex flex-wrap gap-3 px-3.5 py-2.5 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono text-[9.5px] tracking-wide text-gray-600 dark:text-gray-300 uppercase">
                    {Object.entries(typeMeta).filter(([k]) => !['off', 'custom'].includes(k)).map(([k, def]) => (
                        <span key={k} className="flex items-center gap-1.5">
                            <span className="w-3 h-2 rounded-sm" style={{ background: def.color }} />
                            {def.label}
                        </span>
                    ))}
                </div>
            </div>
        </>
    );
}

EmployeeTeam.layout = (page) => <AppLayout>{page}</AppLayout>;
