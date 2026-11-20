import { useEffect, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, Icon, InlineAlert, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import AppDatePicker from '../../../components/AppDatePicker';
import PageTrail from '../../../components/PageTrail';
import useConfirm from '../../../components/useConfirm';
import { __, formatDate, formatNumber, formatTime, transChoice } from '../../../lib/i18n';
import { TONE_STYLE, shortName, staffingGrid, staffingTone } from '../../../lib/staffing';
import { apiGet } from '../../../lib/http';
import { refreshOnReturn } from '../../../lib/refreshOnReturn';

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

    if (isPeopleReason(reason)) {
        return peopleText(reason);
    }

    if (reason.code === 'delayed_by_staffing' && reason.machine_start) {
        return __('Delayed by staffing: :from → :to — :reason', {
            from: isoStamp(reason.machine_start),
            to: isoStamp(reason.planned_start),
            reason: reason.cause ? reasonText(reason.cause) : '',
        });
    }

    if (reason.code === 'predecessor_not_schedulable' && reason.blocking_step_number != null) {
        const params = { step: reason.blocking_step_number, required: reason.required, available: reason.available };
        if (typeof reason.required !== 'number') return __('Waits for step :step, which could not be scheduled.', params);
        return reason.cause?.code === 'qualification'
            ? __('Waits for step :step, which is short of qualified operators (needs :required, :available available).', params)
            : __('Waits for step :step, which is short of operators (needs :required, :available available).', params);
    }

    if (reason.code === 'waited_for_operators' && reason.busy_lines?.length) {
        return __('Waited for operators busy on :lines.', { lines: lineNames(reason.busy_lines) });
    }

    if (reason.code === 'waited_for_qualified_operators' && Array.isArray(reason.qualified_workers)) {
        const params = {
            count: reason.available,
            skills: skillNames(reason.required_skills),
            names: reason.qualified_workers.map((w) => w.name).join(', '),
            lines: lineNames(reason.busy_lines ?? []),
        };
        const busy = typeof reason.busy === 'number' ? reason.busy : reason.available;
        if (busy < reason.available) {
            params.busy = busy;
            return reason.busy_lines?.length
                ? __('Waited: :busy of :count qualified operators for :skills (:names) busy with other work, some on :lines.', params)
                : __('Waited: :busy of :count qualified operators for :skills (:names) busy with other work.', params);
        }
        if (reason.busy_lines?.length) {
            return reason.available === 1
                ? __('Waited: only :count qualified operator for :skills (:names), busy with other work on :lines.', params)
                : __('Waited: only :count qualified operators for :skills (:names), all busy with other work, some on :lines.', params);
        }
        return reason.available === 1
            ? __('Waited: only :count qualified operator for :skills (:names), busy with other work.', params)
            : __('Waited: only :count qualified operators for :skills (:names), all busy with other work.', params);
    }

    return reason.message;
}

/** "Line A, Line B" — the lines holding shared people. */
const lineNames = (busyLines) => busyLines.map((l) => l.line_name ?? `#${l.line_id}`).join(', ');

/** "Welding, Painting" — the skills a step requires. */
const skillNames = (skills) => (skills ?? []).map((s) => s.name ?? `#${s.id}`).join(', ');

/** A reason that names the window it is about. */
const WINDOW_CODES = ['waited_for_operators', 'waited_for_qualified_operators', 'delayed_by_staffing'];
const hasWindow = (reason) => (isPeopleReason(reason) || WINDOW_CODES.includes(reason.code)) && reason.window_start;

/** A people reason carrying the numbers (drafts from before they did only have the message). */
const isPeopleReason = (reason) => (reason.code === 'workforce_capacity' || reason.code === 'qualification') && typeof reason.required === 'number';

