import OverviewCard from './OverviewCard';
import useWidth from './useWidth';
import { __, formatNumber, formatTime } from '../../../lib/i18n';

// Bar area height in px; the value label sits in the headroom above it.
const PLOT = 180;
const HEIGHT = 200;

// The hour still running: hatched, because its total is not final yet.
const CURRENT_FILL = 'repeating-linear-gradient(135deg, var(--om-accent) 0 6px, color-mix(in srgb, var(--om-accent) 55%, transparent) 6px 10px)';

const pcs = (value) => formatNumber(Math.round(value));

/** Value above a bar: "5.3K" once the columns are too narrow for "5,318". */
export function barLabel(value, compact) {
    return compact && Math.abs(value) >= 1000
        ? formatNumber(value, { notation: 'compact', maximumFractionDigits: 1 })
        : pcs(value);
}

/**
 * One factual sentence from `summary` (ShiftPlanService): since when the
 * completed hours have been below / above plan, plus unplanned downtime in
 * that time — no causes beyond what the downtime log records.
 */
export function summaryText(summary) {
    switch (summary?.kind) {
        case 'below': {
            const parts = [__('Below plan since :time', { time: formatTime(summary.since) })];
            if (summary.downtime_minutes) {
                parts.push(__(':minutes min unplanned downtime on :lines', {
                    minutes: summary.downtime_minutes,
                    lines: summary.downtime_lines.join(', '),
                }));
            }
            return { text: parts.join(' · '), className: 'text-om-downtime' };
        }
        case 'above':
            return { text: __('Above plan since :time', { time: formatTime(summary.since) }), className: 'text-om-running' };
        case 'on':
            return { text: __('On plan'), className: 'text-om-muted' };
        case 'not_started':
            return { text: __('The first hour is still running'), className: 'text-om-muted' };
        case 'no_plan':
            return { text: __('No plan scheduled for this shift'), className: 'text-om-muted' };
        default:
            return null;
    }
}

/**
 * "Output per hour": a bar per hour of the shift — done hours solid, the
 * running hour hatched, future hours as empty slots — against each hour's
 * plan (the legend gives the shift's average pcs/h), with a one-line summary
 * underneath. Data is
 * `overview.production` from ShiftPlanService.
 */
export default function OutputPerHour({ overview }) {
    const [barsRef, barsWidth] = useWidth(800);
    const production = overview?.production;
    const title = __('Output per hour');

    if (!production?.has_lines) {
        return (
            <OverviewCard title={title}>
                <p className="m-0 text-[14px] text-om-muted">{__('No active lines')}</p>
            </OverviewCard>
        );
    }

    const { hours, plan_per_hour: planPerHour } = production;
    // ~9 px per mono digit at 12 px: a column narrower than 56 px can't fit "5,318".
    const compact = barsWidth / Math.max(hours.length, 1) < 56;
    const hasPlan = planPerHour != null;
    const peak = Math.max(planPerHour ?? 0, ...hours.map((h) => Math.max(h.actual ?? 0, h.planned ?? 0)), 1);
    const scale = PLOT / (peak * 1.1);
    const summary = summaryText(production.summary);

    const label = `${__('Pieces produced per hour this shift')}: ${hours
        .filter((h) => h.actual != null)
        .map((h) => `${formatTime(h.start)} ${pcs(h.actual)}`)
        .join(', ')}${hasPlan ? `; ${__('Plan :qty pcs/h', { qty: pcs(planPerHour) })}` : ''}`;

    return (
        <OverviewCard>
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="m-0 text-[18px] font-semibold text-om-ink">{title}</h2>
                <div className="flex gap-4 text-[13px] text-om-muted">
                    <span className="inline-flex items-center gap-1.5">
                        <span aria-hidden="true" className="h-2.5 w-2.5 rounded-sm bg-om-accent" />
                        {__('Produced')}
                    </span>
                    {hasPlan && (
                        <span className="inline-flex items-center gap-1.5" data-testid="output-plan-legend">
                            <span aria-hidden="true" className="w-4 border-t-2 border-dashed border-om-ink" />
                            {__('Plan :qty pcs/h', { qty: pcs(planPerHour) })}
                        </span>
                    )}
                </div>
            </div>

            {/* A twelve-hour shift doesn't fit a phone: scroll rather than overlap. */}
            <div ref={barsRef} className="-mx-1 overflow-x-auto px-1">
                <div style={{ minWidth: `${hours.length * 34}px` }}>
                    <div
                        role="img"
                        aria-label={label}
                        className="relative flex items-end gap-2 border-b border-om-line sm:gap-3"
                        style={{ height: HEIGHT }}
                        data-testid="output-bars"
                    >
                        {hours.map((h) => (
                            <div key={h.start} className="relative flex h-full min-w-0 flex-1 flex-col justify-end gap-1" data-phase={h.phase}>
                                {h.phase === 'future' ? (
                                    <div className="rounded-t-[4px] bg-om-track" style={{ height: PLOT }} />
                                ) : (
                                    <>
                                        <span
                                            className={`text-center font-mono text-[12px] font-semibold ${
                                                h.phase === 'past' && h.planned != null && Math.round(h.actual) < Math.round(h.planned)
                                                    ? 'text-om-downtime'
                                                    : 'text-om-ink'
                                            }`}
                                        >
                                            {barLabel(h.actual, compact)}
                                        </span>
                                        <div
                                            className="rounded-t-[4px]"
                                            style={{
                                                height: Math.max(h.actual * scale, 2),
                                                background: h.phase === 'current' ? CURRENT_FILL : 'var(--om-accent)',
                                            }}
                                        />
                                    </>
                                )}
                                {/* This hour's own plan. Bars and the summary are judged against
                                    it, so it is drawn per hour; an even plan reads as one line. */}
                                {h.planned > 0 && (
                                    <div
                                        aria-hidden="true"
                                        className="pointer-events-none absolute -inset-x-1.5 border-t-2 border-dashed border-om-ink opacity-50"
                                        style={{ bottom: h.planned * scale }}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="mt-1.5 flex gap-2 sm:gap-3">
                        {hours.map((h) => (
                            <span
                                key={h.start}
                                className={`min-w-0 flex-1 text-center font-mono text-[12px] ${
                                    h.phase === 'current' ? 'font-semibold text-om-ink' : 'text-om-muted'
                                }`}
                            >
                                {/* "22" rather than "22:00" when "22:00" would collide. */}
                                {compact ? formatTime(h.start, { hour: '2-digit' }) : formatTime(h.start)}
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            {summary && (
                <p className={`m-0 text-[14px] font-medium ${summary.className}`} data-testid="output-summary">
                    {summary.text}
                </p>
            )}
        </OverviewCard>
    );
}
