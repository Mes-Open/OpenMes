import { useMemo } from 'react';
import { StatusPill } from '@openmes/ui';
import { useSyncedShape } from '../../lib/useSyncedShape';
import { __, formatDateTime } from '../../lib/i18n';

/**
 * Shared pieces of the shipment screens (#400): the admin/supervisor pages and
 * the dock's loading terminal read the same three synced collections
 * (`shipments`, `shipment_items`, `shipment_scans`) and render them the same way.
 */

const STATUS_PILL = { planned: 'pending', loading: 'running', confirmed: 'done', cancelled: 'blocked' };

export function ShipmentStatusPill({ status, labels = {} }) {
    return <StatusPill status={STATUS_PILL[status] ?? 'pending'} label={labels[status] ?? status} pulse={status === 'loading'} />;
}

/** Scan result → toast severity and the colours of the result panel. */
export const RESULT_STYLE = {
    loaded: { severity: 'success', panel: 'bg-om-running-bg border-om-running text-om-running' },
    duplicate: { severity: 'warning', panel: 'bg-om-downtime-bg border-om-downtime text-om-downtime' },
    removed: { severity: 'warning', panel: 'bg-om-downtime-bg border-om-downtime text-om-downtime' },
    unexpected: { severity: 'error', panel: 'bg-om-blocked-bg border-om-blocked text-om-blocked' },
    unknown: { severity: 'error', panel: 'bg-om-blocked-bg border-om-blocked text-om-blocked' },
    blocked: { severity: 'error', panel: 'bg-om-blocked-bg border-om-blocked text-om-blocked' },
};

export function resultLabel(result) {
    return {
        loaded: __('Loaded'),
        duplicate: __('Duplicate'),
        unexpected: __('Not on this load'),
        unknown: __('Unknown code'),
        blocked: __('Blocked'),
        removed: __('Removed'),
    }[result] ?? result;
}

export function ResultBadge({ result }) {
    const pill = { loaded: 'running', duplicate: 'downtime', removed: 'downtime', unexpected: 'blocked', unknown: 'blocked', blocked: 'blocked' }[result] ?? 'pending';
    return <StatusPill status={pill} label={resultLabel(result)} pulse={false} />;
}

export function unitTypeLabel(type) {
    return type === 'carton' ? __('Carton') : type === 'pallet' ? __('Pallet') : '—';
}

/**
 * One shipment's live rows: the header, its expected units and its scan log
 * (newest first), each straight from the synced collections.
 */
export function useShipmentLive(shipmentId, initial = null) {
    const { data: shipments } = useSyncedShape('shipments');
    const { data: items, isLoading } = useSyncedShape('shipment_items');
    const { data: scans } = useSyncedShape('shipment_scans');

    return useMemo(() => {
        const id = Number(shipmentId);
        const shipment = shipments.find((s) => Number(s.id) === id) ?? initial;
        const mine = items.filter((i) => Number(i.shipment_id) === id).sort((a, b) => Number(a.id) - Number(b.id));
        const log = scans.filter((s) => Number(s.shipment_id) === id).sort((a, b) => Number(b.id) - Number(a.id));
        const loaded = mine.filter((i) => i.loaded_at);

        return {
            shipment,
            items: mine,
            scans: log,
            isLoading,
            totals: { expected: mine.length, loaded: loaded.length, missing: mine.length - loaded.length },
        };
    }, [shipments, items, scans, shipmentId, initial, isLoading]);
}

