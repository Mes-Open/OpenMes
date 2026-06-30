/**
 * StatusPill — Geist White system (design ref: OpenMES Components.dc.html §06).
 *
 * Mono 9.5px letterspaced uppercase pill, fg/bg from the statusColors token.
 * `pulse` (default true when running) prepends a 6px animated dot.
 * API is identical to the native twin (index.native.tsx).
 */
const statusClasses = {
    running: 'text-om-running bg-om-running-bg',
    pending: 'text-om-pending bg-om-pending-bg',
    blocked: 'text-om-blocked bg-om-blocked-bg',
    downtime: 'text-om-downtime bg-om-downtime-bg',
    done: 'text-om-done bg-om-done-bg',
};

export function StatusPill({ status, label, pulse = status === 'running', className = '', ...props }) {
    return (
        <span
            className={`inline-flex items-center gap-[5px] font-mono text-[9.5px] uppercase tracking-[0.06em] rounded-[20px] px-[10px] py-1 ${statusClasses[status]} ${className}`}
            {...props}
        >
            {pulse && <span aria-hidden className="size-[6px] rounded-full bg-current animate-om-pulse" />}
            {label}
        </span>
    );
}