/** "Needs 3 operators, 2 available (1 absent: Jan Kowalski). 1 busy with other work." — in the reader's language. */
function peopleText(reason) {
    const absent = reason.absent_workers ?? [];
    const params = {
        required: reason.required,
        available: reason.available,
        absent: absent.length,
        names: absent.map((w) => w.name ?? `#${w.id}`).join(', '),
        skills: skillNames(reason.required_skills),
    };
    let text;
    if (reason.code === 'qualification') {
        text = absent.length
            ? __('Needs :required operators qualified in :skills, :available available (:absent absent: :names).', params)
            : __('Needs :required operators qualified in :skills, :available available.', params);
    } else {
        text = absent.length
            ? __('Needs :required operators, :available available (:absent absent: :names).', params)
            : __('Needs :required operators, :available available.', params);
    }

    if (reason.in_use > 0) {
        // Of those busy, how many other lines hold; the rest work on this one.
        const busyLines = reason.busy_lines ?? [];
        const held = Math.min(reason.in_use, busyLines.reduce((sum, l) => sum + (l.operators ?? 0), 0));
        const lines = lineNames(busyLines);
        if (held === 0) text += ` ${__(':count busy with other work.', { count: reason.in_use })}`;
        else if (held === reason.in_use) text += ` ${__(':count busy with other work on :lines.', { count: held, lines })}`;
        else text += ` ${__(':count busy with other work, :held of them on :lines.', { count: reason.in_use, held, lines })}`;
    }

    return reason.bottleneck_skill ? `${text} ${bottleneckText(reason)}` : text;
}

/** "Painting is held only by Jan Kowalski." — the scarcest required skill on the line. */
function bottleneckText(reason) {
    const holders = reason.skill_holders ?? [];
    const params = {
        skill: reason.bottleneck_skill.name ?? `#${reason.bottleneck_skill.id}`,
        names: holders.map((h) => h.name).join(', '),
    };
    if (holders.length === 0) return __('Nobody on this line holds :skill.', params);
    if (holders.length === 1) return __(':skill is held only by :names.', params);

    return __(':skill is held by :names.', params);
}

