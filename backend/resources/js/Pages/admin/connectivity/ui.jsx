/**
 * Shared UI primitives for the connectivity admin pages (Modbus / OPC UA / the
 * "All" overview). Extracted to avoid copy-pasting the status dot, stat card,
 * and form section/field helpers across every page.
 */

export const STATUS_DOT = {
    green:  'bg-green-500',
    yellow: 'bg-yellow-400',
    red:    'bg-red-500',
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
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <p className={`text-2xl font-bold text-gray-900 dark:text-white ${capitalize ? 'capitalize' : ''}`}>{value}</p>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{label}</p>
        </div>
    );
}

/** Card-wrapped form section with an uppercase title. */
export function Section({ title, children }) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{title}</h2>
            {children}
        </div>
    );
}

/** Labeled form field with optional required marker and error text. */
export function Field({ label, required, error, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
