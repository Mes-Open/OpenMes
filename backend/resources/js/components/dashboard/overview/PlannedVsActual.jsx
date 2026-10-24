import { OverviewCard } from './OverviewGrid';
import { __, formatNumber, formatTime } from '../../../lib/i18n';

// Plot area inside the 800×250 viewBox (not OeeGauge's 0 0 100 60 — the
// oee-smoke e2e spec finds the gauge on this page by its viewBox).
const X0 = 50;
const X1 = 780;
const Y0 = 20;
const Y1 = 220;

const PLAN_PAST = 'var(--om-muted)';
const PLAN_FUTURE = 'var(--om-faintest)';
const ACTUAL = 'var(--om-accent)';
const BEHIND = 'var(--om-downtime)';
const AHEAD = 'var(--om-running)';

const pcs = (value) => formatNumber(Math.round(value));

/** Round a maximum up to 4 "nice" gridline steps (1, 1.5, 2, 2.5, 3, 4, 5, 6, 8 × 10ⁿ). */
export function niceMax(value) {
    const raw = Math.max(value, 4) / 4;
    const pow = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].find((m) => m * pow >= raw) * pow;
    return step * 4;
}

/** "−179 pcs behind pace" / "+40 pcs ahead of pace" / "On pace". */
export function paceText(delta) {
    if (delta == null) return null;
    if (delta < 0) return { text: __(':qty pcs behind pace', { qty: `−${pcs(-delta)}` }), color: BEHIND };
    if (delta > 0) return { text: __(':qty pcs ahead of pace', { qty: `+${pcs(delta)}` }), color: AHEAD };
    return { text: __('On pace'), color: 'var(--om-muted)' };
}

function Legend({ hasPlan }) {
    return (
        <div className="flex gap-4 text-[13px] text-om-muted">
            {hasPlan && (
                <span className="inline-flex items-center gap-1.5">
                    <span aria-hidden="true" className="w-4 border-t-2 border-dashed border-om-muted" />
                    {__('Planned')}
                </span>
            )}
            <span className="inline-flex items-center gap-1.5">
                <span aria-hidden="true" className="h-[3px] w-4 rounded-sm bg-om-accent" />
                {__('Actual')}
            </span>
        </div>
    );
}

/**
 * "Production · planned vs actual": pieces made this shift against the
 * schedule — headline figures and a cumulative chart (dashed plan for the
 * whole shift, orange actual up to now, the gap at now). Data is
 * `overview.production` from ShiftPlanService.
 */
export default function PlannedVsActual({ overview }) {
    const production = overview?.production;
    const title = __('Production · planned vs actual');

    if (!production?.has_lines) {
        return (
            <OverviewCard title={title}>
                <p className="m-0 text-[14px] text-om-muted">{__('No active lines')}</p>
            </OverviewCard>
        );
    }

    const { totals } = production;
    const hasPlan = totals.planned != null;
    const pace = paceText(totals.pace_delta);

    return (
        <OverviewCard>
            <div className="flex flex-wrap items-start justify-between gap-3" data-testid="planned-vs-actual">
                <div className="flex flex-col gap-1 min-w-0">
                    <h2 className="m-0 text-[18px] font-semibold text-om-ink">{title}</h2>
                    <div className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                        <span className="font-mono text-[28px] font-semibold tracking-[-0.02em] text-om-ink" data-testid="pva-figure">
                            {totals.actual == null ? '—' : pcs(totals.actual)}
                            {hasPlan && ` / ${pcs(totals.planned)}`}
                        </span>
                        <span className="text-[14px] text-om-muted">
                            {hasPlan && totals.pct_of_plan != null
                                ? __('pcs · :pct% of shift plan', { pct: totals.pct_of_plan })
                                : __('pcs')}
                        </span>
                        {pace && (
                            <span className="text-[14px] font-medium" style={{ color: pace.color }} data-testid="pva-pace">
                                {pace.text}
                            </span>
                        )}
                    </div>
                    {!hasPlan && (
                        <p className="m-0 text-[13px] text-om-muted">{__('No plan scheduled for this shift')}</p>
                    )}
                </div>
                <Legend hasPlan={hasPlan} />
            </div>
            <Chart production={production} hasPlan={hasPlan} pace={pace} />
        </OverviewCard>
    );
}

