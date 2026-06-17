/**
 * Button — Geist White system (design ref: OpenMES Components.dc.html §02).
 *
 * Variants: primary (ink), accent (orange), secondary (chip), ghost (hairline
 * outline), danger (soft red). `loading` swaps in a spinner and mutes the label.
 * API is identical to the native twin (index.native.tsx).
 */
export function Button({
    variant = 'primary',
    disabled = false,
    loading = false,
    type = 'button',
    className = '',
    children,
    ...props
}) {
    const base =
        'inline-flex items-center justify-center gap-2 text-[13px] font-semibold rounded-om-sm transition-colors cursor-pointer disabled:cursor-not-allowed';
    const variants = {
        primary: 'text-om-on-ink bg-om-ink hover:bg-om-ink-hover px-4 py-2.5',
        accent: 'text-white bg-om-accent hover:brightness-95 px-4 py-2.5',
        secondary: 'text-om-ink bg-om-chip hover:bg-om-line2 px-4 py-2.5',
        ghost: 'text-om-ink bg-transparent border border-om-line hover:bg-om-chip px-4 py-[9px]',
        danger: 'text-om-blocked bg-om-blocked-bg hover:bg-[#f8ddd6] px-4 py-2.5',
    };
    const disabledCls = 'text-om-faint bg-om-chip opacity-60 hover:bg-om-chip hover:brightness-100';

    return (
        <button
            type={type}
            disabled={disabled || loading}
            className={`${base} ${disabled || loading ? `${variants[variant]} ${disabledCls}` : variants[variant]} ${className}`}
            {...props}
        >
            {loading && (
                <span
                    aria-hidden
                    className="inline-block size-[11px] rounded-full border-2 border-om-faintest border-t-om-accent animate-spin"
                />
            )}
            {children}
        </button>
    );
}

/** Square 38px icon button. `variant`: 'primary' (ink) | 'danger' (soft red) | 'default' (chip). */
export function IconButton({ variant = 'default', className = '', children, ...props }) {
    const variants = {
        primary: 'text-om-on-ink bg-om-ink hover:bg-om-ink-hover',
        danger: 'text-om-blocked bg-om-blocked-bg hover:bg-[#f8ddd6]',
        default: 'text-om-ink bg-om-chip hover:bg-om-line2',
    };
    return (
        <button
            type="button"
            className={`inline-flex size-[38px] items-center justify-center rounded-om-sm text-[17px] font-semibold transition-colors cursor-pointer ${variants[variant]} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}
