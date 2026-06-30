/**
 * TextField — Geist White system (design ref: OpenMES Components.dc.html §04).
 *
 * Optional mono-uppercase label above (the system's metadata idiom). Field is
 * 13px ink on om-bg with a hairline border; focus switches to a 1.5px accent
 * border + 3px focus ring on a card background. `mono` sets Geist Mono for
 * code-like values (LOT numbers), `multiline` renders a textarea, `error`
 * shows a blocked-red border + message. Controlled: `value` + `onChange(text)`.
 * `className` lands on the root; extra `...props` land on the field element.
 * API is identical to the native twin (index.native.tsx).
 */
export function TextField({
    label,
    value,
    onChange,
    placeholder,
    mono = false,
    multiline = false,
    error,
    className = '',
    ...props
}) {
    const field = [
        'w-full text-[13px] text-om-ink placeholder:text-om-faint bg-om-bg rounded-om-sm border px-3 py-2.5 outline-none transition-colors',
        'focus-visible:border-[1.5px] focus-visible:border-om-accent focus-visible:bg-om-card focus-visible:px-[11.5px] focus-visible:py-[9.5px] focus-visible:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]',
        mono ? 'font-mono' : '',
        error ? 'border-om-blocked' : 'border-om-line',
    ].join(' ');

    const fieldProps = {
        value,
        placeholder,
        onChange: (e) => onChange?.(e.target.value),
        className: field,
        ...props,
    };

    return (
        <div className={className}>
            {label != null && (
                <div className="mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                    {label}
                </div>
            )}
            {multiline ? <textarea rows={3} {...fieldProps} className={`${field} resize-none`} /> : <input type="text" {...fieldProps} />}
            {error && <div className="mt-[5px] text-[11.5px] text-om-blocked">{error}</div>}
        </div>
    );
}
