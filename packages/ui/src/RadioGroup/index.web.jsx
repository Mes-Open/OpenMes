/**
 * RadioGroup — Geist White system (design ref: OpenMES Components.dc.html §05).
 *
 * 18px circles: active is a 2px accent ring with an 8px accent dot, inactive a
 * 2px faintest ring; 13px ink labels. Lays out horizontally by default (design)
 * — set `horizontal={false}` to stack. Controlled: `value` + `onChange(next)`
 * over `options` ({ value, label }[]). API is identical to the native twin
 * (index.native.tsx).
 *
 * The group is one tab stop with the arrows choosing inside it, per the radio
 * pattern — not one stop per option, which is what plain buttons give you and
 * what tells a reader nothing about the options belonging together. `label`
 * names the group; without it a screen reader announces the options with no
 * idea what they are answering.
 */
import { useRovingFocus } from '../lib/rovingFocus.web.js';

export function RadioGroup({ options, value, onChange, horizontal = true, label, className = '', ...props }) {
    const index = options.findIndex((o) => o.value === value);
    const { containerProps, itemProps } = useRovingFocus(
        options.length,
        // Nothing selected yet: the first option holds the tab stop so the group
        // is reachable at all, without claiming to be checked.
        index < 0 ? 0 : index,
        (i) => onChange?.(options[i].value),
    );

    return (
        <div
            {...containerProps}
            role="radiogroup"
            aria-label={label}
            className={`flex ${horizontal ? 'flex-row items-center gap-[18px]' : 'flex-col items-start gap-[13px]'} ${className}`}
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
                        className="inline-flex items-center gap-[9px] cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-om-accent"
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