function Chart({ production, hasPlan, pace }) {
    const { cumulative, totals, start, end, now, is_live: isLive } = production;
    const t0 = new Date(start).getTime();
    const t1 = new Date(end).getTime();
    const tNow = Math.min(Math.max(new Date(now).getTime(), t0), t1);

    const actualPoints = cumulative.filter((p) => p.actual != null);
    const peak = Math.max(totals.planned ?? 0, ...actualPoints.map((p) => p.actual), 0);
    const yMax = niceMax(peak);

    const x = (iso) => X0 + ((new Date(iso).getTime() - t0) / (t1 - t0)) * (X1 - X0);
    const y = (v) => Y1 - (v / yMax) * (Y1 - Y0);
    const xy = (p, key) => `${x(p.at).toFixed(1)},${y(p[key]).toFixed(1)}`;

    const xNow = X0 + ((tNow - t0) / (t1 - t0)) * (X1 - X0);
    const planPast = hasPlan ? cumulative.filter((p) => new Date(p.at).getTime() <= tNow) : [];
    const planFuture = hasPlan ? cumulative.filter((p) => new Date(p.at).getTime() >= tNow) : [];
    const last = actualPoints[actualPoints.length - 1];

    // Every second hour boundary gets a time label, and always the shift end.
    const hourMarks = cumulative.filter((p) => {
        const t = new Date(p.at).getTime();
        return (t - t0) % 3_600_000 === 0 || t === t1;
    });
    const xTicks = hourMarks.filter((p, i) => i % 2 === 0 || i === hourMarks.length - 1);
    const dots = actualPoints.filter((p) => {
        const t = new Date(p.at).getTime();
        return t !== t0 && t !== tNow;
    });

    const gridValues = [1, 2, 3, 4].map((i) => (yMax / 4) * i);

    const label = hasPlan
        ? __('Cumulative pieces this shift: planned :plan, actual :actual at :time', {
            plan: pcs(totals.planned_to_now ?? 0), actual: pcs(totals.actual ?? 0), time: formatTime(now),
        })
        : __('Cumulative pieces this shift: actual :actual at :time', { actual: pcs(totals.actual ?? 0), time: formatTime(now) });

    return (
        <svg role="img" aria-label={label} viewBox="0 0 800 250" className="block h-auto w-full font-mono" data-testid="pva-chart">
            <g stroke="var(--om-track)" strokeWidth="1">
                {gridValues.map((v) => <line key={v} x1={X0} x2={X1} y1={y(v)} y2={y(v)} />)}
            </g>
            <line x1={X0} x2={X1} y1={Y1} y2={Y1} stroke="var(--om-faintest)" strokeWidth="1" />
            <g fill="var(--om-muted)" fontSize="11" textAnchor="end">
                {[0, ...gridValues].map((v) => <text key={v} x={X0 - 8} y={y(v) + 4}>{pcs(v)}</text>)}
            </g>
            <g fill="var(--om-muted)" fontSize="11" textAnchor="middle">
                {xTicks.map((p) => <text key={p.at} x={x(p.at)} y="242">{formatTime(p.at)}</text>)}
            </g>

            {actualPoints.length > 1 && (
                <path
                    d={`M${actualPoints.map((p) => xy(p, 'actual')).join(' L')} L${x(last.at).toFixed(1)},${Y1} L${X0},${Y1} Z`}
                    fill={ACTUAL}
                    fillOpacity="0.08"
                />
            )}
            {planPast.length > 1 && (
                <polyline points={planPast.map((p) => xy(p, 'planned')).join(' ')} fill="none" stroke={PLAN_PAST} strokeWidth="2" strokeDasharray="6 5" />
            )}
            {planFuture.length > 1 && (
                <polyline points={planFuture.map((p) => xy(p, 'planned')).join(' ')} fill="none" stroke={PLAN_FUTURE} strokeWidth="2" strokeDasharray="6 5" />
            )}
            {actualPoints.length > 1 && (
                <polyline points={actualPoints.map((p) => xy(p, 'actual')).join(' ')} fill="none" stroke={ACTUAL} strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
            )}
            <g fill="var(--om-card)" stroke={ACTUAL} strokeWidth="2">
                {dots.map((p) => <circle key={p.at} cx={x(p.at)} cy={y(p.actual)} r="3.5" />)}
            </g>

            {isLive && (
                <g>
                    <line x1={xNow} x2={xNow} y1={Y0} y2={Y1} stroke="var(--om-ink)" strokeWidth="1" strokeDasharray="2 3" />
                    <text x={xNow} y="14" fontSize="11" fill="var(--om-ink)" textAnchor="middle" fontWeight="600">
                        {__('now :time', { time: formatTime(now) })}
                    </text>
                    {hasPlan && totals.planned_to_now != null && totals.actual != null && (
                        <>
                            <line x1={xNow + 7} x2={xNow + 7} y1={y(totals.planned_to_now)} y2={y(totals.actual)} stroke={pace?.color} strokeWidth="2" />
                            <circle cx={xNow} cy={y(totals.planned_to_now)} r="4" fill="var(--om-card)" stroke={PLAN_PAST} strokeWidth="2" />
                            {totals.pace_delta !== 0 && (
                                <text
                                    x={xNow > X1 - 90 ? xNow - 15 : xNow + 15}
                                    y={(y(totals.planned_to_now) + y(totals.actual)) / 2 + 4}
                                    fontSize="12"
                                    fill={pace?.color}
                                    fontWeight="600"
                                    textAnchor={xNow > X1 - 90 ? 'end' : 'start'}
                                >
                                    {`${totals.pace_delta < 0 ? '−' : '+'}${pcs(Math.abs(totals.pace_delta))} ${__('pcs')}`}
                                </text>
                            )}
                        </>
                    )}
                    {totals.actual != null && <circle cx={xNow} cy={y(totals.actual)} r="5" fill={ACTUAL} />}
                </g>
            )}
        </svg>
    );
}
