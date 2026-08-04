/**
 * SegmentedProgress — a discrete meter drawn as a row of thin bars.
 *
 * The fill is a *state ramp*: every filled bar carries the same colour, and
 * that colour is the status — warm while the work is young, amber past the
 * midpoint, green at the finish. The alternative (a positional ramp, where
 * each slot owns a fixed colour) reads as a rainbow and makes the hue
 * meaningless: slot 7 would be green while you are only a quarter done.
 *
 * Colours come from the design tokens rather than fixed hex, so the ramp
 * follows the theme — a hard-coded palette would keep light-mode values on a
 * dark background. The thresholds match the OEE page's existing ramp.
 *
 * API is identical to the native twin (index.native.tsx).
 */

/** Ramp stops, highest first — the first stop the ratio reaches wins. */
const RAMP = [
    { from: 0.85, fill: 'bg-om-running' },
    { from: 0.45, fill: 'bg-om-downtime' },
    { from: 0, fill: 'bg-om-accent' },
];

const EMPTY_FILL = 'bg-om-faintest';

/**
 * How much of the bar the value has landed inside is lit, with one exception:
 * work that has started but not yet earned a visible sliver still gets one.
 * 3 produced of 5000 is a hair over 0% and would render as an untouched meter,
 * which is a different fact — "nobody has begun" is what the empty state means.
 */
const MIN_VISIBLE_FRACTION = 0.25;

function partialFraction(value, fraction, fullBars) {
    if (fraction <= 0) return 0;
    if (fullBars === 0 && value > 0) return Math.max(fraction, MIN_VISIBLE_FRACTION);
    return fraction;
}

export function SegmentedProgress({
    /** Current amount, in the same unit as `max`. */
    value = 0,
    /** Value that counts as complete. */
    max = 8,
    /** How many bars to draw — independent of `max`, so percentages work too. */
    segments = 8,
    /** Render a compact "6/8" after the bars. Worth it when green means "done":
     *  roughly 8% of men can't separate it from the amber stop by hue alone. */
    showValue = false,
    /** Accessible name — a bare meter announces a number with no subject. */
    label,
    className = '',
    ...props
}) {
    const ratio = max > 0 ? Math.min(1, Math.max(0, value / max)) : 0;
    // Eight bars can only land on eight values, so a progress of 1/3 (33%) would
    // round to 2 of 8 (25%) and stay there until 37%. The bar the value lands
    // *inside* is filled by the remainder instead, which is what lets a meter of
    // eight segments read a value that isn't a multiple of an eighth.
    const exact = ratio * segments;
    const full = Math.floor(exact);
    const partial = partialFraction(value, exact - full, full);
    const { fill } = RAMP.find((stop) => ratio >= stop.from) ?? RAMP[RAMP.length - 1];

    return (
        <span className={`inline-flex items-center gap-2 ${className}`}>
            <span
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={max}
                aria-valuenow={value}
                aria-label={label}
                className="inline-flex items-center gap-[3px]"
                {...props}
            >
                {Array.from({ length: segments }, (_, i) => {
                    // The partial bar fills from the bottom, so a row of them reads
                    // as one meter rising left to right rather than eight gauges.
                    const isPartial = i === full && partial > 0;

                    return (
                        <span
                            key={i}
                            aria-hidden="true"
                            className={`relative h-4 w-[3px] overflow-hidden rounded-full transition-colors duration-200 ${
                                i < full ? fill : EMPTY_FILL
                            }`}
                        >
                            {isPartial && (
                                <span
                                    className={`absolute inset-x-0 bottom-0 rounded-full ${fill}`}
                                    style={{ height: `${partial * 100}%` }}
                                />
                            )}
                        </span>
                    );
                })}
            </span>
            {showValue && (
                <span className="font-mono text-[11px] tabular-nums text-om-muted">
                    {value}/{max}
                </span>
            )}
        </span>
    );
}
