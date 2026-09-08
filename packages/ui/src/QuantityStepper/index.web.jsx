/**
 * QuantityStepper — Geist White system (design ref: OpenMES Components.dc.html §04).
 *
 * Compact form-field stepper: hairline-bordered row on om-bg with −/+ buttons
 * flanking a centered mono value (design shows it 128px wide — size via
 * className/parent). Controlled: `value` + `onChange(next)`, clamped to
 * `min`/`max`. API is identical to the native twin (index.native.tsx).
 *
 * The number in the middle is the control, not decoration: it carries
 * `role="spinbutton"` with the current value and its bounds, takes focus, and
 * answers the arrows, Home/End and PageUp/PageDown. Before that it was an inert
 * `<span>` between two unlabelled glyph buttons — a screen reader could press
 * "−" and "+" without ever being told what they did or what the value became.
 * `label` names it; pass one wherever the surrounding text does not already.
 */
export function QuantityStepper({
    value,
    onChange,
    min,
    max,
    step = 1,
    label,
    decrementLabel = 'Decrease',
    incrementLabel = 'Increase',
    className = '',
    ...props
}) {
    const atMin = min != null && value <= min;
    const atMax = max != null && value >= max;
    const clamp = (n) => Math.min(max ?? Infinity, Math.max(min ?? -Infinity, n));
    const set = (n) => onChange?.(clamp(n));

    const onKeyDown = (e) => {
        const jump = step * 10;
        let next;
        switch (e.key) {
            case 'ArrowUp': next = value + step; break;
            case 'ArrowDown': next = value - step; break;
            case 'PageUp': next = value + jump; break;
            case 'PageDown': next = value - jump; break;
            case 'Home': if (min == null) return; next = min; break;
            case 'End': if (max == null) return; next = max; break;
            default: return;
        }
        e.preventDefault();
        set(next);
    };

    const btn =
        'px-3 py-[9px] text-[16px] leading-none text-om-muted cursor-pointer transition-colors hover:text-om-ink disabled:text-om-faintest disabled:cursor-not-allowed disabled:hover:text-om-faintest';

    return (
        <div
            className={`flex items-center overflow-hidden rounded-om-sm border border-om-line bg-om-bg ${className}`}
            {...props}
        >
            {/* The glyphs are shapes, not text a screen reader can read out. */}
            <button type="button" aria-label={decrementLabel} disabled={atMin} onClick={() => set(value - step)} className={btn}>
                <span aria-hidden>−</span>
            </button>
            <span
                role="spinbutton"
                tabIndex={0}
                aria-label={label}
                aria-valuenow={value}
                aria-valuemin={min}
                aria-valuemax={max}
                onKeyDown={onKeyDown}
                className="flex-1 text-center font-mono text-[13px] text-om-ink focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-om-accent"
            >
                {value}
            </span>
            <button type="button" aria-label={incrementLabel} disabled={atMax} onClick={() => set(value + step)} className={btn}>
                <span aria-hidden>+</span>
            </button>
        </div>
    );
}
