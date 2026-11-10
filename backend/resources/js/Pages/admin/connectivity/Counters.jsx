import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { cloneElement, useMemo, useState } from 'react';
import { Button, Dropdown } from '@openmes/ui';
import AppDataTable from '../../../components/AppDataTable';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatNumber } from '../../../lib/i18n';
import { StatCard } from './ui';

const base = '/admin/connectivity/counters';
const input = 'form-input w-full text-sm';
const statuses = {
    legacy_incompatible: 'Explicit counting required', legacy_enabled: 'Legacy counting enabled', configured: 'Configured', baseline: 'Baseline established', applied: 'Applied', unchanged: 'Unchanged',
    unconfigured: 'Configuration required', unassigned: 'Unassigned', blocked: 'Step blocked', partial: 'Partially applied',
    reset_required: 'Reset review required', source_changed: 'Source changed', out_of_order: 'Out-of-order reading',
    future_reading: 'Future reading', event_id_required: 'Event ID required', timestamp_required: 'Timestamp required',
    invalid_value: 'Invalid counter value', invalid_pulse: 'Pulse must equal one', quality_unknown: 'Quality review required',
    rebaseline: 'Baseline requested', reconciled: 'Reconciled', dismissed: 'Dismissed',
};
const MODES = { cumulative: 'Cumulative', increment: 'Increment', pulse: 'Pulse' };
const KINDS = { good: 'Good', reject: 'Reject', total: 'Total (quality unknown)' };
const REVIEWABLE = ['unassigned', 'blocked', 'partial', 'quality_unknown'];

function Errors({ errors }) {
    return Object.entries(errors).map(([key, value]) => <p key={key} role="alert" className="text-om-blocked text-sm">{value}</p>);
}
function Field({ label, children }) {
    return <label className="block space-y-1"><span className="text-xs font-medium text-om-muted">{__(label)}</span>{cloneElement(children, { 'aria-label': __(label) })}</label>;
}
/** A titled card; every block of the counter screen sits in one. */
function Card({ title, aside, children, className = '' }) {
    return (
        <section className={`bg-om-card rounded-om border border-om-line2 p-5 space-y-4 ${className}`}>
            {(title || aside) && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    {title && <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wider">{title}</h2>}
                    {aside}
                </div>
            )}
            {children}
        </section>
    );
}
function Badge({ tone = 'neutral', children }) {
    const tones = {
        neutral: 'bg-om-chip text-om-muted',
        ok: 'bg-om-running-bg text-om-running',
        warn: 'bg-om-downtime-bg text-om-downtime',
        bad: 'bg-om-blocked-bg text-om-blocked',
    };
    return <span className={`inline-flex items-center text-xs px-2 py-0.5 rounded-full font-medium ${tones[tone]}`}>{children}</span>;
}

