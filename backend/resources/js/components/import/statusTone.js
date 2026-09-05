import { __ } from '../../lib/i18n';

/** csv_imports.status (+ failed count) → StatusPill tone + label. */
export function statusTone(status, failedRows = 0) {
    switch (status) {
        case 'PENDING':
            return { tone: 'pending', label: __('Queued') };
        case 'PROCESSING':
            return { tone: 'running', label: __('Processing') };
        case 'COMPLETED':
            return (failedRows ?? 0) > 0
                ? { tone: 'downtime', label: __('Completed with errors') }
                : { tone: 'done', label: __('Completed') };
        case 'FAILED':
            return { tone: 'blocked', label: __('Failed') };
        default:
            return { tone: 'pending', label: status ?? '' };
    }
}

export const ACTIVE_STATUSES = ['PENDING', 'PROCESSING'];

export function isActiveStatus(status) {
    return ACTIVE_STATUSES.includes(status);
}

/** 0–100; a finished run always reads 100. */
export function progressOf(row) {
    if (!row) return 0;
    if (!isActiveStatus(row.status)) return 100;
    const total = Number(row.total_rows ?? 0);
    if (total <= 0) return 0;
    return Math.min(100, Math.floor((Number(row.processed_rows ?? 0) * 100) / total));
}

const RANK = { PENDING: 0, PROCESSING: 1, COMPLETED: 2, FAILED: 2 };

/**
 * The server snapshot and the live collection row describe the same run, but
 * either can be the older one: the collection snapshot is fetched once when the
 * page mounts, while a reload fetches fresh props. Keep whichever has advanced
 * further, so a stale copy never rewinds the status or the counters.
 */
export function newerRun(a, b) {
    if (!a) return b;
    if (!b) return a;
    const ra = RANK[a.status] ?? -1;
    const rb = RANK[b.status] ?? -1;
    if (ra !== rb) return ra > rb ? a : b;
    return Number(b.processed_rows ?? 0) > Number(a.processed_rows ?? 0) ? b : a;
}
