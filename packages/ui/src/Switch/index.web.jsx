/**
 * Switch — Geist White system (design ref: OpenMES Components.dc.html §03).
 *
 * 42×24 pill track (accent on / faintest off) with an 18px white thumb that
 * slides between the 3px insets. Controlled: `checked` + `onChange(next)`.
 * API is identical to the native twin (index.native.tsx).
 */
export function Switch({ checked, onChange, disabled = false, className = '', ...props }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            disabled={disabled}
            onClick={() => onChange?.(!checked)}
            className={`relative h-6 w-[42px] shrink-0 rounded-full transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 ${checked ? 'bg-om-accent' : 'bg-om-faintest'} ${className}`}
            {...props}
        >
            <span
                aria-hidden
                className={`absolute top-[3px] left-[3px] size-[18px] rounded-full bg-white shadow-[0_1px_3px_rgba(0,0,0,0.25)] transition-transform ${checked ? 'translate-x-[18px]' : 'translate-x-0'}`}
            />
        </button>
    );
}
