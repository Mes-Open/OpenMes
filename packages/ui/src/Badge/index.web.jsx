/**
 * Badge — Geist White system (design ref: OpenMES Components.dc.html §06).
 *
 * Mono 11px pill counters/labels: 'danger' (white on blocked), 'neutral'
 * (muted on chip), 'outline' (accent text + 1px accent border, e.g. HIGH).
 * API is identical to the native twin (index.native.tsx).
 */
const variants = {
    danger: 'text-white bg-om-blocked',
    neutral: 'text-om-muted bg-om-chip',
    outline: 'text-om-accent border border-om-accent',
};

export function Badge({ variant = 'neutral', className = '', children, ...props }) {
    return (
        <span
            className={`inline-flex items-center font-mono text-[11px] rounded-[20px] px-[9px] py-[2px] ${variants[variant]} ${className}`}
            {...props}
        >
            {children}
        </span>
    );
}