function Configuration({ counter, workstations, steps }) {
    const form = useForm({ mode: counter.mode, kind: counter.kind, workstation_id: counter.workstation_id ?? '', batch_step_id: counter.batch_step_id ?? '', note: '' });
    const options = steps.filter(s => s.workstation_id === Number(form.data.workstation_id));
    return <form aria-label={__('Counter configuration')} className="space-y-4" onSubmit={e => { e.preventDefault(); form.put(`${base}/${counter.id}`, { preserveScroll: true }); }}>
        <div className="grid md:grid-cols-2 gap-4">
            <Field label="Counter mode"><Dropdown className="w-full" value={form.data.mode} onChange={value => form.setData('mode', value)} options={Object.entries(MODES).map(([value, label]) => ({ value, label: __(label) }))} /></Field>
            <Field label="Count quality"><Dropdown className="w-full" value={form.data.kind} onChange={value => form.setData('kind', value)} options={Object.entries(KINDS).map(([value, label]) => ({ value, label: __(label) }))} /></Field>
            <Field label="Workstation"><Dropdown className="w-full" value={String(form.data.workstation_id)} onChange={value => form.setData({ ...form.data, workstation_id: value, batch_step_id: '' })} placeholder={__('Select workstation')} options={workstations.map(w => ({ value: String(w.id), label: w.name }))} /></Field>
            <Field label="Assigned batch step"><Dropdown className="w-full" value={String(form.data.batch_step_id)} onChange={value => form.setData('batch_step_id', value)} options={[{ value: '', label: __('Unassigned') }, ...options.map(s => ({ value: String(s.id), label: s.label }))]} /></Field>
        </div>
        <p className="text-xs text-om-muted">{__('After a configuration change, the next cumulative reading establishes the baseline. Earlier readings are never replayed automatically.')}</p>
        <Field label="Reason"><input className={input} required maxLength={1000} value={form.data.note} onChange={e => form.setData('note', e.target.value)} /></Field>
        <Errors errors={form.errors} />
        <Button type="submit" variant="primary" disabled={form.processing || !form.data.workstation_id}>{__(counter.configured_at ? 'Save counter configuration' : 'Enable explicit counting')}</Button>
    </form>;
}
function LegacySwitch({ counter }) {
    const form = useForm({ note: '' });
    return <form aria-label={__('Return to legacy counting')} className="space-y-3 pt-3" onSubmit={e => { e.preventDefault(); form.post(`${base}/${counter.id}/legacy`, { preserveScroll: true }); }}>
        <Field label="Reason"><input className={input} required value={form.data.note} onChange={e => form.setData('note', e.target.value)} /></Field>
        <Errors errors={form.errors} />
        <Button type="submit" variant="outline" disabled={form.processing}>{__('Return to legacy counting')}</Button>
    </form>;
}
function Baseline({ counter }) {
    const form = useForm({ note: '' });
    return <form aria-label={__('Reset review')} className="space-y-3" onSubmit={e => { e.preventDefault(); form.post(`${base}/${counter.id}/rebaseline`, { preserveScroll: true, onSuccess: () => form.reset() }); }}>
        <p className="text-xs text-om-muted">{__('Confirm a reset only after checking the machine. The next fresh reading becomes the baseline and adds no production.')}</p>
        <Field label="Reset reason"><input className={input} required maxLength={1000} value={form.data.note} onChange={e => form.setData('note', e.target.value)} /></Field>
        <Errors errors={form.errors} />
        <Button type="submit" variant="outline" disabled={form.processing}>{__('Establish new baseline')}</Button>
    </form>;
}
function Simulator({ counter }) {
    const form = useForm({ value: '', event_id: '', timestamp: '' });
    return <form aria-label={__('Test reading')} className="space-y-3" onSubmit={e => { e.preventDefault(); form.post(`${base}/${counter.id}/simulate`, { preserveScroll: true }); }}>
        <p className="text-xs text-om-muted">{__('These readings change the demo order. Simulation is available only for specially created test sources.')}</p>
        <div className="grid md:grid-cols-3 gap-4">
            <Field label="Raw count"><input className={input} type="number" step="0.01" min="0" required value={form.data.value} onChange={e => form.setData('value', e.target.value)} /></Field>
            <Field label="Event ID"><input className={input} maxLength={160} value={form.data.event_id} onChange={e => form.setData('event_id', e.target.value)} /></Field>
            <Field label="Timestamp (optional ISO 8601)"><input className={input} value={form.data.timestamp} onChange={e => form.setData('timestamp', e.target.value)} /></Field>
        </div>
        <Errors errors={form.errors} />
        <Button type="submit" variant="outline" disabled={form.processing}>{__('Send test reading')}</Button>
    </form>;
}
function Review({ counter, reading, steps, close }) {
    const form = useForm({ decision: 'dismiss', batch_step_id: reading.batch_step_id ?? '', note: '' });
    const canApply = reading.payload?.kind === 'good' && Number(reading.delta) > Number(reading.applied_qty);
    return <Card title={`${__('Review reading')} #${reading.id}`} className="border-om-accent">
        <form aria-label={__('Review reading')} className="space-y-4" onSubmit={e => { e.preventDefault(); form.post(`${base}/${counter.id}/readings/${reading.id}/review`, { preserveScroll: true, onSuccess: close }); }}>
            <div className="grid md:grid-cols-2 gap-4">
                <Field label="Decision"><Dropdown className="w-full" value={form.data.decision} onChange={value => form.setData('decision', value)} options={[{ value: 'dismiss', label: __('Dismiss with reason') }, ...(canApply ? [{ value: 'apply', label: __('Apply remaining good quantity') }] : [])]} /></Field>
                {form.data.decision === 'apply' && <Field label="Assigned batch step"><Dropdown className="w-full" value={String(form.data.batch_step_id)} onChange={value => form.setData('batch_step_id', value)} placeholder={__('Select step')} options={steps.filter(s => s.workstation_id === counter.workstation_id).map(s => ({ value: String(s.id), label: s.label }))} /></Field>}
            </div>
            <Field label="Review reason"><input required maxLength={1000} className={input} value={form.data.note} onChange={e => form.setData('note', e.target.value)} /></Field>
            <Errors errors={form.errors} />
            <div className="flex gap-2">
                <Button type="submit" variant="primary" disabled={form.processing || (form.data.decision === 'apply' && !form.data.batch_step_id)}>{__('Save review')}</Button>
                <Button type="button" variant="secondary" onClick={close}>{__('Cancel')}</Button>
            </div>
        </form>
    </Card>;
}
function ReadingHistory({ counter, readings, onReview }) {
    const { url } = usePage();
    const awaitingReview = new URL(url, window.location.origin).searchParams.get('review') === '1';
    const columns = useMemo(() => [
        { accessorKey: 'id', header: __('ID') },
        { accessorKey: 'observed_at', header: __('Time'), cell: ({ row }) => <div className="whitespace-nowrap">{new Date(row.original.observed_at).toLocaleString()}<div className="text-om-muted">{row.original.event_id}</div></div> },
        { accessorKey: 'raw_value', header: __('Raw count'), cell: ({ getValue }) => getValue() ?? '—' },
        { accessorKey: 'delta', header: __('Delta') },
        { accessorKey: 'applied_qty', header: __('Applied quantity') },
        { accessorKey: 'status', header: __('Status'), cell: ({ getValue }) => __(statuses[getValue()] ?? getValue()) },
        { accessorKey: 'batch_step_id', header: __('Batch step'), cell: ({ getValue }) => getValue() ?? '—' },
        { id: 'review', header: __('Review'), cell: ({ row }) => {
            const reading = row.original;
            return reading.review_note ?? (!reading.reviewed_at && REVIEWABLE.includes(reading.status)
                ? <Button size="sm" variant="outline" onClick={() => onReview(reading)}>{__('Review')}</Button> : null);
        } },
    ].map(column => ({ ...column, enableSorting: false })), [onReview]);
    const visit = target => router.visit(target, { preserveScroll: true });
    const filter = (active) => `px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${active ? 'bg-om-ink text-om-on-ink' : 'bg-om-chip text-om-muted hover:text-om-ink'}`;
    return <Card
        title={__('Reading history')}
        aside={<div className="flex gap-1">
            <button type="button" className={filter(!awaitingReview)} aria-pressed={!awaitingReview} onClick={() => visit(`${base}?counter=${counter.id}`)}>{__('All readings')}</button>
            <button type="button" className={filter(awaitingReview)} aria-pressed={awaitingReview} onClick={() => visit(`${base}?counter=${counter.id}&review=1`)}>
                {__('Awaiting review')}{counter.pending_review_count > 0 && ` (${counter.pending_review_count})`}
            </button>
        </div>}
    >
        <AppDataTable data={readings?.data ?? []} columns={columns} getRowId={row => String(row.id)} searchable={false} columnToggle={false} paginated={false} bodyMaxHeight={null} />
        {(readings?.prev_page_url || readings?.next_page_url) && <div className="flex gap-2">
            <Button variant="outline" disabled={!readings.prev_page_url} onClick={() => visit(readings.prev_page_url)}>{__('Previous')}</Button>
            <Button variant="outline" disabled={!readings.next_page_url} onClick={() => visit(readings.next_page_url)}>{__('Next')}</Button>
        </div>}
    </Card>;
}

