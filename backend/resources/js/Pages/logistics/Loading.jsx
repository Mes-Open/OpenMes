import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button, InlineAlert, Modal, useToast } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import {
    ExpectedList, LoadTotals, Panel, RESULT_STYLE, ScanLog, ShipmentStatusPill, resultLabel, useShipmentLive,
} from '../../components/shipments/shipmentUi';
import { createScanQueue } from '../../components/shipments/scanQueue';
import { apiCall } from '../../lib/http';
import { __ } from '../../lib/i18n';

/** The message a refused JSON call carries, without ever showing server internals. */
async function errorMessage(res) {
    try {
        const body = await res.json();
        const first = body?.errors && Object.values(body.errors)[0];
        return (Array.isArray(first) ? first[0] : first) || body?.message || __('Something went wrong.');
    } catch {
        return __('Something went wrong.');
    }
}

/**
 * The dock's loading terminal (#400).
 *
 * One big input that a keyboard-wedge scanner types into; Enter sends the code.
 * The input clears at once and codes scanned while an answer is still on its
 * way are queued and sent in order, so a fast scanner never loses a label.
 * The answer is shown at once in a coloured panel (green loaded, amber
 * duplicate, red unexpected / unknown / blocked) and as a toast — the page never
 * navigates, so the scanner can keep going. The expected list, the totals and
 * the scan log are synced collections: a second screen watching the same load
 * sees every scan as it happens.
 */
