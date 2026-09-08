/**
 * Shared vocabulary for rendering a workstation's machine state — the colour
 * classes keyed by MachineMonitorService::stateColor() and the "time in state"
 * formatter. Used by the Machine Monitor tiles and the Line Flow canvas so a
 * RUNNING station looks the same on both.
 */

export const STATE_BORDER = {
    green: 'border-om-running',
    amber: 'border-amber-400',
    blue:  'border-om-accent',
    gray:  'border-om-faintest',
    red:   'border-om-blocked',
    yellow: 'border-yellow-400',
    purple: 'border-purple-400',
    orange: 'border-orange-400',
    slate: 'border-slate-300',
};

export const STATE_BADGE = {
    green: 'bg-om-running-bg text-om-running',
    amber: 'bg-om-downtime-bg text-om-downtime',
    blue:  'bg-om-chip text-om-accent',
    gray:  'bg-om-chip text-om-muted',
    red:   'bg-om-blocked-bg text-om-blocked',
    yellow: 'bg-yellow-100 text-yellow-700',
    purple: 'bg-purple-100 text-purple-700',
    orange: 'bg-orange-100 text-orange-700',
    slate: 'bg-slate-100 text-slate-600',
};

// Translatable labels for the manual state picker (#87).
export const STATE_LABELS = {
    RUNNING: 'Running', IDLE: 'Idle', STOPPED: 'Stopped', FAULT: 'Fault', SETUP: 'Setup',
    WAITING: 'Waiting', CLEANING: 'Cleaning', MAINTENANCE: 'Maintenance',
};

export function csrf() {
    const m = typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]') : null;
    return m ? m.content : '';
}

export function timeInState(sinceIso, now) {
    if (!sinceIso) return null;
    const start = new Date(sinceIso).getTime();
    if (Number.isNaN(start)) return null;
    const sec = Math.max(0, Math.floor((now - start) / 1000));
    if (sec < 60) return `${sec}s`;
    if (sec < 3600) return `${Math.floor(sec / 60)}m`;
    return `${Math.floor(sec / 3600)}h ${Math.floor((sec % 3600) / 60)}m`;
}

/** POST a manual state override; resolves to the JSON body or null on failure. */
export async function postWorkstationState(workstationId, state) {
    try {
        const res = await fetch(`/admin/machine-monitor/${workstationId}/state`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ state }),
        });
        return res.ok ? await res.json() : null;
    } catch (_) {
        return null;
    }
}
