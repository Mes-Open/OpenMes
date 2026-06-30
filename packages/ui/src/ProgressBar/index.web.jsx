/**
 * ProgressBar — Geist White system (design ref: OpenMES Components.dc.html §06).
 *
 * 7px track, radius 20, chip background, accent fill (overridable via `color`).
 * API is identical to the native twin (index.native.tsx).
 */
export function ProgressBar({ value, color, className = '', ...props }) {
    const pct = Math.min(100, Math.max(0, value));
    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={pct}
            className={`h-[7px] rounded-[20px] bg-om-chip overflow-hidden ${className}`}
            {...props}
        >
            <div
                className="h-full rounded-[20px] bg-om-accent"
                style={{ width: `${pct}%`, ...(color ? { backgroundColor: color } : null) }}
            />
        </div>
    );
}
