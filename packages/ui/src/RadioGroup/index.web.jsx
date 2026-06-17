/**
 * RadioGroup — Geist White system (design ref: OpenMES Components.dc.html §05).
 *
 * 18px circles: active is a 2px accent ring with an 8px accent dot, inactive a
 * 2px faintest ring; 13px ink labels. Lays out horizontally by default (design)
 * — set `horizontal={false}` to stack. Controlled: `value` + `onChange(next)`
 * over `options` ({ value, label }[]). API is identical to the native twin
 * (index.native.tsx).
 */
export function RadioGroup({ options, value, onChange, horizontal = true, className = '', ...props }) {
    return (
        <div
            role="radiogroup"
            className={`flex ${horizontal ? 'flex-row items-center gap-[18px]' : 'flex-col items-start gap-[13px]'} ${className}`}
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
                        className="inline-flex items-center gap-[9px] cursor-pointer"
                    >
                        <span
                            aria-hidden
                            className={`flex size-[18px] shrink-0 items-center justify-center rounded-full border-2 ${active ? 'border-om-accent' : 'border-om-faintest'}`}
                        >
                            {active && <span className="size-2 rounded-full bg-om-accent" />}
                        </span>
                        <span className="text-[13px] text-om-ink">{option.label}</span>
                    </button>
                );
            })}
        </div>
    );
}
