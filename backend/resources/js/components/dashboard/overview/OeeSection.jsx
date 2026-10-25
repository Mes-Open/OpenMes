import { useId } from 'react';
import { __, formatNumber } from '../../../lib/i18n';
import OverviewCard from './OverviewCard';

// Below this a figure is red whatever its target — App\Support\OeeBand::RED_BELOW.
const RED_BELOW = 65;

const TONE_TEXT = { ok: 'text-om-running', warn: 'text-om-downtime', bad: 'text-om-blocked' };
const TONE_FILL = { ok: 'bg-om-running', warn: 'bg-om-downtime', bad: 'bg-om-blocked' };

/** Green at or above its own target, amber down to RED_BELOW, red under it. */
function tone(value, target) {
    if (value == null) return null;
    if (value >= target) return 'ok';
    return value >= RED_BELOW ? 'warn' : 'bad';
}

const clamp = (v) => Math.max(0, Math.min(100, Number(v) || 0));

/** "87.5%" (locale decimal separator), or an em dash when there is no figure. */
function pct(value, digits = 1) {
    return value == null ? '—' : `${formatNumber(value, { maximumFractionDigits: digits })}%`;
}

/**
 * A horizontal 0–100 % bar with a target tick. A null value draws an empty
 * track, never a zero-length "0 %" bar that reads as a dead machine.
 */
function TargetBar({ value, target, height, label }) {
    const t = tone(value, target);

    return (
        <div role="img" aria-label={label} className="relative rounded bg-om-track" style={{ height }}>
            {value != null && (
                <div className={`h-full rounded ${TONE_FILL[t]}`} style={{ width: `${clamp(value)}%` }} />
            )}
            <div
                aria-hidden="true"
                className="absolute w-0.5 bg-om-ink"
                style={{ left: `${clamp(target)}%`, top: -3, height: height + 6 }}
            />
        </div>
    );
}

/** The (i) button and its explanation — opens on hover and on keyboard focus. */
function OeeInfo() {
    const id = useId();

    return (
        <span className="group relative inline-flex">
            <button
                type="button"
                aria-label={__('What is OEE?')}
                aria-describedby={id}
                className="h-[22px] w-[22px] rounded-full border-[1.5px] border-om-muted bg-om-card text-om-muted
                           inline-flex items-center justify-center p-0 text-[12px] font-semibold italic leading-none cursor-help
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-om-ink"
            >
                i
            </button>
            <span
                role="tooltip"
                id={id}
                className="invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100
                           transition-opacity absolute left-1/2 top-[30px] -translate-x-1/2 z-20
                           w-[min(320px,calc(100vw-32px))] box-border flex flex-col gap-2 rounded-[10px] px-4 py-3.5
                           bg-om-ink text-om-on-ink text-[13px] font-normal leading-normal
                           shadow-[0_8px_24px_rgba(0,0,0,0.18)]"
            >
                <span className="text-[14px] font-semibold">{__('OEE — Overall Equipment Effectiveness')}</span>
                <span>{__('How much of the planned production time was truly productive: good parts, at full speed, with no stops.')}</span>
                <span className="font-mono text-[12px] text-om-accent">{__('OEE = Availability × Performance × Quality')}</span>
                <span>{__('Availability — share of planned time the line actually ran (lost to breakdowns, waiting, changeovers).')}</span>
                <span>{__('Performance — actual speed vs ideal cycle time (lost to slow cycles, micro-stops).')}</span>
                <span>{__('Quality — good parts out of all parts made (lost to scrap, rework).')}</span>
                <span className="opacity-75">{__('Live from machine data since the start of this shift — not the nightly OEE report.')}</span>
                <span className="opacity-75">
                    {__("Target: each line's planning efficiency (Lines settings); a line left at 100% uses the default 85%. The plant target weighs line targets by planned time this shift.")}
                </span>
            </span>
        </span>
    );
}

const PARTS = [
    { key: 'availability', label: () => __('Availability') },
    { key: 'performance', label: () => __('Performance') },
    { key: 'quality', label: () => __('Quality') },
];

const LOSS_WORD = {
    availability: () => __('availability'),
    performance: () => __('performance'),
    quality: () => __('quality'),
};

