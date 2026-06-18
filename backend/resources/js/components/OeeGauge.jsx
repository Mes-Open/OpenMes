// Semicircle OEE gauge with red/yellow/green zones and a needle — React port of
// the `<x-oee-gauge>` Blade component. Points lie on a unit semicircle centered
// at (50,50), r=40: p=0 → (10,50), p=50 → (50,10), p=100 → (90,50).
//
// Shared by the dashboard OEE Overview widget and the full OEE report so both
// render the same banded gauge (the report previously had a one-colour CSS arc).

// OEE banding — mirrors backend/app/Support/OeeBand.php (red < 65 ≤ yellow < 85 ≤ green).
export const OEE_RED_BELOW = 65;
export const OEE_GREEN_AT_LEAST = 85;

export function oeeColor(value) {
    if (value == null) return 'gray';
    if (value >= OEE_GREEN_AT_LEAST) return 'green';
    if (value >= OEE_RED_BELOW) return 'yellow';
    return 'red';
}

export function oeeTextClass(value) {
    return {
        green: 'text-om-running',
        yellow: 'text-om-downtime',
        red: 'text-om-blocked',
        gray: 'text-om-faint',
    }[oeeColor(value)];
}

export default function OeeGauge({ value, size = 104 }) {
    const hasValue = value != null;
    const p = hasValue ? Math.max(0, Math.min(100, Number(value))) : 0;
    const pointAt = (q, r = 40) => {
        const a = (q / 100) * Math.PI;
        return [50 - r * Math.cos(a), 50 - r * Math.sin(a)];
    };
    const [rEndX, rEndY] = pointAt(OEE_RED_BELOW);
    const [yEndX, yEndY] = pointAt(OEE_GREEN_AT_LEAST);
    const [gEndX, gEndY] = pointAt(100);
    const [needleX, needleY] = pointAt(p, 35);

    return (
        <div className="inline-flex flex-col items-center" style={{ width: size }}>
            <svg viewBox="0 0 100 60" className="w-full h-auto" aria-hidden="true">
                <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="currentColor" strokeWidth="10" className="text-om-line2" />
                <path d={`M 10 50 A 40 40 0 0 1 ${rEndX} ${rEndY}`} fill="none" stroke="#D6442F" strokeWidth="10" />
                <path d={`M ${rEndX} ${rEndY} A 40 40 0 0 1 ${yEndX} ${yEndY}`} fill="none" stroke="#C9821E" strokeWidth="10" />
                <path d={`M ${yEndX} ${yEndY} A 40 40 0 0 1 ${gEndX} ${gEndY}`} fill="none" stroke="#1C9A55" strokeWidth="10" />
                {hasValue ? (
                    <>
                        <line x1="50" y1="50" x2={needleX} y2={needleY} stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" className="text-om-ink" />
                        <circle cx="50" cy="50" r="2.2" fill="currentColor" className="text-om-ink" />
                    </>
                ) : (
                    <circle cx="50" cy="50" r="2.2" fill="currentColor" className="text-om-faintest" />
                )}
            </svg>
            <div className="-mt-2 text-center leading-tight">
                <div className={`font-mono font-medium ${oeeTextClass(hasValue ? p : null)}`} style={{ fontSize: size * 0.18 }}>
                    {hasValue ? `${p.toFixed(1)}%` : 'N/A'}
                </div>
                <div className="font-mono text-om-faint uppercase tracking-[0.08em]" style={{ fontSize: size * 0.085 }}>
                    OEE
                </div>
            </div>
        </div>
    );
}
