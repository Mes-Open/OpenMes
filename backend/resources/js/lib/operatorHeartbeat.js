import { useEffect } from 'react';
import { apiCall } from './http';

/**
 * Operator panel presence for Admin -> Workstation devices.
 *
 * While an operator panel page is open, the browser posts a heartbeat to
 * /operator/heartbeat: "active" every HEARTBEAT_INTERVAL_MS while the page is
 * visible, "inactive" when it is hidden or closed, with the selected line and
 * workstation. The server records the
 * logged-in user and the request IP; a panel silent for longer than the
 * online window (60 s) is shown as inactive anyway.
 */
export const HEARTBEAT_INTERVAL_MS = 20000;
// An Inertia visit remounts the layout; don't re-send right after a heartbeat.
const MIN_GAP_MS = 5000;
const STORAGE_KEY = 'openmes.operatorPanelDeviceId';

let lastSentAt = 0;

/** Random v4-style uuid. crypto.randomUUID needs a secure context, which a LAN http:// panel is not. */
export function randomId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/** Stable id of this browser, kept in localStorage so a reload keeps the same roster row. */
export function deviceId() {
    try {
        let id = window.localStorage.getItem(STORAGE_KEY);
        if (!id) {
            id = randomId();
            window.localStorage.setItem(STORAGE_KEY, id);
        }
        return id;
    } catch {
        // Storage blocked (private mode): fall back to one id per page load.
        window.__openmesPanelDeviceId ??= randomId();
        return window.__openmesPanelDeviceId;
    }
}

/**
 * workstationId: a bench id, null for "all workstations", or undefined when
 * the page doesn't say (then the server keeps the last one it had).
 */
function send(status, lineId, workstationId, { keepalive = false } = {}) {
    lastSentAt = Date.now();
    const body = { device_uuid: deviceId(), status, line_id: lineId ?? null };
    if (workstationId !== undefined) body.workstation_id = workstationId;
    if (keepalive) {
        // Survives the page being closed; sendBeacon can't carry the CSRF header.
        return fetch('/operator/heartbeat', {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        }).catch(() => {});
    }
    return apiCall('/operator/heartbeat', 'POST', body).catch(() => {});
}

/** Mount once in the operator layout. */
export function useOperatorHeartbeat(lineId, workstationId) {
    useEffect(() => {
        if (typeof window === 'undefined') return undefined;

        const visible = () => document.visibilityState !== 'hidden';
        const beat = () => {
            if (visible()) send('active', lineId, workstationId);
        };

        if (visible() && Date.now() - lastSentAt > MIN_GAP_MS) beat();
        const timer = setInterval(beat, HEARTBEAT_INTERVAL_MS);

        const onVisibility = () => (visible()
            ? send('active', lineId, workstationId)
            : send('inactive', lineId, workstationId, { keepalive: true }));
        const onPageHide = () => send('inactive', lineId, workstationId, { keepalive: true });
        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('pagehide', onPageHide);

        return () => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('pagehide', onPageHide);
        };
    }, [lineId, workstationId]);
}
