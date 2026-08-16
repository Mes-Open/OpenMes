import { __, formatNumber } from '../../lib/i18n';
import { scoreColor } from './tokens';

/**
 * The header band: which station and shift you are looking at, how the shift is
 * tracking, the live rate chart, and whatever is demanding attention.
 */
export default function TopStrip({
    snapshot,
    metric,
    onMetric,
    onStationStep,
    onShiftStep,
    onJumpLive,
    onOpenAttention,
}) {
    const { station, shift, batch, previousBatches, chart, attention } = snapshot;

    // Capped on desktop so the strip cannot grow at the timeline's expense: the
    // rows below take whatever the viewport has left, and this is the panel that
    // can afford to be smaller. Only the row layout is capped — stacked on a
    // narrow screen it needs its full height.
    return (
        <div className="flex flex-shrink-0 flex-col items-stretch border-b border-om-line2 xl:max-h-[272px] xl:flex-row">
            <StationPanel
                station={station}
                shift={shift}
                batch={batch}
                previousBatches={previousBatches}
                onStationStep={onStationStep}
            />

            <div className="flex min-w-0 flex-1 flex-col border-om-line2 px-5 py-3 xl:border-r">
                <div className="mb-2.5 flex flex-wrap items-center gap-3">
                    <StepButton onClick={() => onShiftStep(-1)} label={__('Previous shift')}>‹</StepButton>
                    <StepButton onClick={() => onShiftStep(1)} label={__('Next shift')}>›</StepButton>
                    <StepButton onClick={onJumpLive} label={__('Jump to now')}>»</StepButton>
                    <div>
                        <div className="font-mono text-[8.5px] uppercase tracking-[0.12em] text-om-faint">
                            {__('Shift')}
                        </div>
                        <div className="mt-px text-lg font-semibold tracking-[-0.015em] text-om-ink">
                            {shift.label}
                            <span className="ml-2 font-mono text-[10px] font-normal text-om-faint">
                                {shift.window}
                            </span>
                        </div>
                    </div>
                    {shift.isLive && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-om-running-bg px-2.5 py-1 font-mono text-[9.5px] tracking-[0.08em] text-om-running">
                            <span className="h-1.5 w-1.5 animate-om-pulse rounded-full bg-om-running" />
                            {__('LIVE')}
                        </span>
                    )}
                    <div className="ml-auto flex gap-[3px] rounded-om-sm border border-om-line2 bg-om-bg p-[3px]">
                        {[['rate', __('pcs/min')], ['percent', __('% of rate')]].map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => onMetric(key)}
                                className={`rounded-md px-2.5 py-[5px] font-mono text-[10.5px] font-semibold ${
                                    metric === key ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                <RateChart chart={chart} metric={metric} />
            </div>

            <div className="flex w-full flex-shrink-0 flex-col items-end justify-center px-5 py-3 xl:w-52">
                <div className="w-full">
                    {attention.count > 0 ? (
                        <button
                            type="button"
                            onClick={onOpenAttention}
                            className="w-full rounded-[11px] border border-om-blocked bg-om-blocked-bg px-[13px] py-3 text-left"
                        >
                            <div className="mb-[5px] flex items-center gap-2">
                                <span className="flex h-4 w-4 animate-om-blink items-center justify-center rounded-full bg-om-blocked text-[10px] font-bold text-white">
                                    ?
                                </span>
                                <span className="font-mono text-[9.5px] font-semibold tracking-[0.06em] text-om-blocked">
                                    {__('NEEDS A CAUSE')}
                                </span>
                            </div>
                            <div className="text-[12.5px] leading-snug text-om-ink">
                                {/* Phrased so the count never has to agree with a
                                    noun — languages with plural cases (pl) can't
                                    render ":count stops" from one string. */}
                                {__('Unclassified stops: :count — oldest is :minutes min at :time', {
                                    count: attention.count,
                                    minutes: attention.first.minutes,
                                    time: attention.first.at,
                                })}
                            </div>
                        </button>
                    ) : (
                        <div className="rounded-[11px] border border-om-running bg-om-running-bg px-[13px] py-3">
                            <div className="mb-1 font-mono text-[9.5px] font-semibold tracking-[0.06em] text-om-running">
                                {__('ALL STOPS CLASSIFIED')}
                            </div>
                            <div className="text-[12.5px] text-om-muted">{__('Shift log is complete.')}</div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function StationPanel({ station, shift, batch, previousBatches, onStationStep }) {
    return (
        <div className="flex-shrink-0 overflow-hidden border-om-line2 px-5 py-3 xl:w-[392px] xl:border-r">
            <div className="mb-2 flex items-center gap-2.5">
                <StepButton onClick={() => onStationStep(-1)} label={__('Previous station')}>‹</StepButton>
                <StepButton onClick={() => onStationStep(1)} label={__('Next station')}>›</StepButton>
                <div className="ml-0.5 min-w-0">
                    <div className="font-mono text-[8.5px] uppercase tracking-[0.12em] text-om-faint">
                        {__('Station')}
                    </div>
                    <div className="mt-px truncate text-[17px] font-semibold tracking-[-0.015em] text-om-ink">
                        {station.code}
                    </div>
                </div>
                <span className="ml-auto truncate text-right font-mono text-[9.5px] leading-normal text-om-faint">
                    {station.name}
                    <br />
                    {station.line}
                </span>
            </div>

            <div className="mb-2.5 flex items-end justify-between gap-4">
                <div>
                    <div className="mb-1 font-mono text-[8.5px] uppercase tracking-[0.12em] text-om-faint">
                        {__('Shift quantity')}
                    </div>
                    <div className="flex items-baseline gap-[7px]">
                        <span className="font-mono text-[28px] font-semibold leading-none tracking-[-0.02em] text-om-ink">
                            {formatNumber(shift.quantity)}
                        </span>
                        <span className="font-mono text-xs text-om-faint">
                            / {shift.target === null ? '—' : formatNumber(shift.target)} {__('pcs')}
                        </span>
                    </div>
                </div>
                <div className="text-right">
                    <div className="mb-1 font-mono text-[8.5px] uppercase tracking-[0.12em] text-om-faint">
                        {__('OEE')}
                    </div>
                    <span
                        className="font-mono text-[28px] font-semibold leading-none tracking-[-0.02em]"
                        style={{ color: scoreColor(shift.oee) }}
                    >
                        {shift.oee === null ? '—' : `${shift.oee}%`}
                    </span>
                </div>
            </div>

            <div className="mb-1 font-mono text-[8.5px] uppercase tracking-[0.12em] text-om-faint">
                {__('Current batch')}
            </div>
            {batch ? (
                <>
                    <div className="mb-1 flex items-baseline justify-between gap-3">
                        <span className="truncate text-[14px] font-semibold text-om-ink">{batch.product}</span>
                    </div>
                    <div className="mb-1.5 h-[7px] overflow-hidden rounded-full bg-om-track">
                        <div className="h-full rounded-full bg-om-running" style={{ width: `${batch.percent}%` }} />
                    </div>
                    <div className="flex items-baseline justify-between gap-2">
                        <span className="font-mono text-[9.5px] text-om-faint">{batch.lot}</span>
                        <span className="font-mono text-[10.5px] text-om-muted">
                            {formatNumber(batch.made)}{' '}
                            <span className="text-om-blocked">({formatNumber(batch.scrap)})</span> /{' '}
                            {formatNumber(batch.plan)} {__('pcs')}
                        </span>
                    </div>
                </>
            ) : (
                <p className="text-[12.5px] text-om-muted">{__('No batch running.')}</p>
            )}

            {previousBatches.length > 0 && (
                <div className="mt-2 border-t border-om-line2 pt-1.5">
                    <div className="mb-1 font-mono text-[8.5px] uppercase tracking-[0.12em] text-om-faint">
                        {__('Previous')}
                    </div>
                    {previousBatches.map((b) => (
                        <div key={b.id} className="mb-0.5 flex items-baseline justify-between gap-2.5">
                            {/* One line each: a lot number is 19 characters and
                                was wrapping in an 86px column, so three past
                                batches cost six lines and pushed the timeline
                                down the screen. */}
                            <span className="w-[112px] flex-shrink-0 truncate font-mono text-[10px] text-om-faint" title={b.lot}>
                                {b.lot}
                            </span>
                            <span className="min-w-0 flex-1 truncate text-[11.5px] text-om-muted">{b.product}</span>
                            <span className="font-mono text-[10px] text-om-muted">
                                {formatNumber(b.made)} <span className="text-om-blocked">({formatNumber(b.scrap)})</span> /{' '}
                                {formatNumber(b.plan)}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * The rolling rate window. Bars are the same 2-minute samples the backend
 * emits; the newest sits at the right edge and is accented, so "now" is
 * findable without reading the axis.
 */
function RateChart({ chart, metric }) {
    const max = metric === 'rate' ? chart.rateMax : 100;
    const ticks = metric === 'rate' ? chart.rateTicks : chart.percentTicks;
    const last = chart.samples.length - 1;

    return (
        <div className="flex max-h-[172px] min-h-[76px] flex-1 gap-[9px]">
            <div className="flex w-[26px] flex-col items-end justify-between pb-[15px]">
                {ticks.map((tick) => (
                    <span key={tick} className="font-mono text-[8.5px] text-om-faint">{tick}</span>
                ))}
            </div>
            <div className="flex min-w-0 flex-1 flex-col">
                <div className="relative flex-1 border-b border-l border-om-line2">
                    {[25, 50, 75].map((y) => (
                        <div
                            key={y}
                            className="absolute inset-x-0 h-px bg-om-line2 opacity-50"
                            style={{ bottom: `${y}%` }}
                        />
                    ))}
                    <div className="absolute inset-0 flex items-end gap-px px-px">
                        {chart.samples.map((sample, i) => {
                            const value = metric === 'rate' ? sample.rate : sample.ratePercent;
                            const height = Math.max(1.5, (value / max) * 100);
                            // The server already decided run vs slow for these
                            // minutes, against the counter feed. Re-deriving it
                            // here from a 2-minute average would let a bar read
                            // green above an orange row on the same timeline.
                            const color = !sample.inRange
                                ? 'var(--om-line2)'
                                : i === last
                                    ? 'var(--om-accent)'
                                    : SAMPLE_COLOR[sample.kind] ?? 'var(--om-running)';

                            return (
                                <div
                                    key={`${sample.label}-${i}`}
                                    className="flex-1 rounded-[1px]"
                                    style={{ height: `${height}%`, background: color }}
                                    title={`${sample.label} · ${value}${metric === 'rate' ? ' ' + __('pcs/min') : '%'}`}
                                />
                            );
                        })}
                    </div>
                </div>
                <div className="mt-[5px] flex justify-between">
                    {chart.samples
                        .filter((_, i) => i % 4 === 0)
                        .map((sample, i) => (
                            <span key={`${sample.label}-${i}`} className="font-mono text-[8.5px] text-om-faint">
                                {sample.label}
                            </span>
                        ))}
                </div>
            </div>
        </div>
    );
}

/** A rate-chart bar takes the colour of the machine state behind it. */
const SAMPLE_COLOR = {
    run: 'var(--om-running)',
    slow: 'var(--om-downtime)',
    down: 'var(--om-blocked)',
    plan: 'var(--om-planned)',
    idle: 'var(--om-line2)',
};

function StepButton({ onClick, label, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title={label}
            className="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] border border-om-line text-[13px] text-om-muted hover:border-om-accent hover:text-om-ink"
        >
            {children}
        </button>
    );
}
