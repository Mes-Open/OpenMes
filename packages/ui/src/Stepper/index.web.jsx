/**
 * Stepper — an ordered run of steps with an indicator, a title and a connector.
 *
 * Written against our own tokens rather than copied from a component kit: the
 * kits build on their own primitives (Base UI / Radix) and ship a compound API
 * — Stepper/StepperItem/StepperTrigger/StepperIndicator/StepperSeparator — which
 * is the right shape for an interactive wizard you click through. A routing is
 * not that. It is a read-only list of what happened, so it takes a list of steps
 * and draws them.
 *
 * The connector above a step is coloured by the step *before* it: the line
 * represents progress between two points, and it has been travelled only once
 * the earlier step is done.
 *
 * API is identical to the native twin (index.native.tsx).
 */
import { Icon } from '../Icon';

/**
 * done → the work happened; active → it is happening; pending → it hasn't.
 *
 * Indicators are filled, not outlined. A ring of hairline circles reads as a
 * list of bullets; a filled disc reads as a station on a line, which is the
 * whole point of drawing a routing this way.
 */
const TONE = {
    done: { dot: 'bg-om-running text-white', line: 'bg-om-running', title: 'text-om-ink' },
    active: { dot: 'bg-om-accent text-white', line: 'bg-om-line2', title: 'text-om-ink' },
    pending: { dot: 'bg-om-chip text-om-faint', line: 'bg-om-line2', title: 'text-om-muted' },
    blocked: { dot: 'bg-om-blocked text-white', line: 'bg-om-line2', title: 'text-om-ink' },
};

// The gap has to be worth more than the space it costs: too tight and the
// connector is a 10px tick nobody reads as a line joining two steps.
const SIZES = {
    sm: { dot: 24, gapY: 'pb-5', text: 'text-[12.5px]', desc: 'text-[11.5px]', icon: 13, line: 2 },
    md: { dot: 28, gapY: 'pb-6', text: 'text-[13.5px]', desc: 'text-[12px]', icon: 15, line: 2 },
};

export function Stepper({
    /** `[{ key, title, description, status, icon?, meta? }]` — status as in TONE. */
    steps = [],
    size = 'md',
    /** Right-hand column, e.g. a duration. Kept out of `description` so it can
     *  align away from the title instead of wrapping under it. */
    className = '',
    ...props
}) {
    const s = SIZES[size] ?? SIZES.md;

    return (
        <ol className={`flex flex-col ${className}`} {...props}>
            {steps.map((step, i) => {
                const tone = TONE[step.status] ?? TONE.pending;
                const isLast = i === steps.length - 1;
                // The line below this step belongs to this step's progress.
                const lineTone = TONE[step.status]?.line ?? TONE.pending.line;

                return (
                    <li key={step.key ?? i} className={`relative flex gap-3 ${isLast ? '' : s.gapY}`}>
                        {!isLast && (
                            // Positioned by style, not an arbitrary Tailwind value:
                            // the offset is derived from the indicator size, and a
                            // class built by interpolation is never generated.
                            <span
                                aria-hidden="true"
                                className={`absolute bottom-0 rounded-full ${lineTone}`}
                                style={{ left: (s.dot - s.line) / 2, top: s.dot + 2, width: s.line }}
                            />
                        )}
                        <span
                            aria-hidden="true"
                            className={`flex shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-semibold ${tone.dot}`}
                            style={{ width: s.dot, height: s.dot }}
                        >
                            {step.status === 'done' ? (
                                <Icon name={step.icon ?? 'check'} size={s.icon} />
                            ) : (
                                step.label ?? i + 1
                            )}
                        </span>
                        <div className="flex min-w-0 flex-1 items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className={`truncate font-semibold ${s.text} ${tone.title}`}>{step.title}</div>
                                {step.description && (
                                    <div className={`truncate ${s.desc} text-om-faint`}>{step.description}</div>
                                )}
                            </div>
                            {step.meta && <div className={`shrink-0 ${s.desc}`}>{step.meta}</div>}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

export default Stepper;
