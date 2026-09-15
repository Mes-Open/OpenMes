import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { cloneElement, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

const base = '/admin/connectivity/counters';
const input = 'border border-om-line2 rounded px-3 py-2 bg-om-surface text-om-ink w-full';
const button = 'rounded bg-om-accent text-white px-4 py-2 disabled:opacity-50';
const statuses = {
    configured: 'Configured', baseline: 'Baseline established', applied: 'Applied', unchanged: 'Unchanged',
    unconfigured: 'Configuration required', unassigned: 'Unassigned', blocked: 'Step blocked', partial: 'Partially applied',
    reset_required: 'Reset review required', source_changed: 'Source changed', out_of_order: 'Out-of-order reading',
    future_reading: 'Future reading', event_id_required: 'Event ID required', timestamp_required: 'Timestamp required',
    invalid_value: 'Invalid counter value', invalid_pulse: 'Pulse must equal one', quality_unknown: 'Quality review required',
    rebaseline: 'Baseline requested', reconciled: 'Reconciled', dismissed: 'Dismissed',
};
function Errors({ errors }) {
    return Object.entries(errors).map(([key, value]) => <p key={key} role="alert" className="text-red-600 text-sm">{value}</p>);
}
function Field({ label, children }) {
    return <label className="block space-y-1"><span className="text-sm text-om-muted">{__(label)}</span>{cloneElement(children, { 'aria-label': __(label) })}</label>;
}
function Configuration({ counter, workstations, steps }) {
    const form = useForm({ mode: counter.mode, kind: counter.kind, workstation_id: counter.workstation_id ?? '', batch_step_id: counter.batch_step_id ?? '', note: '' });
    const options = steps.filter(s => s.workstation_id === Number(form.data.workstation_id));
    return <form aria-label={__('Counter configuration')} className="space-y-3" onSubmit={e => { e.preventDefault(); form.put(`${base}/${counter.id}`, { preserveScroll: true }); }}>
        <div className="grid md:grid-cols-2 gap-3">
            <Field label="Counter mode"><select className={input} value={form.data.mode} onChange={e => form.setData('mode', e.target.value)}><option value="cumulative">{__('Cumulative')}</option><option value="increment">{__('Increment')}</option><option value="pulse">{__('Pulse')}</option></select></Field>
            <Field label="Count quality"><select className={input} value={form.data.kind} onChange={e => form.setData('kind', e.target.value)}><option value="good">{__('Good')}</option><option value="reject">{__('Reject')}</option><option value="total">{__('Total (quality unknown)')}</option></select></Field>
            <Field label="Workstation"><select className={input} required value={form.data.workstation_id} onChange={e => form.setData({ ...form.data, workstation_id: e.target.value, batch_step_id: '' })}><option value="">{__('Select workstation')}</option>{workstations.map(w => <option key={w.id} value={w.id}>{w.name}</option>)}</select></Field>
            <Field label="Assigned batch step"><select className={input} value={form.data.batch_step_id} onChange={e => form.setData('batch_step_id', e.target.value)}><option value="">{__('Unassigned')}</option>{options.map(s => <option key={s.id} value={s.id}>{s.label}</option>)}</select></Field>
        </div>
        <p className="text-sm text-om-muted">{__('After a configuration change, the next cumulative reading establishes the baseline. Earlier readings are never replayed automatically.')}</p>
        <Field label="Reason"><input className={input} required maxLength={1000} value={form.data.note} onChange={e => form.setData('note', e.target.value)} /></Field>
        <Errors errors={form.errors} /><button className={button} disabled={form.processing}>{__('Save counter configuration')}</button>
    </form>;
}
function Baseline({ counter }) {
    const form = useForm({ note: '' });
    return <form aria-label={__('Reset review')} className="space-y-2" onSubmit={e => { e.preventDefault(); form.post(`${base}/${counter.id}/rebaseline`, { preserveScroll: true, onSuccess: () => form.reset() }); }}>
        <p className="text-sm text-om-muted">{__('Confirm a reset only after checking the machine. The next fresh reading becomes the baseline and adds no production.')}</p>
        <Field label="Reset reason"><input className={input} required maxLength={1000} value={form.data.note} onChange={e => form.setData('note', e.target.value)} /></Field>
        <Errors errors={form.errors} /><button className={button} disabled={form.processing}>{__('Establish new baseline')}</button>
    </form>;
}
function Simulator({ counter }) {
    const form = useForm({ value: '', event_id: '', timestamp: '' });
    return <form aria-label={__('Test reading')} className="space-y-3" onSubmit={e => { e.preventDefault(); form.post(`${base}/${counter.id}/simulate`, { preserveScroll: true }); }}>
        <h2 className="font-semibold">{__('Local test readings')}</h2>
        <p className="text-sm text-om-muted">{__('These readings change the demo order. Simulation is available only for specially created test sources.')}</p>
        <div className="grid md:grid-cols-3 gap-3">
            <Field label="Raw count"><input className={input} type="number" step="0.01" min="0" required value={form.data.value} onChange={e => form.setData('value', e.target.value)} /></Field>
            <Field label="Event ID"><input className={input} maxLength={160} value={form.data.event_id} onChange={e => form.setData('event_id', e.target.value)} /></Field>
            <Field label="Timestamp (optional ISO 8601)"><input className={input} value={form.data.timestamp} onChange={e => form.setData('timestamp', e.target.value)} /></Field>
        </div>
        <Errors errors={form.errors} /><button className={button} disabled={form.processing}>{__('Send test reading')}</button>
    </form>;
}
function Review({ counter, reading, steps, close }) {
    const form = useForm({ decision: 'dismiss', batch_step_id: reading.batch_step_id ?? '', note: '' });
    const canApply = reading.payload?.kind === 'good' && Number(reading.delta) > Number(reading.applied_qty);
    return <form aria-label={__('Review reading')} className="p-4 border border-om-line2 space-y-3" onSubmit={e => { e.preventDefault(); form.post(`${base}/${counter.id}/readings/${reading.id}/review`, { preserveScroll: true, onSuccess: close }); }}>
        <h3 className="font-semibold">{__('Review reading')} #{reading.id}</h3>
        <Field label="Decision"><select className={input} value={form.data.decision} onChange={e => form.setData('decision', e.target.value)}><option value="dismiss">{__('Dismiss with reason')}</option>{canApply && <option value="apply">{__('Apply remaining good quantity')}</option>}</select></Field>
        {form.data.decision === 'apply' && <Field label="Assigned batch step"><select required className={input} value={form.data.batch_step_id} onChange={e => form.setData('batch_step_id', e.target.value)}><option value="">{__('Select step')}</option>{steps.filter(s => s.workstation_id === counter.workstation_id).map(s => <option key={s.id} value={s.id}>{s.label}</option>)}</select></Field>}
        <Field label="Review reason"><input required maxLength={1000} className={input} value={form.data.note} onChange={e => form.setData('note', e.target.value)} /></Field>
        <Errors errors={form.errors} /><div className="flex gap-3"><button className={button} disabled={form.processing}>{__('Save review')}</button><button type="button" onClick={close}>{__('Cancel')}</button></div>
    </form>;
}
export default function Counters() {
    const { counters, selectedId, sources, workstations, steps, readings, canSimulate } = usePage().props;
    const counter = counters.find(c => c.id === selectedId);
    const register = useForm({ source_type: 'tag', source_id: '' });
    const [review, setReview] = useState(null);
    const assigned = steps.find(s => s.id === counter?.batch_step_id);
    return <><Head title={__('Machine counters')} /><div className="p-4 md:p-6 max-w-7xl mx-auto space-y-6">
        <div className="flex flex-wrap justify-between gap-2"><h1 className="text-2xl font-semibold">{__('Machine counters')}</h1><Link href="/admin/connectivity">{__('Machine Connectivity')}</Link><button onClick={() => router.reload({ preserveScroll: true })}>{__('Refresh')}</button></div>
        <p className="text-om-muted">{__('Assign each counting channel to one batch step. Only confirmed good output advances production; total and reject counts remain available for quality review.')}</p>
        <form aria-label={__('Register counter')} className="flex flex-wrap items-end gap-3" onSubmit={e => { e.preventDefault(); register.post(base); }}>
            <Field label="Machine source"><select className={input} required value={`${register.data.source_type}:${register.data.source_id}`} onChange={e => { const [source_type, source_id] = e.target.value.split(':'); register.setData({ source_type, source_id }); }}><option value="tag:">{__('Select source')}</option>{sources.map(s => <option key={`${s.type}:${s.id}`} value={`${s.type}:${s.id}`}>{s.label} ({s.type} #{s.id})</option>)}</select></Field>
            <button className={button} disabled={register.processing}>{__('Open counter')}</button><Errors errors={register.errors} />
        </form>
        <nav className="flex flex-wrap gap-2">{counters.map(c => <Link key={c.id} href={`${base}?counter=${c.id}`} onClick={() => setReview(null)} className={`px-3 py-2 rounded border ${c.id === selectedId ? 'border-om-accent text-om-accent' : 'border-om-line2'}`}>{c.label} #{c.id}</Link>)}</nav>
        {counter && <>
            <section className="rounded border border-om-line2 p-4 space-y-4">
                <h2 className="font-semibold">{counter.label} — {counter.connection?.name}</h2>
                <div className="flex flex-wrap gap-6 text-sm"><span>{__('Last raw count')}: <strong data-testid="last-raw">{counter.last_raw ?? '—'}</strong></span><span>{__('Assigned batch step')}: {assigned?.label ?? counter.batch_step_id ?? __('Unassigned')}</span><span>{__('Good')}: <strong data-testid="step-good">{assigned?.passed_qty ?? '—'}</strong></span></div>
                {counter.reset_required && <p role="alert" className="text-red-600 font-semibold">{__('Reset review required')}</p>}
                <Configuration key={`${counter.id}:${counter.updated_at}`} {...{ counter, workstations, steps }} />
            </section>
            {counter.mode === 'cumulative' && <section className="rounded border border-om-line2 p-4"><Baseline key={counter.id} counter={counter} /></section>}
            {canSimulate && <section className="rounded border border-om-line2 p-4"><Simulator key={counter.id} counter={counter} /></section>}
            {review && <Review key={review.id} {...{ counter, reading: review, steps }} close={() => setReview(null)} />}
            <section className="space-y-3"><h2 className="font-semibold">{__('Reading history')}</h2><div className="flex gap-4"><Link href={`${base}?counter=${counter.id}`}>{__('All readings')}</Link><Link href={`${base}?counter=${counter.id}&review=1`}>{__('Awaiting review')}</Link></div><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr>{['ID', 'Time', 'Raw count', 'Delta', 'Applied quantity', 'Status', 'Batch step', 'Review'].map(h => <th key={h} className="p-2 text-left">{__(h)}</th>)}</tr></thead><tbody>{readings?.data.map(r => <tr key={r.id} className="border-t border-om-line2" data-reading-id={r.id}><td className="p-2">{r.id}</td><td className="p-2 whitespace-nowrap">{new Date(r.observed_at).toLocaleString()}<div className="text-om-muted">{r.event_id}</div></td><td className="p-2">{r.raw_value ?? '—'}</td><td className="p-2">{r.delta}</td><td className="p-2">{r.applied_qty}</td><td className="p-2">{__(statuses[r.status] ?? r.status)}</td><td className="p-2">{r.batch_step_id ?? '—'}</td><td className="p-2">{r.review_note ?? (!r.reviewed_at && ['unassigned', 'blocked', 'partial', 'quality_unknown'].includes(r.status) && <button className="text-om-accent underline" onClick={() => setReview(r)}>{__('Review')}</button>)}</td></tr>)}</tbody></table></div>
                <div className="flex gap-4">{readings?.prev_page_url && <Link href={readings.prev_page_url}>{__('Previous')}</Link>}{readings?.next_page_url && <Link href={readings.next_page_url}>{__('Next')}</Link>}</div>
            </section>
        </>}
    </div></>;
}
Counters.layout = page => <AppLayout>{page}</AppLayout>;