/** Expected / loaded / missing as three big numbers and a bar. */
export function LoadTotals({ totals, large = false }) {
    const pct = totals.expected > 0 ? Math.round((totals.loaded / totals.expected) * 100) : 0;
    const num = large ? 'text-[44px]' : 'text-[28px]';
    const tile = (label, value, tone, testId) => (
        <div className="bg-om-card border border-om-line rounded-om px-4 py-3" data-testid={testId}>
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</div>
            <div className={`${num} font-semibold leading-tight ${tone}`}>{value}</div>
        </div>
    );

    return (
        <div>
            <div className="grid grid-cols-3 gap-3">
                {tile(__('Expected'), totals.expected, 'text-om-ink', 'total-expected')}
                {tile(__('Loaded'), totals.loaded, 'text-om-running', 'total-loaded')}
                {tile(__('Missing'), totals.missing, totals.missing > 0 ? 'text-om-blocked' : 'text-om-muted', 'total-missing')}
            </div>
            <div className="mt-3 h-2 rounded-full bg-om-chip overflow-hidden" aria-hidden>
                <div className="h-full bg-om-running transition-all duration-300" style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

/** The expected list: what is on the truck and what is still missing. */
export function ExpectedList({ items, userNames = {}, onUndo, busyId }) {
    if (items.length === 0) {
        return <p className="text-[13px] text-om-muted px-4 py-6">{__('No units on the expected list.')}</p>;
    }

    return (
        <table className="w-full text-[13px]">
            <thead>
                <tr className="text-left font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint border-b border-om-line">
                    <th className="px-4 py-2">{__('Label')}</th>
                    <th className="px-4 py-2">{__('Type')}</th>
                    <th className="px-4 py-2">{__('Status')}</th>
                    <th className="px-4 py-2">{__('Loaded by')}</th>
                    {onUndo && <th className="px-4 py-2 text-right">{__('Actions')}</th>}
                </tr>
            </thead>
            <tbody>
                {items.map((item) => (
                    <tr
                        key={item.id}
                        data-testid={`item-${item.identifier}`}
                        data-loaded={item.loaded_at ? '1' : '0'}
                        className={`border-b border-om-line2 ${item.loaded_at ? 'bg-om-running-bg/40' : ''}`}
                    >
                        <td className="px-4 py-2.5 font-mono font-medium text-om-ink">{item.identifier}</td>
                        <td className="px-4 py-2.5 text-om-muted">{unitTypeLabel(item.unit_type)}</td>
                        <td className="px-4 py-2.5">
                            {item.loaded_at
                                ? <StatusPill status="running" label={__('Loaded')} pulse={false} />
                                : <StatusPill status="pending" label={__('Missing')} pulse={false} />}
                        </td>
                        <td className="px-4 py-2.5 text-om-muted">
                            {item.loaded_at ? `${userNames[item.loaded_by_id] ?? '—'} · ${formatDateTime(item.loaded_at)}` : '—'}
                        </td>
                        {onUndo && (
                            <td className="px-4 py-2.5 text-right">
                                {item.loaded_at && (
                                    <button
                                        type="button"
                                        onClick={() => onUndo(item)}
                                        disabled={busyId === item.id}
                                        className="text-[12px] font-semibold text-om-blocked hover:underline disabled:opacity-50"
                                    >
                                        {__('Undo')}
                                    </button>
                                )}
                            </td>
                        )}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/** Every scan against the shipment, newest first. */
export function ScanLog({ scans, userNames = {}, limit = 50 }) {
    if (scans.length === 0) {
        return <p className="text-[13px] text-om-muted px-4 py-6">{__('No scans yet.')}</p>;
    }

    return (
        <ul className="divide-y divide-om-line2" data-testid="scan-log">
            {scans.slice(0, limit).map((scan) => (
                <li key={scan.id} className="px-4 py-2.5 flex items-start gap-3 text-[13px]">
                    <ResultBadge result={scan.result} />
                    <div className="flex-1 min-w-0">
                        <div className="font-mono text-om-ink">{scan.scanned_code}</div>
                        {scan.message && <div className="text-[12px] text-om-muted">{scan.message}</div>}
                    </div>
                    <div className="text-right text-[11.5px] text-om-faint whitespace-nowrap">
                        <div>{formatDateTime(scan.scanned_at, { hour: '2-digit', minute: '2-digit', second: '2-digit' })}</div>
                        <div>{userNames[scan.user_id] ?? ''}</div>
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** Card wrapper used by the shipment screens. */
export function Panel({ title, actions, children, className = '' }) {
    return (
        <section className={`bg-om-card border border-om-line rounded-om ${className}`}>
            {(title || actions) && (
                <header className="flex items-center justify-between px-4 py-3 border-b border-om-line">
                    <h2 className="text-[14px] font-semibold text-om-ink">{title}</h2>
                    {actions}
                </header>
            )}
            {children}
        </section>
    );
}
