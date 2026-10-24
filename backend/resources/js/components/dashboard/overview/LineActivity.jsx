import { Link } from '@inertiajs/react';
import { __, formatTime } from '../../../lib/i18n';
import Tooltip from '../../Tooltip';
import { pct } from '../../shift-monitor/tokens';
import { OverviewCard } from './OverviewGrid';
import { LINE_STATE_COLOR, LINE_STATES, lineStateLabel } from './lineState';

// Name | bar | uptime — the mockup's three columns.
const ROW_GRID = 'grid grid-cols-[120px_minmax(0,1fr)_76px] gap-4';

/** "1 h 05 min" / "12 min". */
function duration(minutes) {
    if (minutes >= 60) {
        return __(':hours h :minutes min', {
            hours: Math.floor(minutes / 60),
            minutes: String(minutes % 60).padStart(2, '0'),
        });
    }
    return __(':count min', { count: minutes });
}

/** Mockup thresholds: green from 85 %, orange from 70 %, red below. */
function uptimeClass(uptime) {
    if (uptime === null || uptime === undefined) return 'text-om-muted';
    if (uptime >= 85) return 'text-om-running';
    if (uptime >= 70) return 'text-om-downtime';
    return 'text-om-blocked';
}

/**
 * Clock-hour ticks inside the shift, as minute offsets from its start. The
 * minute-of-hour the shift starts on is read in the plant timezone (formatTime),
 * so a half-hour-offset zone still gets its ticks on the plant's full hours.
 */
function hourTicks(start, span) {
    if (!(span > 0)) return [];
    const startMinute = Number(formatTime(start, { minute: 'numeric' })) || 0;
    const step = (span <= 360 ? 1 : span <= 720 ? 2 : 3) * 60;
    const ticks = [];
    for (let m = (60 - startMinute) % 60; m <= span; m += step) ticks.push(m);
    return ticks;
}

function addMinutes(iso, minutes) {
    return new Date(Date.parse(iso) + minutes * 60000);
}

function LegendItem({ color, label, now = false }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span
                aria-hidden="true"
                className={now ? 'h-3 w-0.5' : 'h-2.5 w-2.5 rounded-sm'}
                style={{ background: color }}
            />
            {label}
        </span>
    );
}

function Segment({ segment, span }) {
    const lines = [
        lineStateLabel(segment.state),
        `${formatTime(segment.starts_at)}–${formatTime(segment.ends_at)} · ${duration(segment.minutes)}`,
        segment.reason ?? (segment.needs_reason ? __('Cause not recorded') : null),
    ].filter(Boolean);

    return (
        <Tooltip
            label={<span className="flex flex-col gap-0.5">{lines.map((l, i) => <span key={i}>{l}</span>)}</span>}
        >
            <div
                tabIndex={0}
                role="img"
                aria-label={lines.join(', ')}
                data-testid="line-activity-segment"
                data-state={segment.state}
                className="absolute top-0 bottom-0 outline-none focus-visible:ring-2 focus-visible:ring-om-ink"
                style={{
                    left: pct(segment.from, span),
                    width: pct(segment.minutes, span),
                    background: LINE_STATE_COLOR[segment.state] ?? LINE_STATE_COLOR.none,
                    boxShadow: 'inset -1px 0 0 var(--om-card)',
                }}
            />
        </Tooltip>
    );
}

function LineRow({ line, span, elapsed, canOpenShift }) {
    const nowLeft = pct(Math.min(elapsed, span), span);

    return (
        <div className={`${ROW_GRID} items-center`} data-testid={`line-activity-row-${line.id}`}>
            {canOpenShift ? (
                <Link
                    href={`/admin/shift-overview?line=${line.id}`}
                    className="truncate text-[14px] font-semibold text-om-ink hover:underline"
                    title={__('Open the shift overview for :line', { line: line.name })}
                >
                    {line.name}
                </Link>
            ) : (
                <span className="truncate text-[14px] font-semibold text-om-ink" title={line.name}>{line.name}</span>
            )}

            <div className="relative h-6 overflow-hidden rounded-md" style={{ background: 'var(--om-track)' }}>
                {line.has_data ? (
                    line.segments.map((s) => <Segment key={s.from} segment={s} span={span} />)
                ) : (
                    <>
                        <div
                            aria-hidden="true"
                            className="absolute top-0 bottom-0 left-0"
                            style={{ width: nowLeft, background: LINE_STATE_COLOR.none }}
                        />
                        <span className="absolute inset-0 flex items-center justify-center text-[12px] text-om-muted">
                            {__('No data')}
                        </span>
                    </>
                )}
                <div
                    aria-hidden="true"
                    data-testid="line-activity-now"
                    className="absolute top-0 bottom-0 w-0.5 -translate-x-1/2"
                    style={{ left: nowLeft, background: 'var(--om-ink)' }}
                />
            </div>

            <span className={`text-right font-mono text-[14px] font-semibold ${uptimeClass(line.uptime)}`}>
                {line.uptime === null || line.uptime === undefined ? '—' : `${line.uptime}%`}
            </span>
        </div>
    );
}

/**
 * "Line activity · this shift": one bar per active line from shift start to
 * shift end — what the line did up to the Now marker, light track after it —
 * and its uptime. Data: `overview.timeline` (PlantOverviewService::lineTimeline).
 */
export default function LineActivity({ overview, accessibleTabs = [] }) {
    const timeline = overview?.timeline;
    const lines = timeline?.lines ?? [];
    const span = timeline?.span ?? 0;
    const canOpenShift = accessibleTabs.includes('shift_monitor');

    const legend = (
        <div className="flex flex-wrap gap-3.5 text-[13px] text-om-muted">
            {LINE_STATES.map((state) => (
                <LegendItem key={state} color={LINE_STATE_COLOR[state]} label={lineStateLabel(state)} />
            ))}
            <LegendItem color="var(--om-ink)" label={__('Now')} now />
        </div>
    );

    let body;
    if (!timeline || !(span > 0) || !overview?.shift?.is_live) {
        body = <p className="text-[14px] text-om-muted">{__('No shift running')}</p>;
    } else if (lines.length === 0) {
        body = <p className="text-[14px] text-om-muted">{__('No active lines')}</p>;
    } else {
        const ticks = hourTicks(timeline.start, span);
        body = (
            <div className="overflow-x-auto">
                <div className="flex min-w-[560px] flex-col gap-2.5">
                    <div className={`${ROW_GRID} text-[12px] text-om-muted`}>
                        <span />
                        <div className="relative h-4 font-mono" aria-hidden="true">
                            {ticks.map((m, i) => (
                                <span
                                    key={m}
                                    className={`absolute top-0 whitespace-nowrap ${
                                        m === 0 ? '' : m === span || (i === ticks.length - 1 && m > span - 30)
                                            ? '-translate-x-full'
                                            : '-translate-x-1/2'
                                    }`}
                                    style={{ left: pct(m, span) }}
                                >
                                    {formatTime(addMinutes(timeline.start, m))}
                                </span>
                            ))}
                        </div>
                        <span className="text-right">{__('Uptime')}</span>
                    </div>
                    {lines.map((line) => (
                        <LineRow
                            key={line.id}
                            line={line}
                            span={span}
                            elapsed={timeline.elapsed}
                            canOpenShift={canOpenShift}
                        />
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div data-testid="line-activity" className="h-full">
            <OverviewCard title={__('Line activity · this shift')} action={legend}>
                {body}
            </OverviewCard>
        </div>
    );
}
