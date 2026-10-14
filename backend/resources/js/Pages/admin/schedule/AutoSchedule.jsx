import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, Icon, InlineAlert, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import AppDatePicker from '../../../components/AppDatePicker';
import PageTrail from '../../../components/PageTrail';
import useConfirm from '../../../components/useConfirm';
import { __, formatNumber } from '../../../lib/i18n';

const MONO = 'var(--font-mono)';

// ─── Formatting ──────────────────────────────────────────────────────────────

/** 25 → "25 min", 90 → "1 h 30 min", 120 → "2 h". */
/** "2026-10-15 06:25" → "15.10 06:25": the year is the horizon's, not news. */
const shortStamp = (label) => (label ? `${label.slice(8, 10)}.${label.slice(5, 7)} ${label.slice(11, 16)}` : '—');

function minutes(value) {
    if (value == null) return '—';
    const m = Math.round(value);
    if (m < 60) return __(':m min', { m });
    const h = Math.floor(m / 60);
    const rest = m % 60;
    return rest ? __(':h h :m min', { h, m: rest }) : __(':h h', { h });
}

/** One reason, worded for a planner: material shortages say what is missing. */
function reasonText(reason) {
    if (reason.code === 'material' && Array.isArray(reason.shortages) && reason.shortages.length) {
        return reason.shortages.map((s) => __(':material: :missing :unit missing (needs :required, :available in stock)', {
            material: s.material_name ?? s.material_code,
            missing: formatNumber(s.missing_qty),
            required: formatNumber(s.required_qty),
            available: formatNumber(s.available_qty),
            unit: s.unit_of_measure ? __(s.unit_of_measure) : '',
        })).join(' · ');
    }

    return reason.message;
}

// ─── Workstation override (manual selection, audited server-side) ────────────

const SOURCE_LABEL = () => ({ fixed: __('fixed'), auto: __('auto'), manual: __('manual'), line: __('whole line') });

function StationCell({ op, stationsByType, canDecide }) {
    const options = op.workstation_type_id ? stationsByType[op.workstation_type_id] ?? [] : [];
    const [value, setValue] = useState(op.workstation_id ? String(op.workstation_id) : '');
    const [error, setError] = useState(null);
    const source = SOURCE_LABEL()[op.resource_source];

    if (!canDecide || !op.scheduled || options.length < 2) {
        return (
            <span className="text-[13px] text-om-ink">
                {op.workstation_name ?? op.line_name ?? '—'}
                {source && <span className="text-[11px] text-om-muted"> · {source}</span>}
            </span>
        );
    }

    // A refused move (station busy in this window, wrong class) comes back as a
    // validation error. Keep this cell's state through that visit so the reason
    // shows next to the select instead of the choice silently snapping back.
    const save = (next) => {
        setValue(next);
        setError(null);
        if (next && next !== String(op.workstation_id)) {
            router.put(`/admin/schedule/operations/${op.id}`, { workstation_id: next }, {
                preserveScroll: true,
                preserveState: 'errors',
                onError: (errors) => {
                    setValue(op.workstation_id ? String(op.workstation_id) : '');
                    setError(errors.workstation_id ?? __('The operation could not be moved.'));
                },
            });
        }
    };

    return (
        <span className="flex flex-col gap-1">
            <span className="flex items-center gap-2">
                <Dropdown
                    size="sm"
                    className="w-[190px]"
                    options={options.map((st) => ({ value: String(st.id), label: st.name }))}
                    value={value}
                    onChange={save}
                    aria-label={__('Workstation for :order step :step', { order: op.order_no, step: op.step_number })}
                />
                {source && <span className="text-[11px] text-om-muted">{source}</span>}
            </span>
            {error && <span role="alert" className="text-[11.5px] text-om-blocked">{error}</span>}
        </span>
    );
}

// ─── Operations table ────────────────────────────────────────────────────────

const PILL = { scheduled: 'running', scheduled_late: 'downtime' };

function SetupNote({ op }) {
    if (!op.setup_minutes) return null;
    const byRule = op.reasons?.some((r) => r.code === 'changeover');

    return byRule ? (
        <div className="text-[11px] font-semibold text-om-accent">{__('incl. :time changeover', { time: minutes(op.setup_minutes) })}</div>
    ) : (
        <div className="text-[11px] text-om-muted">{__('incl. :time set-up', { time: minutes(op.setup_minutes) })}</div>
    );
}

