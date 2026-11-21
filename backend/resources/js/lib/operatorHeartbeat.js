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
// Tabs of this browser that are currently showing the panel: { tabId: lastVisibleAt }.
const TABS_KEY = 'openmes.operatorPanelVisibleTabs';
const TAB_ID_KEY = 'openmes.operatorPanelTabId';
// A visible tab refreshes its entry with every heartbeat; one older than this
// belongs to a tab that crashed or was killed without saying goodbye.
export const TAB_STALE_MS = HEARTBEAT_INTERVAL_MS * 2 + 5000;

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
 * All tabs of one browser report as one panel (one roster row, keyed on the
 * localStorage id). So a tab that is hidden or closed must not report the
 * panel inactive while another tab still shows it - that tab's next heartbeat
 * would flip it back. True when some other tab was visible recently.
 */
export function otherTabVisible(tabs, selfId, now = Date.now()) {
    return Object.entries(tabs ?? {}).some(([id, at]) => id !== selfId && now - at < TAB_STALE_MS);
}

/** This tab's id; sessionStorage is per tab and survives a reload of it. */
function tabId() {
    try {
        let id = window.sessionStorage.getItem(TAB_ID_KEY);
        if (!id) {
            id = randomId();
            window.sessionStorage.setItem(TAB_ID_KEY, id);
        }
        return id;
    } catch {
        window.__openmesPanelTabId ??= randomId();
        return window.__openmesPanelTabId;
    }
}

function readTabs() {
    try {
        return JSON.parse(window.localStorage.getItem(TABS_KEY) ?? '{}') ?? {};
    } catch {
        return {};
    }
}

/** Record (visible = true) or drop this tab from the browser's visible tabs. */
function markTab(visible) {
    try {
        const tabs = readTabs();
        const now = Date.now();
        // Drop entries of tabs that died without cleaning up.
        for (const [id, at] of Object.entries(tabs)) {
            if (now - at >= TAB_STALE_MS) delete tabs[id];
        }
        if (visible) tabs[tabId()] = now;
        else delete tabs[tabId()];
        window.localStorage.setItem(TABS_KEY, JSON.stringify(tabs));
    } catch {
        // Storage blocked: every tab reports for itself, as before.
    }
}

/** Report this panel inactive, unless another tab of this browser still shows it. */
function sendInactive(lineId, workstationId) {
    markTab(false);
    if (otherTabVisible(readTabs(), tabId())) return undefined;
    return send('inactive', lineId, workstationId, { keepalive: true });
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
            if (!visible()) return;
            markTab(true);
            send('active', lineId, workstationId);
        };

        if (visible()) {
            markTab(true);
            if (Date.now() - lastSentAt > MIN_GAP_MS) beat();
        }
        const timer = setInterval(beat, HEARTBEAT_INTERVAL_MS);

        const onVisibility = () => (visible() ? beat() : sendInactive(lineId, workstationId));
        const onPageHide = () => sendInactive(lineId, workstationId);
        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('pagehide', onPageHide);

        return () => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('pagehide', onPageHide);
        };
    }, [lineId, workstationId]);
}
