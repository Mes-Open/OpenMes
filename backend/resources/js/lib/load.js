// Shared load-band colors for schedule load/capacity views.
// Green under 80%, amber 80–100%, red over 100%. `pct` may be null to mean
// "no capacity" (capacity view), which renders muted.

export function loadPercClass(pct) {
    if (pct === null) return 'text-om-muted';
    if (pct > 100) return 'text-om-blocked';
    if (pct > 80) return 'text-om-accent';
    return 'text-om-running';
}

export function loadBarClass(pct) {
    if (pct === null) return 'bg-om-line2';
    if (pct > 100) return 'bg-om-blocked';
    if (pct > 80) return 'bg-orange-500';
    return 'bg-om-running';
}
