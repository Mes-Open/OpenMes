import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import AppLayout from '../../../../layouts/AppLayout';
import { formatDate, __ } from '../../../../lib/i18n';

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
    const [workOrderId, setWorkOrderId] = useState('');

    const dateObj = new Date(date);

    const handleSubmit = (e) => {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.set('type', selectedType);
        data.set('custom_code', selectedCustom);
        data.set('work_order_id', workOrderId);
        router.post('/admin/schedule/employees', Object.fromEntries(data));
    };

    return (
        <>
            <Head title={__('Add activity')} />
            <div className="max-w-2xl mx-auto">
                <div className="mb-4">
                    <div className="font-mono text-[11px] tracking-wider font-bold uppercase text-om-accent">
                        {worker?.name} · {formatDate(dateObj, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })}
                    </div>
                    <h1 className="text-2xl md:text-3xl font-bold text-om-ink mt-0.5">{__('Add activity')}</h1>
                </div>

                {Object.keys(errors).length > 0 && (
                    <div className="p-3 rounded-om-sm bg-om-blocked-bg border border-om-blocked text-om-blocked text-sm mb-4">
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
                        <div className="font-mono text-[10.5px] tracking-wider text-om-muted uppercase mb-2">{__('Type')}</div>
                        <div className="grid grid-cols-3 gap-2">
                            {types.map((t) => {
                                const on = selectedType === t.key && !selectedCustom;
                                return (
                                    <button key={t.key} type="button"
                                            onClick={() => { setSelectedType(t.key); setSelectedCustom(''); }}
                                            className={`p-3 rounded-om flex flex-col items-center gap-1.5 transition-colors ${on ? 'border-2' : 'border bg-om-card border-om-line2 hover:bg-om-bg'}`}
                                            style={on ? { background: `${t.color}1a`, borderColor: t.color } : {}}>
                                        <div className="w-8 h-8 rounded-om-sm flex items-center justify-center" style={{ background: `${t.color}30` }}>
                                            <span className="font-mono text-[10px] font-bold tracking-wider" style={{ color: t.color }}>{t.short}</span>
                                        </div>
                                        <span className={`font-mono text-[10px] font-bold tracking-wider uppercase ${on ? '' : 'text-om-muted'}`} style={{ color: on ? t.color : undefined }}>
                                            {t.short}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Custom pills */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-om-muted uppercase mb-2">{__('Custom')}</div>
                        <div className="flex flex-wrap gap-1.5">
                            {customs.length === 0 ? (
                                <div className="font-mono text-[10px] text-om-faint italic px-3 py-1.5">{__('No custom activity types defined.')}</div>
                            ) : customs.map((c) => {
                                const on = selectedCustom === c.code;
                                return (
                                    <button key={c.code} type="button"
                                            onClick={() => { setSelectedType('custom'); setSelectedCustom(c.code); }}
                                            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full ${on ? 'border-2' : 'border'}`}
                                            style={{ borderColor: `${c.color}60`, background: on ? `${c.color}15` : 'transparent' }}>
                                        <span className="w-2 h-2 rounded-sm" style={{ background: c.color }} />
                                        <span className="text-xs font-semibold text-om-muted">{c.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Time range */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-om-muted uppercase mb-2">{__('Time range')}</div>
                        <div className="grid grid-cols-2 gap-2">
                            <label className="p-3.5 rounded-om bg-om-card border border-om-line2">
                                <div className="font-mono text-[9.5px] tracking-wider text-om-muted uppercase">{__('From')}</div>
                                <input type="time" name="from_time" value={fromTime} onChange={(e) => setFromTime(e.target.value)} required
                                       className="font-mono text-2xl font-bold mt-1 bg-transparent text-om-ink outline-none w-full -tracking-wide" />
                            </label>
                            <label className="p-3.5 rounded-om bg-om-card border border-om-line2">
                                <div className="font-mono text-[9.5px] tracking-wider text-om-muted uppercase">{__('To')}</div>
                                <input type="time" name="to_time" value={toTime} onChange={(e) => setToTime(e.target.value)} required
                                       className="font-mono text-2xl font-bold mt-1 bg-transparent text-om-ink outline-none w-full -tracking-wide" />
                            </label>
                        </div>
                        <div className="mt-2 px-3 py-2 rounded-om-sm bg-om-accent-bg font-mono text-xs tracking-wider text-om-accent text-center font-bold uppercase">
                            {__('Duration')} {fmtDuration(fromTime, toTime)}
                        </div>
                    </div>

                    {/* Optional WO link */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-om-muted uppercase mb-2">
                            {__('Link to work order · optional')}
                        </div>
                        <Dropdown
                            options={[
                                { value: '', label: __('— None —') },
                                ...workOrders.map((wo) => ({ value: String(wo.id), label: `${wo.order_no} — ${wo.product_name ?? '—'}` })),
                            ]}
                            value={workOrderId}
                            onChange={(v) => setWorkOrderId(v)}
                            className="w-full"
                        />
                    </div>

                    {/* Label override */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-om-muted uppercase mb-2">{__('Label override')}</div>
                        <input type="text" name="label" placeholder={__('e.g. Lunch, Shift handover')}
                               className="form-input w-full bg-om-card border-om-line2" />
                    </div>

                    {/* Notes */}
                    <div>
                        <div className="font-mono text-[10.5px] tracking-wider text-om-muted uppercase mb-2">{__('Notes')}</div>
                        <textarea name="notes" rows="3" placeholder={__('Optional context…')}
                                  className="form-input w-full bg-om-card border-om-line2" />
                    </div>

                    <div className="flex gap-2">
                        <a href={`/admin/schedule/employees?view=day&date=${date}&worker_id=${worker?.id}`}
                           className="flex-1 h-12 rounded-om border border-om-line2 text-om-muted font-mono text-xs font-bold tracking-wider uppercase flex items-center justify-center">
                            {__('Cancel')}
                            {__('Cancel')}
                        </a>
                        <button type="submit"
                                className="flex-[2] h-12 rounded-om bg-om-accent hover:brightness-95 text-white font-mono text-xs font-bold tracking-wider uppercase">
                            {__('Save activity')}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}

EmployeeCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
