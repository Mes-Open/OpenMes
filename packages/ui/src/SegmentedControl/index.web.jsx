/**
 * SegmentedControl — Geist White system (design ref: OpenMES Components.dc.html §05).
 *
 * Hairline-bordered om-bg container with 3px padding; equal-width segments,
 * active one is an ink pill (radius 6) with white text. Controlled: `value` +
 * `onChange(next)` over `options` ({ value, label }[]). API is identical to
 * the native twin (index.native.tsx).
 *
 * A radio group in a pill: one tab stop, arrows to choose, and `label` to say
 * what is being chosen — see RadioGroup for why plain buttons are not enough.
 */
import { useRovingFocus } from '../lib/rovingFocus.web.js';

export function SegmentedControl({ options, value, onChange, label, className = '', ...props }) {
    const index = options.findIndex((o) => o.value === value);
    const { containerProps, itemProps } = useRovingFocus(
        options.length,
        index < 0 ? 0 : index,
        (i) => onChange?.(options[i].value),
    );

    return (
        <div
            {...containerProps}
            role="radiogroup"
            aria-label={label}
            className={`flex gap-[3px] rounded-om-sm border border-om-line bg-om-bg p-[3px] ${className}`}
            {...props}
        >
            {options.map((option, i) => {
                const active = option.value === value;
                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        {...itemProps(i)}
                        onClick={() => onChange?.(option.value)}
                        className={`flex-1 rounded-[6px] py-[7px] text-center text-[12.5px] font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-om-accent ${active ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'}`}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
