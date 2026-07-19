/**
 * TextField — Geist White system (design ref: OpenMES Components.dc.html §04).
 *
 * Optional mono-uppercase label above (the system's metadata idiom; `required`
 * adds a red asterisk, `labelHint` a right-aligned mono note). Field is 13px ink
 * on om-bg with a hairline border; focus switches to a 1.5px accent border + 3px
 * focus ring on a card background. `mono` sets Geist Mono for code-like values,
 * `multiline` renders a textarea, `error` shows a blocked-red border + message,
 * `hint` a muted note, `suffix` a trailing affordance inside the field.
 * Controlled: `value` + `onChange(text)`. `className` lands on the root; extra
 * `...props` land on the field element. API matches the native twin.
 */
export function TextField({
    label,
    value,
    onChange,
    placeholder,
    mono = false,
    multiline = false,
    error,
    hint,
    labelHint,
    required = false,
    suffix = null,
    className = '',
    ...props
}) {
    const field = [
        'w-full text-[13px] text-om-ink placeholder:text-om-faint bg-om-bg rounded-om-sm border px-3 py-2.5 outline-none transition-colors',
        'focus-visible:border-[1.5px] focus-visible:border-om-accent focus-visible:bg-om-card focus-visible:px-[11.5px] focus-visible:py-[9.5px] focus-visible:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]',
        mono ? 'font-mono' : '',
        suffix ? 'pr-10' : '',
        error ? 'border-om-blocked' : 'border-om-line',
    ].join(' ');

    const fieldProps = {
        value,
        placeholder,
        onChange: (e) => onChange?.(e.target.value),
        className: field,
        ...props,
    };

    const control = multiline ? (
        <textarea rows={3} {...fieldProps} className={`${field} resize-none`} />
    ) : (
        <input type="text" {...fieldProps} />
    );

    return (
        <div className={className}>
            {label != null && (
                <div className="mb-[7px] flex items-baseline justify-between gap-2">
                    <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                        {label}
                        {required && <span className="text-om-blocked"> *</span>}
                    </span>
                    {labelHint && (
                        <span className="font-mono text-[9.5px] tracking-[0.04em] text-om-faint">{labelHint}</span>
                    )}
                </div>
            )}
            {suffix ? (
                <div className="relative flex items-center">
                    {control}
                    <div className="absolute right-3 flex items-center text-om-faint">{suffix}</div>
                </div>
            ) : (
                control
            )}
            {error ? (
                <div className="mt-[5px] text-[11.5px] text-om-blocked">{error}</div>
            ) : hint ? (
                <div className="mt-[5px] text-[10.5px] text-om-faint">{hint}</div>
            ) : null}
        </div>
    );
}
