/**
 * Shared UI primitives for the connectivity admin pages (Modbus / OPC UA / the
 * "All" overview). Extracted to avoid copy-pasting the status dot, stat card,
 * and form section/field helpers across every page.
 */

export const STATUS_DOT = {
    green:  'bg-om-running',
    yellow: 'bg-om-downtime',
    red:    'bg-om-blocked',
    slate:  'bg-slate-400',
};

/** Colored connection-status dot. `size` is a Tailwind w/h pair. */
export function StatusDot({ color, pulse = false, size = 'w-2.5 h-2.5' }) {
    const cls = STATUS_DOT[color] ?? 'bg-slate-400';
    return <span className={`${size} rounded-full shrink-0 ${cls} ${pulse ? 'animate-pulse' : ''}`} />;
}

/** Centered metric card used on the connection Show pages. */
export function StatCard({ value, label, capitalize = false }) {
    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-4 text-center">
            <p className={`text-2xl font-bold text-om-ink ${capitalize ? 'capitalize' : ''}`}>{value}</p>
            <p className="text-xs text-om-muted mt-1">{label}</p>
        </div>
    );
}

/** Card-wrapped form section with an uppercase title. */
export function Section({ title, children }) {
    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-5 space-y-4">
            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wider">{title}</h2>
            {children}
        </div>
    );
}

/** Labeled form field with optional required marker and error text. */
export function Field({ label, required, error, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">
                {label} {required && <span className="text-om-blocked">*</span>}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
