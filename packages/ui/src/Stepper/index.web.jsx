/**
 * Stepper — an ordered run of steps with an indicator, a title and a connector.
 *
 * Written against our own tokens rather than copied from a component kit: the
 * kits build on their own primitives (Base UI / Radix) and ship a compound API
 * — Stepper/StepperItem/StepperTrigger/StepperIndicator/StepperSeparator — which
 * is the right shape for an interactive wizard you click through. A routing is
 * mostly not that: it is a record of what happened, so this takes a list of
 * steps and draws them. Where a step *can* be acted on, it carries its own
 * control in `action` rather than the whole component becoming a wizard.
 *
 * The connector above a step is coloured by the step *before* it: the line
 * represents progress between two points, and it has been travelled only once
 * the earlier step is done.
 *
 * The indicator keeps the step's **number** in every state, including done.
 * "Step 4 of 6" is the vocabulary a routing is discussed in on the floor, and a
 * column of ticks loses exactly the thing that lets someone say which step they
 * mean. Done-ness is carried by the fill and by the connector below it. Pass
 * `icon` on a step to put a glyph there instead.
 *
 * API is identical to the native twin (index.native.tsx).
 */
import { Icon } from '../Icon';

/**
 * done → the work happened; active → it is happening; pending → it hasn't.
 *
 * Indicators are filled, not outlined. A ring of hairline circles reads as a
 * list of bullets; a filled disc reads as a station on a line, which is the
 * whole point of drawing a routing this way. Pending is the exception: it is
 * the state of *not yet*, so it is the one that stays hollow.
 */
const TONE = {
    done: { dot: 'bg-om-running text-white', line: 'bg-om-running', title: 'text-om-ink', caption: 'text-om-faint' },
    active: { dot: 'bg-om-accent text-white', line: 'bg-om-line2', title: 'text-om-ink', caption: 'text-om-accent' },
    pending: { dot: 'bg-om-chip text-om-faint border border-om-line', line: 'bg-om-line2', title: 'text-om-muted', caption: 'text-om-muted' },
    blocked: { dot: 'bg-om-blocked text-white', line: 'bg-om-line2', title: 'text-om-ink', caption: 'text-om-blocked' },
};

// The gap has to be worth more than the space it costs: too tight and the
// connector is a 10px tick nobody reads as a line joining two steps.
const SIZES = {
    sm: { dot: 24, gapY: 'pb-5', text: 'text-[12.5px]', desc: 'text-[9.5px]', icon: 13, line: 2 },
    md: { dot: 28, gapY: 'pb-6', text: 'text-[13.5px]', desc: 'text-[10px]', icon: 15, line: 2 },
};

export function Stepper({
    /** `[{ key, title, description, status, icon?, label?, meta?, action? }]`. */
    steps = [],
    size = 'md',
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
                            {step.icon ? <Icon name={step.icon} size={s.icon} /> : (step.label ?? i + 1)}
                        </span>
                        <div className="flex min-w-0 flex-1 items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className={`truncate font-semibold ${s.text} ${tone.title}`}>{step.title}</div>
                                {/* The step's state, in the caption's own voice: mono
                                    and letterspaced like every other status label in
                                    the system, coloured by the state it names. */}
                                {step.description && (
                                    <div className={`truncate font-mono tracking-[0.06em] ${s.desc} ${tone.caption}`}>
                                        {step.description}
                                    </div>
                                )}
                            </div>
                            {/* `meta` is a fact about the step (a duration); `action`
                                is something you can do to it. Kept apart so a row
                                with both doesn't have to choose. */}
                            {step.meta && <div className={`shrink-0 font-mono ${s.desc} pt-[2px]`}>{step.meta}</div>}
                            {step.action && <div className="flex shrink-0 items-center gap-1.5">{step.action}</div>}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

export default Stepper;