export default function Loading() {
    const { shipment: initial, statusLabels = {}, canConfirmShort, userNames = {} } = usePage().props;
    const { shipment: live, items, scans, totals } = useShipmentLive(initial.id, initial);
    const shipment = { ...initial, ...live };
    const active = shipment.status === 'planned' || shipment.status === 'loading';

    const toast = useToast();
    const inputRef = useRef(null);
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [last, setLast] = useState(null);
    const [undoing, setUndoing] = useState(null);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [confirmError, setConfirmError] = useState(null);
    const [confirming, setConfirming] = useState(false);

    // Keep the cursor in the scan box: a wedge scanner types wherever focus is.
    const refocus = useCallback(() => {
        if (!confirmOpen) setTimeout(() => inputRef.current?.focus(), 0);
    }, [confirmOpen]);
    useEffect(() => { refocus(); }, [refocus]);

    const show = (result, title, message) => {
        setLast({ result, title, message });
        toast({ severity: RESULT_STYLE[result]?.severity ?? 'error', title, body: message, duration: 2500 });
    };

    const sendScan = async (value) => {
        try {
            const res = await apiCall(`/logistics/loading/${shipment.id}/scan`, 'POST', { code: value });
            if (res.ok) {
                const { data } = await res.json();
                show(data.scan.result, `${resultLabel(data.scan.result)} · ${data.scan.scanned_code}`, data.scan.message);
            } else {
                show('blocked', `${__('Scan refused')} · ${value}`, await errorMessage(res));
            }
        } catch {
            show('blocked', __('No connection'), __('The scan was not recorded. Scan again.'));
        } finally {
            refocus();
        }
    };
    // The queue lives as long as the page; it always calls the latest sendScan.
    const sendRef = useRef(sendScan);
    sendRef.current = sendScan;
    const queueRef = useRef(null);
    if (!queueRef.current) queueRef.current = createScanQueue((value) => sendRef.current(value), setBusy);

    const submitScan = (e) => {
        e.preventDefault();
        const value = code.trim();
        setCode('');
        if (value) queueRef.current.push(value);
    };

    const undo = async (item) => {
        setUndoing(item.id);
        try {
            const res = await apiCall(`/logistics/loading/${shipment.id}/items/${item.id}/unload`, 'POST', {});
            if (res.ok) {
                show('removed', `${__('Removed')} · ${item.identifier}`, __(':identifier taken off the load.', { identifier: item.identifier }));
            } else {
                show('blocked', __('Undo refused'), await errorMessage(res));
            }
        } finally {
            setUndoing(null);
            refocus();
        }
    };

    const confirmLoad = async (withReason) => {
        setConfirming(true);
        setConfirmError(null);
        try {
            const res = await apiCall(`/logistics/loading/${shipment.id}/confirm`, 'POST', withReason ? { short_reason: reason } : {});
            if (res.ok) {
                setConfirmOpen(false);
                setLast({ result: 'loaded', title: __('Loading confirmed'), message: __('Shipment :no has left. Pallets are marked as shipped.', { no: shipment.shipment_no }) });
                toast({ severity: 'success', title: __('Loading confirmed'), body: shipment.shipment_no });
            } else {
                const msg = await errorMessage(res);
                if (confirmOpen) {
                    setConfirmError(msg);
                } else {
                    show('blocked', res.status === 403 ? __('Confirmation refused') : __('Cannot confirm yet'), msg);
                }
            }
        } finally {
            setConfirming(false);
            refocus();
        }
    };

    // A short load asks a supervisor for the reason first; everyone else sends
    // it straight away and gets the server's answer.
    const onConfirmClick = () => {
        if (totals.missing > 0 && canConfirmShort) {
            setReason('');
            setConfirmError(null);
            setConfirmOpen(true);
        } else {
            confirmLoad(false);
        }
    };

    const panel = last ? RESULT_STYLE[last.result]?.panel : 'bg-om-card border-om-line text-om-muted';

    return (
        <div className="max-w-7xl mx-auto space-y-5">
            <Head title={`${__('Loading')} ${shipment.shipment_no}`} />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link href="/logistics/loading" className="text-[13px] text-om-muted hover:text-om-ink">‹ {__('All open shipments')}</Link>
                    <div className="flex items-center gap-3 mt-1">
                        <h1 className="text-[28px] font-semibold tracking-[-0.02em] text-om-ink font-mono" data-testid="shipment-no">{shipment.shipment_no}</h1>
                        <span data-testid="shipment-status"><ShipmentStatusPill status={shipment.status} labels={statusLabels} /></span>
                    </div>
                    <p className="text-[13px] text-om-muted">
                        {[shipment.carrier, shipment.vehicle_no, shipment.destination].filter(Boolean).join(' · ') || '—'}
                    </p>
                </div>
                {active && (
                    <Button size="lg" variant={totals.missing === 0 ? 'success' : 'primary'} onClick={onConfirmClick} loading={confirming} data-testid="confirm-loading">
                        {__('Confirm loading')}
                    </Button>
                )}
            </div>

            {!active && (
                <InlineAlert severity={shipment.status === 'confirmed' ? 'success' : 'warning'} title={statusLabels[shipment.status] ?? shipment.status}>
                    {shipment.short_reason
                        ? __('Shipped short: :reason', { reason: shipment.short_reason })
                        : __('This shipment is closed. Scans are no longer accepted.')}
                </InlineAlert>
            )}

            <form onSubmit={submitScan} className={`rounded-om border-2 p-5 transition-colors ${panel}`} data-testid="scan-panel" data-result={last?.result ?? ''} data-busy={busy ? 'true' : 'false'}>
                <label htmlFor="scan-code" className="block font-mono text-[10px] uppercase tracking-[0.1em] text-om-faint mb-2">{__('Scan pallet or carton label')}</label>
                <input
                    id="scan-code"
                    ref={inputRef}
                    name="code"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    onBlur={refocus}
                    disabled={!active}
                    autoFocus
                    autoComplete="off"
                    spellCheck={false}
                    maxLength={64}
                    placeholder={active ? __('Scan or type, then Enter') : ''}
                    className="w-full bg-om-bg border border-om-line rounded-om-sm px-5 py-4 text-[30px] font-mono text-om-ink outline-none focus:border-om-accent disabled:opacity-50"
                />
                <div className="mt-4 min-h-[64px]" aria-live="assertive" data-testid="scan-result">
                    {last ? (
                        <>
                            <div className="text-[26px] font-bold leading-tight">{last.title}</div>
                            {last.message && <div className="text-[15px] text-om-ink mt-1">{last.message}</div>}
                        </>
                    ) : (
                        <div className="text-[15px]">{__('Waiting for the first scan.')}</div>
                    )}
                </div>
            </form>

            <LoadTotals totals={totals} large />

            <div className="grid grid-cols-1 lg:grid-cols-5 gap-5">
                <Panel title={__('Expected list')} className="lg:col-span-3">
                    <ExpectedList items={items} userNames={userNames} onUndo={active ? undo : undefined} busyId={undoing} />
                </Panel>
                <Panel title={__('Scan log')} className="lg:col-span-2">
                    <ScanLog scans={scans} userNames={userNames} limit={30} />
                </Panel>
            </div>

            <Modal
                open={confirmOpen}
                onClose={() => { setConfirmOpen(false); refocus(); }}
                title={__('Confirm a short load')}
                subtitle={shipment.shipment_no}
                closeLabel={__('Close')}
                footer={(
                    <>
                        <Button variant="secondary" onClick={() => { setConfirmOpen(false); refocus(); }}>{__('Cancel')}</Button>
                        <Button variant="accent" onClick={() => confirmLoad(true)} loading={confirming} data-testid="confirm-short">{__('Ship short')}</Button>
                    </>
                )}
            >
                <div className="space-y-3">
                    <p className="text-[13px] text-om-ink">
                        {__(':missing of :expected units are not loaded. They stay at the plant.', { missing: totals.missing, expected: totals.expected })}
                    </p>
                    <label htmlFor="short-reason" className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Reason')} <span className="text-om-blocked">*</span></label>
                    <textarea
                        id="short-reason"
                        name="short_reason"
                        rows={3}
                        maxLength={1000}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        className="w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none focus:border-om-accent"
                    />
                    {confirmError && <InlineAlert severity="error" title={__('Not confirmed')}>{confirmError}</InlineAlert>}
                </div>
            </Modal>
        </div>
    );
}

Loading.layout = (page) => <AppLayout>{page}</AppLayout>;