/** "2026-10-12T06:00:00+02:00" → "12.10 06:00", plant time as the server wrote it. */
const isoStamp = (iso) => (iso ? shortStamp(`${iso.slice(0, 10)} ${iso.slice(11, 16)}`) : '—');

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
            {shown.map((r, i) => (
                <li
                    key={i}
                    className={r.code === 'delayed_by_staffing' ? 'text-[12px] font-medium text-om-downtime' : 'text-[12px] text-om-muted'}
                    data-testid={r.code === 'delayed_by_staffing' ? 'delayed-by-staffing' : undefined}
                >
                    {reasonText(r)}
                    {hasWindow(r) && (
                        <div className="text-[11px] text-om-faint" style={{ fontFamily: MONO }}>
                            {__('Window :from – :to', { from: isoStamp(r.window_start), to: isoStamp(r.window_end) })}
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}

// ─── Staffing ────────────────────────────────────────────────────────────────

const UNTRACKED_TEXT = () => __('Staffing not tracked — scheduled on machine capacity only');

/** Which lines the run counted people on, and which it scheduled on machines alone. */
function StaffingPanel({ workforce, canAssignOperators }) {
    if (!workforce?.length) return null; // drafts from before staffing was reported

    if (workforce.some((w) => w.respected === false)) {
        return <InlineAlert severity="info" title={__('Operator availability was ignored for this run.')} />;
    }

    const tracked = workforce.filter((w) => w.tracked);
    const untracked = workforce.filter((w) => !w.tracked);

    return (
        <div className="space-y-2 rounded-om-sm border border-om-line bg-om-card p-4" data-testid="staffing-panel">
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Staffing')}</div>
            {tracked.length > 0 && (
                <ul className="flex flex-wrap gap-x-5 gap-y-1 text-[12.5px] text-om-ink">
                    {tracked.map((w) => (
                        <li key={w.line_id}>
                            <span className="font-semibold">{w.line_name}</span>
                            {w.assigned != null && <span className="text-om-muted"> · {transChoice(':count operator assigned|:count operators assigned', w.assigned)}</span>}
                            {(w.single_holder_skills ?? []).length > 0 && (
                                <span className="mt-1 flex flex-wrap gap-1.5">
                                    {w.single_holder_skills.map((s) => (
                                        <span
                                            key={s.skill_id}
                                            data-testid="single-qualified-skill"
                                            title={__('Only one person on this line holds this skill.')}
                                            className="inline-flex items-center gap-1 rounded-full border border-om-downtime/30 bg-om-downtime-bg px-2 py-0.5 text-[11.5px] font-medium text-om-downtime"
                                        >
                                            <Icon name="triangle-alert" size={12} />
                                            {__(':skill — single qualified operator: :name', { skill: s.skill_name ?? `#${s.skill_id}`, name: s.holder_name ?? '—' })}
                                        </span>
                                    ))}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {untracked.map((w) => (
                <InlineAlert key={w.line_id} severity="warning" title={`${w.line_name} — ${UNTRACKED_TEXT()}`}>
                    {canAssignOperators ? (
                        <Link href={`/admin/lines/${w.line_id}#operators`} className="text-[12px] font-semibold text-om-accent hover:underline">
                            {__('Assign operators')}
                        </Link>
                    ) : null}
                </InlineAlert>
            ))}
        </div>
    );
}

/** Time of day of an ISO stamp in the reader's locale: "06:00". */
const clock = (iso) => formatTime(iso, { hour: '2-digit', minute: '2-digit', hour12: false });

/** One line × shift cell: people available / people the draft needs. */
function GridCell({ cell }) {
    if (!cell) return <td className="p-0.5"><div className="rounded-om-sm px-1 py-1.5 text-center text-[11px]" style={TONE_STYLE.idle}>·</div></td>;
    const tone = staffingTone(cell);
    const absent = cell.absent ?? [];
    const held = cell.held_elsewhere ?? [];
    const tip = [
        `${cell.shift_name} · ${clock(cell.start)}–${clock(cell.end)}`,
        __(':available available, :needed needed', { available: cell.available ?? 0, needed: cell.needed ?? 0 }),
        absent.length ? `${__('Absent')}: ${absent.map((w) => w.name ?? `#${w.id}`).join(', ')}` : null,
        ...held.map((h) => __(':names booked on :line', { names: h.names.join(', '), line: h.line_name })),
        cell.person_hours > 0 ? __(':hours planned person-hours', { hours: formatNumber(cell.person_hours, { maximumFractionDigits: 1 }) }) : null,
        cell.blocked_operations > 0 ? __(':count operations blocked', { count: cell.blocked_operations }) : null,
    ].filter(Boolean).join('\n');
    const note = absent.length
        ? __(':name absent', { name: absent.length === 1 ? shortName(absent[0].name) : String(absent.length) })
        : held.length ? __(':name on :line', { name: shortName(held[0].names[0]), line: held[0].line_name }) : null;

    return (
        <td className="p-0.5">
            <div className="rounded-om-sm px-1 py-1.5 text-center" style={{ ...TONE_STYLE[tone], fontFamily: MONO }} title={tip}
                data-testid="staffing-cell" data-tone={tone}>
                <div className={`text-[12.5px] ${tone === 'short' ? 'font-bold' : 'font-semibold'}`}>
                    {tone === 'idle' || tone === 'untracked' ? '—' : `${cell.available}/${cell.needed}`}
                </div>
                {note && <div className="truncate text-[10px] font-medium" style={{ fontFamily: 'var(--font-sans, inherit)' }}>{note}</div>}
            </div>
        </td>
    );
}

/** Lines as rows, day + shift as columns, one week at a time. */
function StaffingByShift({ staffing, canAssignOperators }) {
    const grid = staffingGrid(staffing?.shifts);
    const [weekIdx, setWeekIdx] = useState(0);
    const week = grid.weeks[Math.min(weekIdx, grid.weeks.length - 1)];
    const cols = grid.columns[week] ?? [];
    const head = 'px-1 py-1 text-center text-[9.5px] font-normal uppercase tracking-[0.06em] text-om-faint whitespace-nowrap';
    const legend = [['spare', __('Spare people')], ['enough', __('Exactly enough')], ['short', __('Short')], ['untracked', __('Staffing not tracked')]];

    return (
        <div className="space-y-2 rounded-om-sm border border-om-line bg-om-card p-4" data-testid="staffing-by-shift">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Staffing by shift')}</div>
                <div className="text-[11.5px] text-om-muted">{__('cell = people available / people the plan needs')}</div>
            </div>
            {grid.lines.length === 0 ? (
                <div className="text-[12.5px] text-om-muted">{__('No staffing demand in this horizon.')}</div>
            ) : (
                <>
                    {grid.weeks.length > 1 && (
                        <div className="flex items-center gap-2 text-[12px]" data-testid="staffing-week-pager">
                            <Button size="sm" variant="outline" disabled={weekIdx === 0} onClick={() => setWeekIdx(weekIdx - 1)} aria-label={__('Previous week')}><Icon name="chevron-left" size={14} /></Button>
                            <span style={{ fontFamily: MONO }}>{__('Week of :date', { date: formatDate(week, { day: '2-digit', month: '2-digit' }) })} · {weekIdx + 1}/{grid.weeks.length}</span>
                            <Button size="sm" variant="outline" disabled={weekIdx >= grid.weeks.length - 1} onClick={() => setWeekIdx(weekIdx + 1)} aria-label={__('Next week')}><Icon name="chevron-right" size={14} /></Button>
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full border-separate" style={{ borderSpacing: 0, minWidth: 120 + cols.length * 64 }}>
                            <thead>
                                <tr style={{ fontFamily: MONO }}>
                                    <th className={`${head} text-left`}>{__('Line')}</th>
                                    {cols.map((c) => (
                                        <th key={c.key} className={head} title={c.shift_name}>
                                            {formatDate(c.start, { weekday: 'short' })} {c.date.slice(8, 10)}.{c.date.slice(5, 7)}
                                            <br />{clock(c.start)}–{clock(c.end)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {grid.lines.map((line) => (
                                    <tr key={line.id} data-testid="staffing-row">
                                        <td className="whitespace-nowrap pr-2 text-[12.5px] font-semibold text-om-ink">{line.name}</td>
                                        {line.tracked ? cols.map((c) => <GridCell key={c.key} cell={grid.cell(line.id, c.key)} />) : (
                                            <td colSpan={Math.max(1, cols.length)} className="p-0.5">
                                                <div className="rounded-om-sm px-2 py-1.5 text-center text-[12px]" style={TONE_STYLE.untracked} data-testid="staffing-cell" data-tone="untracked">
                                                    {UNTRACKED_TEXT()}.{' '}
                                                    {canAssignOperators && <Link href={`/admin/lines/${line.id}#operators`} className="font-semibold text-om-accent hover:underline">{__('Assign operators')}</Link>}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap gap-3 text-[11.5px] text-om-muted">
                        {legend.map(([tone, label]) => (
                            <span key={tone} className="inline-flex items-center gap-1.5">
                                <span className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: TONE_STYLE[tone].background, outline: `1px solid ${TONE_STYLE[tone].color}` }} />{label}
                            </span>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

/** Each person away during the horizon, and the work orders their absence delayed or blocked. */
function HorizonAbsences({ absences }) {
    if (!absences?.length) return null;
    const day = (date) => `${date.slice(8, 10)}.${date.slice(5, 7)}`;

    return (
        <div className="space-y-2 rounded-om-sm border border-om-line bg-om-card p-4" data-testid="horizon-absences">
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Absences in this horizon')}</div>
            <ul className="divide-y divide-om-line">
                {absences.map((a) => (
                    <li key={a.worker_id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2 text-[12.5px] text-om-ink">
                        <span className="font-semibold">{a.name ?? `#${a.worker_id}`}</span>
                        <span className="text-om-muted" style={{ fontFamily: MONO }}>{(a.dates ?? []).map(day).join(', ')}</span>
                        <span className="text-om-muted">{(a.lines ?? []).map((l) => l.name).join(', ')}</span>
                        {a.affected_orders?.length ? (
                            <span>
                                <span className="text-om-muted">{__('Affected work orders')}: </span>
                                {a.affected_orders.map((o, i) => (
                                    <span key={o.work_order_id}>
                                        {i > 0 && ', '}
                                        <Link href={`/admin/work-orders/${o.work_order_id}`} className="font-semibold text-om-accent hover:underline" style={{ fontFamily: MONO }}>
                                            {o.order_no}
                                        </Link>
                                    </span>
                                ))}
                            </span>
                        ) : (
                            <span className="text-om-faint">{__('No work orders affected.')}</span>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function OperationsTable({ operations, stationsByType, canDecide, emptyText, untrackedLineIds }) {
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
                                {untrackedLineIds?.has(op.line_id) && (
                                    <div className="mt-1">
                                        <span className="inline-block rounded-om-sm bg-om-downtime-bg px-1.5 py-0.5 text-[10.5px] font-semibold text-om-downtime" title={UNTRACKED_TEXT()}>
                                            {__('staffing not tracked')}
                                        </span>
                                    </div>
                                )}
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
        respect_quality: defaults.respect_quality ?? true,
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
                <Checkbox
                    checked={!!form.data.respect_quality}
                    onChange={(v) => form.setData('respect_quality', v)}
                    label={__('Quality holds')}
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

/**
 * Whether the draft went stale, kept current without a reload: the page's
 * answer at load, asked again whenever the planner returns to the tab —
 * e.g. after unassigning someone in another one.
 */
function useDraftStale(draftId, initial) {
    const [stale, setStale] = useState(initial);
    useEffect(() => { setStale(initial); }, [draftId, initial]);
    useEffect(() => {
        if (!draftId) return undefined;
        let controller = null;
        const stop = refreshOnReturn(async () => {
            controller?.abort();
            controller = new AbortController();
            try {
                const r = await apiGet(`/admin/schedule/simulations/${draftId}/status`, { signal: controller.signal });
                if (!r.ok) return;
                const json = await r.json();
                if (json?.id === draftId) setStale(json.stale ?? null);
            } catch { /* aborted or offline — the next return asks again */ }
        });
        return () => { stop(); controller?.abort(); };
    }, [draftId]);
    return stale;
}

const NAV_LINK = 'inline-flex items-center gap-2 rounded-om-sm border border-om-line px-3 py-2 text-xs font-semibold text-om-ink hover:bg-om-chip';

export default function AutoSchedule() {
    const { draft, draftStale: staleAtLoad = null, committed = [], stationsByType = {}, defaults, canDecide, canAssignOperators = false } = usePage().props;
    const draftStale = useDraftStale(draft?.id ?? null, staleAtLoad);
    const { confirm, dialog } = useConfirm();
    const [showOnlyProblems, setShowOnlyProblems] = useState(false);
    const placed = (draft?.counts?.scheduled ?? 0) + (draft?.counts?.scheduled_late ?? 0);
    const problems = (draft?.operations ?? []).filter((op) => op.status !== 'scheduled');
    const draftOps = showOnlyProblems ? problems : (draft?.operations ?? []);
    const untrackedLineIds = new Set((draft?.workforce ?? []).filter((w) => !w.tracked && w.respected !== false).map((w) => w.line_id));

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

                        {/* Already translated by the server; Apply refuses for the same reason. */}
                        {draftStale && <InlineAlert severity="warning" title={draftStale.message} />}

                        <StaffingPanel workforce={draft.workforce} canAssignOperators={canAssignOperators} />
                        {draft.staffing && (
                            <>
                                <StaffingByShift staffing={draft.staffing} canAssignOperators={canAssignOperators} />
                                <HorizonAbsences absences={draft.staffing.absences} />
                            </>
                        )}

                        <div className="flex justify-end">
                            <Checkbox size="sm" checked={showOnlyProblems} onChange={setShowOnlyProblems} label={`${__('Only late or infeasible')} (${problems.length})`} />
                        </div>
                        <OperationsTable
                            operations={draftOps}
                            stationsByType={stationsByType}
                            canDecide={canDecide}
                            untrackedLineIds={untrackedLineIds}
                            emptyText={showOnlyProblems && draft.operations?.length
                                ? __('Every operation is scheduled on time.')
                                : __('No work orders to schedule — every active order already has a time plan, or none has a routing.')}
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