/** One counter in the side list: what it is, where it counts, what needs a look. */
function CounterTile({ counter, selected, onSelect }) {
    return (
        <Link
            href={`${base}?counter=${counter.id}`}
            onClick={onSelect}
            preserveScroll
            aria-current={selected ? 'page' : undefined}
            className={`block rounded-om border p-3 transition-colors ${selected ? 'border-om-accent bg-om-chip' : 'border-om-line2 bg-om-card hover:bg-om-chip'}`}
        >
            <div className="flex items-start justify-between gap-2">
                <span className="font-medium text-sm text-om-ink truncate">{counter.label}</span>
                <span className="text-xs text-om-faint shrink-0">#{counter.id}</span>
            </div>
            <p className="text-xs text-om-muted truncate">
                {counter.connection?.name}{counter.workstation?.name ? ` · ${counter.workstation.name}` : ''}
            </p>
            <div className="mt-2 flex flex-wrap gap-1">
                {counter.configured_at ? <Badge tone="ok">{__('Explicit counting')}</Badge> : <Badge>{__('Legacy counting')}</Badge>}
                {counter.reset_required && <Badge tone="bad">{__('Reset review required')}</Badge>}
                {counter.pending_review_count > 0 && <Badge tone="warn">{__('To review: :count', { count: counter.pending_review_count })}</Badge>}
            </div>
        </Link>
    );
}

