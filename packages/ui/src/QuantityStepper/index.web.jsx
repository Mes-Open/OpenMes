/**
 * QuantityStepper — Geist White system (design ref: OpenMES Components.dc.html §04).
 *
 * Compact form-field stepper: hairline-bordered row on om-bg with −/+ buttons
 * flanking a centered mono value (design shows it 128px wide — size via
 * className/parent). Controlled: `value` + `onChange(next)`, clamped to
 * `min`/`max`. API is identical to the native twin (index.native.tsx).
 */
export function QuantityStepper({ value, onChange, min, max, step = 1, className = '', ...props }) {
    const atMin = min != null && value <= min;
    const atMax = max != null && value >= max;
    const clamp = (n) => Math.min(max ?? Infinity, Math.max(min ?? -Infinity, n));

    const btn =
        'px-3 py-[9px] text-[16px] leading-none text-om-muted cursor-pointer transition-colors hover:text-om-ink disabled:text-om-faintest disabled:cursor-not-allowed disabled:hover:text-om-faintest';

    return (
        <div
            className={`flex items-center overflow-hidden rounded-om-sm border border-om-line bg-om-bg ${className}`}
            {...props}
        >
            <button type="button" disabled={atMin} onClick={() => onChange?.(clamp(value - step))} className={btn}>
                −
            </button>
            <span className="flex-1 text-center font-mono text-[13px] text-om-ink">{value}</span>
            <button type="button" disabled={atMax} onClick={() => onChange?.(clamp(value + step))} className={btn}>
                +
            </button>
        </div>
    );
}