/**
 * "OEE · this shift": live plant OEE since shift start (overview.oee, built by
 * PlantOverviewService::shiftOee), its three components and every line
 * against its own target.
 */
export default function OeeSection({ overview }) {
    const oee = overview?.oee;
    if (!oee) return null;

    const target = oee.target;
    const lines = oee.lines ?? [];

    const header = (
        <span className="inline-flex items-center gap-1.5 text-[13px] text-om-muted" data-testid="oee-plant-target">
            <span aria-hidden="true" className="h-3 w-0.5 bg-om-ink" />
            {__('Target :pct%', { pct: formatNumber(target, { maximumFractionDigits: 1 }) })}
        </span>
    );

    let subtitle = null;
    if (oee.oee != null) {
        subtitle = oee.on_target ? (
            <span className="text-[14px] font-medium text-om-running">{__('On target')}</span>
        ) : (
            <span className="text-[14px] font-medium text-om-downtime">
                {__(':pts pts below target · biggest loss: :loss', {
                    pts: formatNumber(oee.gap_pts, { maximumFractionDigits: 1 }),
                    loss: LOSS_WORD[oee.biggest_loss]?.() ?? '—',
                })}
            </span>
        );
    } else {
        subtitle = (
            <span className="text-[14px] text-om-muted">
                {lines.length === 0 ? __('No active lines.') : __('No machine data this shift yet.')}
            </span>
        );
    }

    return (
        <OverviewCard>
            {/* Own title row rather than OverviewCard's: the (i) button sits next
                to the heading, outside the <h2> so it isn't part of its name. */}
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div className="flex items-center gap-2">
                    <h2 className="text-[18px] font-semibold text-om-ink">{__('OEE · this shift')}</h2>
                    <OeeInfo />
                </div>
                {header}
            </div>
            <div className="flex flex-wrap gap-8" data-testid="oee-section">
                <div className="flex flex-[1_1_220px] min-w-0 flex-col gap-3.5">
                    <div className="flex flex-col">
                        <span
                            className="font-mono text-[48px] font-semibold leading-[1.1] tracking-[-0.03em] text-om-ink"
                            data-testid="oee-plant-value"
                        >
                            {pct(oee.oee, 0)}
                        </span>
                        {subtitle}
                    </div>

                    {PARTS.map(({ key, label }) => {
                        const value = oee[key];
                        const t = tone(value, target);
                        return (
                            <div key={key} className="flex flex-col gap-[5px]">
                                <div className="flex justify-between text-[14px] text-om-ink">
                                    <span>{label()}</span>
                                    <span className={`font-mono font-semibold ${t ? TONE_TEXT[t] : 'text-om-muted'}`}>
                                        {pct(value)}
                                    </span>
                                </div>
                                <TargetBar
                                    value={value}
                                    target={target}
                                    height={8}
                                    label={__(':name :value, target :target', {
                                        name: label(),
                                        value: pct(value),
                                        target: pct(target),
                                    })}
                                />
                            </div>
                        );
                    })}
                </div>

                <div className="flex flex-[1_1_240px] min-w-0 flex-col gap-2.5">
                    <span className="text-[13px] text-om-muted">{__('By line')}</span>
                    {lines.length === 0 && <span className="text-[14px] text-om-muted">{__('No active lines.')}</span>}
                    {lines.map((line) => {
                        const t = tone(line.oee, line.target);
                        return (
                            <div
                                key={line.id}
                                className="grid grid-cols-[minmax(0,140px)_minmax(0,1fr)_52px] items-center gap-3"
                                data-testid="oee-line-row"
                            >
                                <span className="truncate text-[14px] font-medium text-om-ink" title={line.name}>
                                    {line.name}
                                </span>
                                <TargetBar
                                    value={line.oee}
                                    target={line.target}
                                    height={14}
                                    label={__(':name :value, target :target', {
                                        name: line.name,
                                        value: pct(line.oee),
                                        target: pct(line.target),
                                    })}
                                />
                                <span
                                    className={`text-right font-mono text-[14px] font-semibold ${t ? TONE_TEXT[t] : 'text-om-muted'}`}
                                >
                                    {pct(line.oee)}
                                </span>
                            </div>
                        );
                    })}
                </div>
            </div>
        </OverviewCard>
    );
}
