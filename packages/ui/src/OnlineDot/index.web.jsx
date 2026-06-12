/**
 * OnlineDot — Geist White system (design ref: OpenMES Components.dc.html §06).
 *
 * 7px running-color dot + mono 10px running-color label (e.g. ONLINE),
 * with optional pulse. API is identical to the native twin (index.native.tsx).
 */
export function OnlineDot({ label, pulse = false, className = '', ...props }) {
    return (
        <span className={`inline-flex items-center gap-[5px] font-mono text-[10px] text-om-running ${className}`} {...props}>
            <span aria-hidden className={`size-[7px] rounded-full bg-om-running ${pulse ? 'animate-om-pulse' : ''}`} />
            {label}
        </span>
    );
}
