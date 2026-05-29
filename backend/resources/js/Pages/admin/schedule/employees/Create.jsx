import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';

function toMin(t) {
    if (!t) return 0;
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
}
function fmtDuration(from, to) {
    const diff = Math.max(0, toMin(to) - toMin(from));
    return `${String(Math.floor(diff / 60)).padStart(2, '0')}:${String(diff % 60).padStart(2, '0')}`;
}

export default function EmployeeCreate() {
    const {
        worker, date, workOrders = [], customTypes = [], typeMeta = {},
        defaultFrom, defaultTo, errors = {},
    } = usePage().props;

    const types = Object.entries(typeMeta)
        .filter(([k]) => k !== 'custom')
        .map(([key, v]) => ({ key, ...v }));
    const customs = customTypes.map((c) => ({ code: c.code, label: c.label, color: c.color }));

    const [selectedType, setSelectedType] = useState('work');
    const [selectedCustom, setSelectedCustom] = useState('');
    const [fromTime, setFromTime] = useState(defaultFrom ?? '08:00');
    const [toTime, setToTime] = useState(defaultTo ?? '09:00');

    const dateObj = new Date(date);

    const handleSubmit = (e) => {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.set('type', selectedType);
        data.set('custom_code', selectedCustom);
        router.post('/admin/schedule/employees', Object.fromEntries(data));
    };

    return (
        <>
            <Head title="Add Activity" />
            <div className="max-w-2xl mx-auto">
                <div className="mb-4">
                    <div className="font-mono text-[11px] tracking-wider font-bold uppercase text-amber-600 dark:text-amber-400">
                        {worker?.name} · {dateObj.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })}
                    </div>
                    <h1 className="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-100 mt-0.5">Add activity</h1>
                </div>

                {Object.keys(errors).length > 0 && (
                    <div className="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm dark:bg-rose-500/10 dark:border-rose-500/50 dark:text-rose-300 mb-4">
                        <ul className="list-disc list-inside">
                            {Object.values(errors).map((err, i) => <li key={i}>{err}</li>)}
                        </ul>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-5">
                    <input type="hidden" name="worker_id" value={worker?.id} />
                    <input type="hidden" name="date" value={date} />

                    {/* Type tile grid */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mb-2">Type</div>
                        <div className="grid grid-cols-3 gap-2">
                            {types.map((t) => {
                                const on = selectedType === t.key && !selectedCustom;
                                return (
                                    <button key={t.key} type="button"
                                            onClick={() => { setSelectedType(t.key); setSelectedCustom(''); }}
                                            className={`p-3 rounded-xl flex flex-col items-center gap-1.5 transition-colors ${on ? 'border-2' : 'border bg-white dark:bg-zinc-900 border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-800'}`}
                                            style={on ? { background: `${t.color}1a`, borderColor: t.color } : {}}>
                                        <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{ background: `${t.color}30` }}>
                                            <span className="font-mono text-[10px] font-bold tracking-wider" style={{ color: t.color }}>{t.short}</span>
                                        </div>
                                        <span className={`font-mono text-[10px] font-bold tracking-wider uppercase ${on ? '' : 'text-gray-600 dark:text-gray-300'}`} style={{ color: on ? t.color : undefined }}>
                                            {t.short}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Custom pills */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mb-2">Custom</div>
                        <div className="flex flex-wrap gap-1.5">
                            {customs.length === 0 ? (
                                <div className="font-mono text-[10px] text-gray-400 italic px-3 py-1.5">No custom activity types defined.</div>
                            ) : customs.map((c) => {
                                const on = selectedCustom === c.code;
                                return (
                                    <button key={c.code} type="button"
                                            onClick={() => { setSelectedType('custom'); setSelectedCustom(c.code); }}
                                            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full ${on ? 'border-2' : 'border'}`}
                                            style={{ borderColor: `${c.color}60`, background: on ? `${c.color}15` : 'transparent' }}>
                                        <span className="w-2 h-2 rounded-sm" style={{ background: c.color }} />
                                        <span className="text-xs font-semibold text-gray-700 dark:text-gray-200">{c.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Time range */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mb-2">Time range</div>
                        <div className="grid grid-cols-2 gap-2">
                            <label className="p-3.5 rounded-xl bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700">
                                <div className="font-mono text-[9.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">From</div>
                                <input type="time" name="from_time" value={fromTime} onChange={(e) => setFromTime(e.target.value)} required
                                       className="font-mono text-2xl font-bold mt-1 bg-transparent text-gray-800 dark:text-gray-100 outline-none w-full -tracking-wide" />
                            </label>
                            <label className="p-3.5 rounded-xl bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700">
                                <div className="font-mono text-[9.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase">To</div>
                                <input type="time" name="to_time" value={toTime} onChange={(e) => setToTime(e.target.value)} required
                                       className="font-mono text-2xl font-bold mt-1 bg-transparent text-gray-800 dark:text-gray-100 outline-none w-full -tracking-wide" />
                            </label>
                        </div>
                        <div className="mt-2 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 font-mono text-xs tracking-wider text-amber-700 dark:text-amber-300 text-center font-bold uppercase">
                            Duration {fmtDuration(fromTime, toTime)}
                        </div>
                    </div>

                    {/* Optional WO link */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mb-2">
                            Link to work order · optional
                        </div>
                        <select name="work_order_id" className="form-input w-full bg-white dark:bg-zinc-900 border-gray-200 dark:border-zinc-700">
                            <option value="">— None —</option>
                            {workOrders.map((wo) => (
                                <option key={wo.id} value={wo.id}>{wo.order_no} — {wo.product_name ?? '—'}</option>
                            ))}
                        </select>
                    </div>

                    {/* Label override */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mb-2">Label override</div>
                        <input type="text" name="label" placeholder="e.g. Lunch, Shift handover"
                               className="form-input w-full bg-white dark:bg-zinc-900 border-gray-200 dark:border-zinc-700" />
                    </div>

                    {/* Notes */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-gray-500 dark:text-gray-400 uppercase mb-2">Notes</div>
                        <textarea name="notes" rows="3" placeholder="Optional context…"
                                  className="form-input w-full bg-white dark:bg-zinc-900 border-gray-200 dark:border-zinc-700" />
                    </div>

                    <div className="flex gap-2">
                        <a href={`/admin/schedule/employees?view=day&date=${date}&worker_id=${worker?.id}`}
                           className="flex-1 h-12 rounded-xl border border-gray-200 dark:border-zinc-700 text-gray-700 dark:text-gray-200 font-mono text-xs font-bold tracking-wider uppercase flex items-center justify-center">
                            Cancel
                        </a>
                        <button type="submit"
                                className="flex-[2] h-12 rounded-xl bg-amber-500 hover:bg-amber-400 text-amber-950 font-mono text-xs font-bold tracking-wider uppercase">
                            Save activity
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}

EmployeeCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
