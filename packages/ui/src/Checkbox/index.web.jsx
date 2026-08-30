/**
 * Checkbox — Geist White system (design ref: OpenMES Components.dc.html §03).
 *
 * 18px radius-5 box: accent fill + white check when on, 2px faintest outline
 * when off. Optional 13px ink `label` to the right. Controlled: `checked` +
 * `onChange(next)`. API is identical to the native twin (index.native.tsx).
 *
 * `indeterminate` renders the accent fill with a – instead of a ✓ (a "some but
 * not all" parent, e.g. DataTable's select-all header box) and reports
 * aria-checked="mixed". `size="sm"` is the 17px box the data table uses.
 */
const BOX_SIZE = { md: 'size-[18px]', sm: 'size-[17px]' };

export function Checkbox({
    checked,
    indeterminate = false,
    onChange,
    disabled = false,
    label,
    size = 'md',
    className = '',
    ...props
}) {
    const filled = checked || indeterminate;
    return (
        <button
            type="button"
            role="checkbox"
            aria-checked={indeterminate ? 'mixed' : checked}
            disabled={disabled}
            onClick={() => onChange?.(!checked)}
            className={`inline-flex items-center gap-[9px] cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 ${className}`}
            {...props}
        >
            <span
                aria-hidden
                className={`flex ${BOX_SIZE[size]} shrink-0 items-center justify-center rounded-[5px] text-[12px] font-bold transition-colors ${filled ? 'bg-om-accent text-white' : 'border-2 border-om-faintest'}`}
            >
                {filled && (indeterminate ? '–' : '✓')}
            </span>
            {label != null && <span className="text-[13px] text-om-ink">{label}</span>}
        </button>
    );
}
