/**
 * InlineAlert — Geist White system (design ref: OpenMES Components.dc.html §08).
 *
 * Tinted alert row: 9px severity dot, 13px semibold title, 11.5px muted body.
 * Backgrounds per severity (success=runningBg, info=chip + accent dot,
 * warning=downtimeBg, error=blockedBg). API is identical to the native twin.
 */
const severities = {
    success: { bg: 'bg-om-running-bg', dot: 'bg-om-running' },
    info: { bg: 'bg-om-chip', dot: 'bg-om-accent' },
    warning: { bg: 'bg-om-downtime-bg', dot: 'bg-om-downtime' },
    error: { bg: 'bg-om-blocked-bg', dot: 'bg-om-blocked' },
};

export function InlineAlert({ severity, title, children, className = '', ...props }) {
    const s = severities[severity];
    return (
        <div
            role={severity === 'error' ? 'alert' : 'status'}
            className={`flex items-start gap-[11px] rounded-om px-[15px] py-[14px] ${s.bg} ${className}`}
            {...props}
        >
            <span aria-hidden className={`mt-[3px] size-[9px] shrink-0 rounded-full ${s.dot}`} />
            <div className="flex-1 min-w-0">
                <div className="text-[13px] font-semibold text-om-ink">{title}</div>
                {children != null && <div className="mt-[3px] text-[11.5px] leading-[1.45] text-om-muted">{children}</div>}
            </div>
        </div>
    );
}