/** What a planner needs to read beside the status; the changeover note is shown with the duration. */
function Reasons({ op }) {
    const shown = (op.reasons ?? []).filter((r) => r.code !== 'changeover' && (r.code !== 'in_progress' || !op.scheduled));
    if (!shown.length) return null;

    return (
        <ul className="mt-1 space-y-0.5">
            {shown.map((r, i) => <li key={i} className="text-[12px] text-om-muted">{reasonText(r)}</li>)}
        </ul>
    );
}

function OperationsTable({ operations, stationsByType, canDecide, emptyText }) {
    if (!operations.length) {
        return <div className="rounded-om-sm border border-dashed border-om-line bg-om-card px-4 py-8 text-center text-[13px] text-om-muted">{emptyText}</div>;
    }

    const head = 'px-3 py-2.5 text-left text-[9.5px] font-normal uppercase tracking-[0.08em] text-om-faint whitespace-nowrap';
    const cell = 'px-3 py-2.5 align-top';

    return (
        <div className="overflow-x-auto rounded-om-sm border border-om-line bg-om-card">
            <table className="w-full border-collapse text-[13px]">
                <thead>
                    <tr className="border-b border-om-line bg-om-bg" style={{ fontFamily: MONO }}>
                        <th className={head}>{__('Work order')}</th>
                        <th className={head}>{__('Step')}</th>
                        <th className={head}>{__('Resource')}</th>
                        <th className={head}>{__('Start')}</th>
                        <th className={head}>{__('End')}</th>
                        <th className={`${head} text-right`}>{__('Duration')}</th>
                        <th className={head}>{__('Status')}</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-om-line">
                    {operations.map((op) => (
                        <tr key={op.id} className="hover:bg-om-chip/40">
                            <td className={`${cell} whitespace-nowrap`}>
                                <Link href={`/admin/work-orders/${op.work_order_id}`} className="font-semibold text-om-ink hover:underline" style={{ fontFamily: MONO }}>{op.order_no}</Link>
                                {op.product_name && <div className="max-w-[160px] truncate text-[12px] text-om-muted">{op.product_name}</div>}
                            </td>
                            <td className={cell}>
                                <span className="whitespace-nowrap">
                                    <span className="text-om-faint" style={{ fontFamily: MONO }}>{op.step_number}</span>{' '}
                                    <span className="text-om-ink">{op.step_name}</span>
                                </span>
                            </td>
                            <td className={cell}>
                                <StationCell op={op} stationsByType={stationsByType} canDecide={canDecide} />
                            </td>
                            <td className={`${cell} whitespace-nowrap text-[12.5px] text-om-ink`} style={{ fontFamily: MONO }}>{shortStamp(op.planned_start_label)}</td>
                            <td className={`${cell} whitespace-nowrap text-[12.5px] text-om-ink`} style={{ fontFamily: MONO }}>{shortStamp(op.planned_end_label)}</td>
                            <td className={`${cell} w-[120px] text-right`}>
                                <div className="whitespace-nowrap text-om-ink">{minutes(op.duration_minutes)}</div>
                                <SetupNote op={op} />
                            </td>
                            <td className={`${cell} w-[230px]`}>
                                <StatusPill status={PILL[op.status] ?? 'blocked'} label={op.status_label} pulse={false} />
                                <Reasons op={op} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── Generate form ───────────────────────────────────────────────────────────

/** Every half hour of the day, 24-hour clock — the plant's own times. */
const HALF_HOURS = Array.from({ length: 48 }, (_, i) => `${String(Math.floor(i / 2)).padStart(2, '0')}:${i % 2 ? '30' : '00'}`);

/** "2026-10-14T16:04" → ["2026-10-14", "16:30"]: the next half hour from now. */
function splitStart(value) {
    const [day, time = '06:00'] = (value ?? '').split('T');
    const [h, m] = time.split(':').map(Number);
    const next = h * 60 + m === 0 ? 0 : Math.ceil((h * 60 + m) / 30) * 30;
    if (next >= 24 * 60) {
        const d = new Date(`${day}T12:00:00`);
        d.setDate(d.getDate() + 1);
        return [d.toISOString().slice(0, 10), '00:00'];
    }
    return [day, `${String(Math.floor(next / 60)).padStart(2, '0')}:${String(next % 60).padStart(2, '0')}`];
}

function GenerateForm({ defaults, canDecide }) {
    const [initialDay, initialTime] = splitStart(defaults.start);
    const form = useForm({
        day: initialDay,
        time: initialTime,
        horizon_days: String(defaults.horizon_days),
        scope: defaults.scope,
        respect_material: defaults.respect_material,
        respect_workforce: defaults.respect_workforce ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        form.transform(({ day, time, ...rest }) => ({ ...rest, start: day ? `${day}T${time}` : '' }));
        form.post('/admin/schedule/simulations', { preserveScroll: true });
    };

    const horizons = [[7, __('1 week')], [14, __('2 weeks')], [28, __('4 weeks')], [56, __('8 weeks')], [84, __('12 weeks')]]
        .filter(([days]) => days <= defaults.max_horizon_days)
        .map(([days, text]) => ({ value: String(days), label: text }));
    if (!horizons.some((o) => o.value === form.data.horizon_days)) {
        horizons.push({ value: form.data.horizon_days, label: __(':days days', { days: form.data.horizon_days }) });
    }

    // Same caption style as ResourceForm, so this reads like every other form.
    const label = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
    const error = (key) => form.errors[key] && <p className="mt-1 text-[11.5px] text-om-blocked">{form.errors[key]}</p>;

    return (
        <form onSubmit={submit} className="rounded-om-sm border border-om-line bg-om-card p-5">
            <div className="mb-4">
                <div className="text-[15px] font-semibold text-om-ink">{__('New simulation')}</div>
                <p className="mt-0.5 text-[12.5px] text-om-muted">
                    {__('Calculates a feasible plan from shifts, machines, people and material. Nothing changes until you apply it.')}
                </p>
            </div>

            <div className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(180px,1fr)_120px_minmax(150px,0.8fr)_minmax(240px,1.3fr)_auto]">
                <div>
                    <div className={label}>{__('From day')}</div>
                    <AppDatePicker className="w-full" value={form.data.day || null} onChange={(d) => form.setData('day', d ?? '')} aria-label={__('From day')} />
                    {error('start')}
                </div>
                <div>
                    <div className={label}>{__('From time')}</div>
                    <Dropdown
                        className="w-full"
                        options={HALF_HOURS.map((t) => ({ value: t, label: t }))}
                        value={form.data.time}
                        onChange={(v) => form.setData('time', v)}
                        aria-label={__('From time')}
                    />
                </div>
                <div>
                    <div className={label}>{__('Horizon')}</div>
                    <Dropdown
                        className="w-full"
                        options={horizons}
                        value={form.data.horizon_days}
                        onChange={(v) => form.setData('horizon_days', v)}
                        aria-label={__('Horizon')}
                        searchable={false}
                    />
                    {error('horizon_days')}
                </div>
                <div>
                    <div className={label}>{__('Orders to schedule')}</div>
                    <Dropdown
                        className="w-full"
                        options={[
                            { value: 'unplanned', label: __('Only orders without a time plan') },
                            { value: 'all', label: __('Replan every order not yet started') },
                        ]}
                        value={form.data.scope}
                        onChange={(v) => form.setData('scope', v)}
                        aria-label={__('Orders to schedule')}
                        searchable={false}
                    />
                    {error('scope')}
                </div>
                <Button type="submit" className="py-[11px]!" disabled={!canDecide || form.processing} leftIcon={<Icon name="calendar-cog" size={14} />}>
                    {form.processing ? __('Calculating…') : __('Simulate')}
                </Button>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-om-line pt-4">
                <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Take into account')}</span>
                <Checkbox
                    checked={!!form.data.respect_material}
                    onChange={(v) => form.setData('respect_material', v)}
                    label={__('Material availability')}
                />
                <Checkbox
                    checked={!!form.data.respect_workforce}
                    onChange={(v) => form.setData('respect_workforce', v)}
                    label={__('Operator availability')}
                />
            </div>

            {!canDecide && (
                <div className="mt-4">
                    <InlineAlert severity="info" title={__('Only a supervisor or administrator can simulate or apply schedules.')} />
                </div>
            )}
        </form>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────

const NAV_LINK = 'inline-flex items-center gap-2 rounded-om-sm border border-om-line px-3 py-2 text-xs font-semibold text-om-ink hover:bg-om-chip';

export default function AutoSchedule() {
    const { draft, committed = [], stationsByType = {}, defaults, canDecide } = usePage().props;
    const { confirm, dialog } = useConfirm();
    const [showOnlyProblems, setShowOnlyProblems] = useState(false);
    const placed = (draft?.counts?.scheduled ?? 0) + (draft?.counts?.scheduled_late ?? 0);
    const draftOps = (draft?.operations ?? []).filter((op) => !showOnlyProblems || op.status !== 'scheduled');

    const apply = () => confirm(
        {
            title: __('Apply this schedule?'),
            body: __('Fully schedulable work orders get the simulated time plan and workstations. Orders with infeasible steps keep their current placement. Each change is logged and can be undone from the planner.'),
            confirmLabel: __('Apply'),
        },
        () => router.post(`/admin/schedule/simulations/${draft.id}/apply`, {}, { preserveScroll: true }),
    );
    const discard = () => confirm(
        { title: __('Discard this simulation?'), body: __('The committed schedule is not affected.'), confirmLabel: __('Discard') },
        () => router.post(`/admin/schedule/simulations/${draft.id}/discard`, {}, { preserveScroll: true }),
    );

    return (
        <>
            <Head title={__('Automatic scheduling')} />
            <PageTrail append={[{ label: __('Automatic scheduling') }]} />

            <div className="space-y-5 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div style={{ fontFamily: MONO }} className="mb-1.5 text-[10px] uppercase tracking-[0.1em] text-om-faint">
                            {__('Schedule')} · {draft ? __('simulation ready for review') : __(':count operations committed', { count: committed.length })}
                        </div>
                        <h2 className="m-0 text-[24px] font-semibold tracking-[-0.022em] text-om-ink">{__('Automatic scheduling')}</h2>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/schedule" className={NAV_LINK}><Icon name="calendar-range" size={14} />{__('Planner')}</Link>
                        <Link href="/admin/schedule/capacity" className={NAV_LINK}><Icon name="chart-no-axes-combined" size={14} />{__('Capacity')}</Link>
                        <Link href="/admin/schedule/changeover-rules" className={NAV_LINK}><Icon name="repeat" size={14} />{__('Changeover Rules')}</Link>
                    </div>
                </div>

                <GenerateForm defaults={defaults} canDecide={canDecide} />

                {draft && (
                    <section className="space-y-4 rounded-om-sm border border-om-accent bg-om-panel p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="text-[15px] font-semibold text-om-ink">{__('Simulation — not applied')}</div>
                                <div className="mt-0.5 text-[12px] text-om-muted">
                                    {__('Horizon :range', { range: draft.horizon_label })}
                                    {' · '}
                                    {__('Generated :at by :user', { at: draft.created_label, user: draft.created_by ?? '—' })}
                                </div>
                            </div>
                            {canDecide && (
                                <div className="flex items-center gap-2">
                                    <Button variant="outline" onClick={discard}>{__('Discard')}</Button>
                                    <Button onClick={apply} disabled={placed === 0}>{__('Apply')}</Button>
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <Checkbox size="sm" checked={showOnlyProblems} onChange={setShowOnlyProblems} label={__('Only late or infeasible')} />
                        </div>
                        <OperationsTable
                            operations={draftOps}
                            stationsByType={stationsByType}
                            canDecide={canDecide}
                            emptyText={__('No work orders to schedule — every active order already has a time plan, or none has a routing.')}
                        />
                    </section>
                )}

                <section className="space-y-4">
                    <div>
                        <div className="text-[15px] font-semibold text-om-ink">{__('Committed operation plan')}</div>
                        <div className="mt-0.5 text-[12px] text-om-muted">
                            {__('Operations of active work orders from applied schedules. Moving an order by hand on the planner removes its rows here; the next simulation works around its new time plan.')}
                        </div>
                    </div>
                    <OperationsTable
                        operations={committed}
                        stationsByType={stationsByType}
                        canDecide={canDecide}
                        emptyText={__('No schedule has been applied yet.')}
                    />
                </section>
            </div>
            {dialog}
        </>
    );
}

AutoSchedule.layout = (page) => <AppLayout>{page}</AppLayout>;