export default function Counters() {
    const { counters, selectedId, sources, workstations, steps, readings, canSimulate, auth } = usePage().props;
    // Counters are their own tab; someone granted only them (a supervisor,
    // typically) would get a 403 behind the Connectivity button.
    const tabs = auth?.user?.accessibleTabs;
    const canOpenConnectivity = ! Array.isArray(tabs) || tabs.includes('connectivity');
    const counter = counters.find(c => c.id === selectedId);
    const register = useForm({ source_type: 'tag', source_id: '' });
    const [review, setReview] = useState(null);
    const assigned = steps.find(s => s.id === counter?.batch_step_id);

    return <><Head title={__('Machine counters')} /><div className="p-6 space-y-6">
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 className="text-2xl font-bold text-om-ink">{__('Machine counters')}</h1>
                <p className="text-sm text-om-muted mt-1 max-w-3xl">{__('Existing channels keep legacy counting until you enable explicit counting. In explicit mode, assign a channel to one batch step and verify its count quality.')}</p>
            </div>
            <div className="flex flex-wrap gap-2">
                {canOpenConnectivity && (
                    <Button variant="outline" onClick={() => router.visit('/admin/connectivity')}>{__('Machine Connectivity')}</Button>
                )}
                <Button variant="outline" onClick={() => router.reload({ preserveScroll: true })}>{__('Refresh')}</Button>
            </div>
        </div>

        <div className="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
            <aside className="space-y-4">
                <Card title={__('Counters')}>
                    {counters.length === 0
                        ? <p className="text-sm text-om-faint">{__('No counters yet. Open one from a machine source below.')}</p>
                        : <nav className="space-y-2">{counters.map(c => <CounterTile key={c.id} counter={c} selected={c.id === selectedId} onSelect={() => setReview(null)} />)}</nav>}
                </Card>
                <Card title={__('Add counter')}>
                    <form aria-label={__('Register counter')} className="space-y-3" onSubmit={e => { e.preventDefault(); register.post(base); }}>
                        <Field label="Machine source"><Dropdown className="w-full" value={register.data.source_id ? `${register.data.source_type}:${register.data.source_id}` : ''} placeholder={__('Select source')} onChange={value => { const [source_type, source_id] = value.split(':'); register.setData({ source_type, source_id }); }} options={sources.map(s => ({ value: `${s.type}:${s.id}`, label: `${s.label} (${s.type} #${s.id})` }))} /></Field>
                        <Errors errors={register.errors} />
                        <Button type="submit" variant="primary" disabled={register.processing || !register.data.source_id}>{__('Open counter')}</Button>
                    </form>
                </Card>
            </aside>

            <div className="space-y-6 min-w-0">
                {!counter && (
                    <div className="bg-om-card rounded-om border border-dashed border-om-line p-10 text-center text-sm text-om-faint">
                        {__('Pick a counter on the left, or open one from a machine source.')}
                    </div>
                )}
                {counter && <>
                    <Card>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <h2 className="text-lg font-semibold text-om-ink">{counter.label}</h2>
                                <p className="text-sm text-om-muted">
                                    {counter.connection?.name}{counter.workstation?.name ? ` · ${counter.workstation.name}` : ''}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {counter.configured_at ? <Badge tone="ok">{__('Explicit counting')}</Badge> : <Badge>{__('Legacy counting')}</Badge>}
                                <Badge>{__(MODES[counter.mode] ?? counter.mode)}</Badge>
                                <Badge>{__(KINDS[counter.kind] ?? counter.kind)}</Badge>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <StatCard value={<span data-testid="last-raw">{counter.last_raw != null ? formatNumber(Number(counter.last_raw)) : '—'}</span>} label={__('Last raw count')} />
                            <StatCard value={counter.last_read_at ? new Date(counter.last_read_at).toLocaleTimeString() : '—'} label={__('Last reading')} />
                            <StatCard value={<span data-testid="step-good">{assigned?.passed_qty != null ? formatNumber(Number(assigned.passed_qty)) : '—'}</span>} label={__('Good')} />
                            <StatCard value={counter.pending_review_count ?? 0} label={__('Awaiting review')} />
                        </div>
                        <p className="text-sm text-om-muted">
                            {__('Assigned batch step')}: <span className="text-om-ink">{assigned?.label ?? (counter.batch_step_id ? `#${counter.batch_step_id}` : __('Unassigned'))}</span>
                        </p>
                        {counter.reset_required && <p role="alert" className="rounded-md bg-om-blocked-bg text-om-blocked text-sm font-semibold px-3 py-2">{__('Reset review required')}</p>}
                        <p className="text-xs text-om-muted">{__(counter.configured_at ? 'Explicit counting is enabled for this channel.' : 'Legacy counting is active. Opening this page does not change machine behaviour. Enable explicit counting when this channel is ready.')}</p>
                    </Card>

                    {review && <Review key={review.id} {...{ counter, reading: review, steps }} close={() => setReview(null)} />}

                    <Card title={__('Configuration')}>
                        <Configuration key={`${counter.id}:${counter.updated_at}`} {...{ counter, workstations, steps }} />
                    </Card>

                    {counter.configured_at && counter.mode === 'cumulative' && (
                        <Card title={__('Counter reset')}><Baseline key={counter.id} counter={counter} /></Card>
                    )}
                    {canSimulate && <Card title={__('Local test readings')}><Simulator key={counter.id} counter={counter} /></Card>}

                    <ReadingHistory counter={counter} readings={readings} onReview={setReview} />

                    {counter.configured_at && (
                        <details className="bg-om-card rounded-om border border-om-line2 p-5">
                            <summary className="cursor-pointer text-sm font-medium text-om-muted">{__('Return to legacy counting')}</summary>
                            <LegacySwitch counter={counter} />
                        </details>
                    )}
                </>}
            </div>
        </div>
    </div></>;
}
Counters.layout = page => <AppLayout>{page}</AppLayout>;
