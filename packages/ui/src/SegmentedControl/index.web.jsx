/**
 * SegmentedControl — Geist White system (design ref: OpenMES Components.dc.html §05).
 *
 * Hairline-bordered om-bg container with 3px padding; equal-width segments,
 * active one is an ink pill (radius 6) with white text. Controlled: `value` +
 * `onChange(next)` over `options` ({ value, label }[]). API is identical to
 * the native twin (index.native.tsx).
 */
export function SegmentedControl({ options, value, onChange, className = '', ...props }) {
    return (
        <div
            role="radiogroup"
            className={`flex gap-[3px] rounded-om-sm border border-om-line bg-om-bg p-[3px] ${className}`}
            {...props}
        >
            {options.map((option) => {
                const active = option.value === value;
                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        onClick={() => onChange?.(option.value)}
                        className={`flex-1 rounded-[6px] py-[7px] text-center text-[12.5px] font-medium transition-colors cursor-pointer ${active ? 'bg-om-ink text-white' : 'text-om-muted hover:text-om-ink'}`}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
