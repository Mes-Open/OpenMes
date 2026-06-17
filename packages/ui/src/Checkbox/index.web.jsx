/**
 * Checkbox — Geist White system (design ref: OpenMES Components.dc.html §03).
 *
 * 18px radius-5 box: accent fill + white check when on, 2px faintest outline
 * when off. Optional 13px ink `label` to the right. Controlled: `checked` +
 * `onChange(next)`. API is identical to the native twin (index.native.tsx).
 */
export function Checkbox({ checked, onChange, disabled = false, label, className = '', ...props }) {
    return (
        <button
            type="button"
            role="checkbox"
            aria-checked={checked}
            disabled={disabled}
            onClick={() => onChange?.(!checked)}
            className={`inline-flex items-center gap-[9px] cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 ${className}`}
            {...props}
        >
            <span
                aria-hidden
                className={`flex size-[18px] shrink-0 items-center justify-center rounded-[5px] text-[12px] font-bold transition-colors ${checked ? 'bg-om-accent text-white' : 'border-2 border-om-faintest'}`}
            >
                {checked && '✓'}
            </span>
            {label != null && <span className="text-[13px] text-om-ink">{label}</span>}
        </button>
    );
}
