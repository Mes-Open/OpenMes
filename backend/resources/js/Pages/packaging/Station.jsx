// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls (scanning logic untouched).
import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Badge, Button, Dropdown, StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../layouts/AppLayout';
import { __, formatTime } from '../../lib/i18n';
import LabelPrintMenu from '../../components/LabelPrintMenu';

function csrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

/** Group open pallets by their work order's line, for the per-line list. */
function groupByLine(pallets) {
    const groups = new Map();
    for (const p of pallets) {
        const key = p.line_name || __('No line');
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(p);
    }
    return Array.from(groups.entries());
}

function ProgressBar({ pct, done }) {
    const color = done ? 'bg-om-running' : pct >= 50 ? 'bg-om-downtime' : 'bg-om-accent';
    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 bg-om-line rounded-full h-1.5">
                <div className={`h-1.5 rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
            <span className="font-mono text-[11px] text-om-faint w-8 text-right">{pct}%</span>
        </div>
    );
}

function ShiftLabel() {
    const h = new Date().getHours();
    return h >= 6 && h < 18 ? '06:00 – 18:00' : '18:00 – 06:00';
}

export default function Station() {
    const { auth, labelTemplates = [], currentShift = null } = usePage().props;

    const [items, setItems] = useState([]);
    const [history, setHistory] = useState([]);
    const [stats, setStats] = useState({ today_packed: 0, plan: 0, backlog: 0 });
    const [lastScan, setLastScan] = useState(null);
    const [flash, setFlash] = useState(null); // 'success' | 'error' | null
    const [activePallet, setActivePallet] = useState(null); // { id, pallet_no, work_order_id, order_no, qty }
    const [openPallets, setOpenPallets] = useState([]); // all currently open pallets (persist across shifts)
    const [palletWoId, setPalletWoId] = useState(''); // selected work order for a new pallet
    const [palletBatchId, setPalletBatchId] = useState(''); // selected batch (when the WO has several)
    const [palletBusy, setPalletBusy] = useState(false);
    const lastHistoryIdRef = useRef(0);
    const bufferRef = useRef('');
    const bufferTimerRef = useRef(null);
    const activePalletRef = useRef(null);
    useEffect(() => { activePalletRef.current = activePallet; }, [activePallet]);

    const realizacja =
        stats.plan > 0 ? Math.min(100, Math.round((stats.today_packed / stats.plan) * 100)) : 0;

    const itemColumns = useMemo(() => [
        {
            id: 'order_no',
            accessorKey: 'order_no',
            header: __('Order'),
            cell: ({ row }) => <span className="font-mono font-semibold text-om-ink">{row.original.order_no}</span>,
        },
        {
            id: 'product',
            accessorKey: 'product',
            header: __('Product'),
            meta: { flex: true },
            cell: ({ row }) => <span className="text-om-ink">{row.original.product}</span>,
        },
        {
            id: 'ean',
            accessorFn: (r) => (r.eans ?? []).join(' '),
            header: 'EAN',
            cell: ({ row }) => (row.original.eans ?? []).map((ean) => (
                <span key={ean} className="inline-block font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-[5px] mr-1 mb-0.5">
                    {ean}
                </span>
            )),
        },
        {
            id: 'packed_qty',
            accessorKey: 'packed_qty',
            header: __('Packed'),
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono font-semibold text-om-ink">{row.original.packed_qty}</span>,
        },
        {
            id: 'planned_qty',
            accessorKey: 'planned_qty',
            header: __('Plan'),
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.planned_qty}</span>,
        },
        {
            id: 'progress',
            accessorKey: 'progress',
            header: __('Progress'),
            cell: ({ row }) => <ProgressBar pct={row.original.progress} done={row.original.done} />,
        },
    ], []);

    const historyColumns = useMemo(() => [
        {
            id: 'scanned_at',
            accessorKey: 'scanned_at',
            header: __('Time'),
            cell: ({ row }) => <span className="font-mono text-om-muted text-[11px] whitespace-nowrap">{row.original.scanned_at}</span>,
        },
        {
            id: 'product_name',
            accessorKey: 'product_name',
            header: __('Product'),
            meta: { flex: true },
            cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.product_name}</span>,
        },
        {
            id: 'ean',
            accessorKey: 'ean',
            header: 'EAN',
            cell: ({ row }) => <span className="font-mono text-[11px] text-om-muted">{row.original.ean}</span>,
        },
    ], []);

    const fetchItems = useCallback(async () => {
        try {
            const res = await fetch('/packaging/items', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                const data = await res.json();
                setItems(data.items ?? []);
            }
        } catch {}
    }, []);

    const fetchHistory = useCallback(async () => {
        try {
            const res = await fetch('/packaging/history', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            const hist = data.history ?? [];
            setHistory(hist);
            if (hist.length > 0) {
                lastHistoryIdRef.current = Math.max(...hist.map((h) => h.id));
            }
        } catch {}
    }, []);

    const fetchStats = useCallback(async () => {
        try {
            const res = await fetch('/packaging/stats', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                const data = await res.json();
                setStats(data);
            }
        } catch {}
    }, []);

    const fetchOpenPallets = useCallback(async () => {
        try {
            const res = await fetch('/packaging/pallets', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            const list = data.pallets ?? [];
            setOpenPallets(list);
            // Keep the active pallet's qty in sync if another shift/device changed it,
            // and drop it if it was closed elsewhere.
            const active = activePalletRef.current;
            if (active) {
                const fresh = list.find((p) => p.id === active.id);
                if (fresh) setActivePallet((p) => ({ ...p, ...fresh }));
                else setActivePallet(null);
            }
        } catch {}
    }, []);

    const poll = useCallback(async () => {
        try {
            const res = await fetch(`/packaging/history/poll?after_id=${lastHistoryIdRef.current}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            const newEntries = data.history ?? [];
            if (newEntries.length > 0) {
                setHistory((prev) => {
                    const merged = [...newEntries, ...prev].slice(0, 100);
                    return merged;
                });
                lastHistoryIdRef.current = Math.max(lastHistoryIdRef.current, ...newEntries.map((h) => h.id));
                await Promise.all([fetchItems(), fetchStats()]);
            }
        } catch {}
    }, [fetchItems, fetchStats]);

    const handleScan = useCallback(async (ean) => {
        try {
            const palletId = activePalletRef.current?.id ?? null;
            const res = await fetch('/packaging/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ean, pallet_id: palletId }),
            });
            const data = await res.json();

            if (res.ok) {
                const wo = data.work_order;
                if (data.pallet) setActivePallet((p) => (p && p.id === data.pallet.id ? { ...p, ...data.pallet } : p));
                const packedQty = wo.packed_qty;
                const plannedQty = wo.planned_qty;
                const pct = plannedQty > 0 ? Math.min(100, Math.round((packedQty / plannedQty) * 100)) : 0;
                setLastScan({
                    success: true,
                    product: wo.product,
                    ean,
                    packed_qty: packedQty,
                    planned_qty: plannedQty,
                    progress: pct,
                    scanned_at: formatTime(new Date()),
                });
                setFlash('success');
                await Promise.all([fetchItems(), fetchStats(), fetchOpenPallets()]);
                setHistory((prev) => [
                    { id: Date.now(), ean, product_name: wo.product, scanned_at: formatTime(new Date()) },
                    ...prev,
                ].slice(0, 100));
            } else {
                setLastScan({ success: false, ean, error: data.message, scanned_at: formatTime(new Date()) });
                setFlash('error');
            }
        } catch {
            setLastScan({ success: false, ean, error: __('Connection error'), scanned_at: formatTime(new Date()) });
            setFlash('error');
        }

        setTimeout(() => setFlash(null), 2000);
    }, [fetchItems, fetchStats, fetchOpenPallets]);

    const createPallet = useCallback(async () => {
        if (!palletWoId || palletBusy) return;
        setPalletBusy(true);
        try {
            const res = await fetch('/packaging/pallets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    work_order_id: Number(palletWoId),
                    ...(palletBatchId ? { batch_id: Number(palletBatchId) } : {}),
                }),
            });
            const data = await res.json();
            if (res.ok) {
                setActivePallet(data.pallet);
                setPalletWoId('');
                setPalletBatchId('');
                fetchOpenPallets();
            } else {
                setLastScan({ success: false, ean: '—', error: data.message, scanned_at: formatTime(new Date()) });
                setFlash('error');
                setTimeout(() => setFlash(null), 2000);
            }
        } catch {
            /* ignore — best effort */
        } finally {
            setPalletBusy(false);
        }
    }, [palletWoId, palletBatchId, palletBusy, fetchOpenPallets]);

    // Resume an already-open pallet (e.g. one started on a previous shift) so the
    // next scans keep filling it instead of creating a new pallet.
    const resumePallet = useCallback((pallet) => {
        setActivePallet(pallet);
    }, []);

    const closePallet = useCallback(async () => {
        const pallet = activePalletRef.current;
        if (!pallet || palletBusy) return;
        setPalletBusy(true);
        try {
            const res = await fetch(`/packaging/pallets/${pallet.id}/close`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                setActivePallet(null);
                setPalletWoId('');
                fetchOpenPallets();
            }
        } catch {
            /* ignore — best effort */
        } finally {
            setPalletBusy(false);
        }
    }, [palletBusy, fetchOpenPallets]);

    const onKey = useCallback((e) => {
        const tag = e.target.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if (e.key === 'Enter') {
            const ean = bufferRef.current.trim();
            bufferRef.current = '';
            if (bufferTimerRef.current) clearTimeout(bufferTimerRef.current);
            if (ean) handleScan(ean);
        } else if (e.key.length === 1) {
            bufferRef.current += e.key;
            if (bufferTimerRef.current) clearTimeout(bufferTimerRef.current);
            bufferTimerRef.current = setTimeout(() => { bufferRef.current = ''; }, 500);
        }
    }, [handleScan]);

    useEffect(() => {
        Promise.all([fetchItems(), fetchHistory(), fetchStats(), fetchOpenPallets()]);
        const interval = setInterval(poll, 3000);
        // Refresh the open-pallets list on a slower cadence so a pallet opened on
        // another device/shift shows up here too.
        const palletInterval = setInterval(fetchOpenPallets, 5000);
        document.addEventListener('keydown', onKey);
        return () => {
            clearInterval(interval);
            clearInterval(palletInterval);
            document.removeEventListener('keydown', onKey);
        };
    }, [fetchItems, fetchHistory, fetchStats, fetchOpenPallets, poll, onKey]);

    const flashBg =
        flash === 'success'
            ? 'bg-om-running-bg border-om-running/30'
            : flash === 'error'
            ? 'bg-om-blocked-bg border-om-blocked/30'
            : 'bg-om-card border-om-line';

    // Work order selected for a new pallet + its batches (one lookup, reused by
    // the batch picker and the create-button guard). A single batch auto-links
    // server-side, so the picker only shows when there are 2+.
    const palletWo = items.find((it) => String(it.id) === String(palletWoId));
    const palletBatches = palletWo?.batches ?? [];

    return (
        <>
            <Head title={__('Packing Station')} />
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-[-0.02em] text-om-ink flex items-center gap-2">
                            <svg className="w-6 h-6 text-om-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                            </svg>
                            {__('Packing Station')}
                        </h1>
                        <p className="text-[12.5px] text-om-muted mt-1">
                            {__('Shift')}:{' '}
                            <span className="font-semibold text-om-ink">
                                {currentShift
                                    ? `${currentShift.name} (${currentShift.start}–${currentShift.end})`
                                    : <ShiftLabel />}
                            </span>
                            &nbsp;&middot;&nbsp; {__('Logged in')}: <span className="font-semibold text-om-ink">{auth?.user?.name}</span>
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusPill status="running" label={__('Scanning active')} />
                    </div>
                </div>

                {/* Stats row */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <p className="font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] text-om-ink">{stats.today_packed ?? '—'}</p>
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2">{__('Packed (shift)')}</p>
                    </div>
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <p className="font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] text-om-muted">{stats.plan ?? '—'}</p>
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2">{__('Total plan')}</p>
                    </div>
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <p className={`font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] ${(stats.backlog ?? 0) > 0 ? 'text-om-blocked' : 'text-om-running'}`}>
                            {stats.backlog ?? '—'}
                        </p>
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2">{__('Station backlog')}</p>
                    </div>
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <p className={`font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] ${realizacja >= 100 ? 'text-om-running' : realizacja >= 50 ? 'text-om-downtime' : 'text-om-blocked'}`}>
                            {realizacja}%
                        </p>
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2">{__('Completion')}</p>
                    </div>
                </div>

                {/* Active pallet */}
                <div className="bg-om-card border border-om-line rounded-om p-5 mb-6">
                    {!activePallet ? (
                        <div className="flex flex-col sm:flex-row sm:items-end gap-3">
                            <div className="flex-1">
                                <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">
                                    {__('Create pallet for order')}
                                </label>
                                <Dropdown
                                    value={palletWoId == null ? '' : String(palletWoId)}
                                    onChange={(v) => { setPalletWoId(v); setPalletBatchId(''); }}
                                    placeholder={__('— Select order —')}
                                    options={items.map((it) => ({
                                        value: String(it.id),
                                        label: `${it.order_no} — ${it.product}`,
                                    }))}
                                    className="w-full"
                                />
                            </div>
                            {palletBatches.length >= 2 && (
                                <div className="flex-1">
                                    <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">
                                        {__('Batch')}
                                    </label>
                                    <Dropdown
                                        value={palletBatchId == null ? '' : String(palletBatchId)}
                                        onChange={(v) => setPalletBatchId(v)}
                                        placeholder={__('— Select batch —')}
                                        options={palletBatches.map((b) => ({ value: String(b.id), label: b.label }))}
                                        className="w-full"
                                    />
                                </div>
                            )}
                            <Button
                                variant="accent"
                                onClick={createPallet}
                                disabled={!palletWoId || palletBusy || (palletBatches.length >= 2 && !palletBatchId)}
                                className="px-6 py-4 text-[15px]"
                            >
                                {__('+ Create pallet')}
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Active pallet')}</p>
                                <p className="font-mono text-[40px] leading-tight font-semibold tracking-[-0.02em] text-om-ink">
                                    {activePallet.pallet_no}
                                </p>
                                <p className="text-[13px] text-om-muted">
                                    {__('Order')} <span className="font-semibold text-om-ink">{activePallet.order_no}</span>
                                    &nbsp;&middot;&nbsp; {__('Pieces on pallet:')} <span className="font-semibold text-om-ink">{activePallet.qty ?? 0}</span>
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <LabelPrintMenu kind="pallet" id={activePallet.id} templates={labelTemplates} label={__('Label')} />
                                <Button
                                    variant="primary"
                                    onClick={closePallet}
                                    disabled={palletBusy}
                                    className="px-6 py-4 text-[15px]"
                                >
                                    {__('Close pallet')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Open pallets (persist across shifts) — grouped by line, resumable */}
                <div className="bg-om-card border border-om-line rounded-om overflow-hidden mb-6">
                    <div className="px-4 py-3 border-b border-om-line flex items-center justify-between">
                        <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">
                            {__('Open pallets')}
                        </h2>
                        <Badge variant="neutral">{__(':count open', { count: openPallets.length })}</Badge>
                    </div>
                    {openPallets.length === 0 ? (
                        <div className="px-4 py-6 text-center text-om-faint text-[12.5px]">
                            {__('No open pallets — create one above')}
                        </div>
                    ) : (
                        groupByLine(openPallets).map(([lineName, pallets]) => (
                            <div key={lineName}>
                                <div className="px-4 py-1.5 bg-om-chip font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                                    {lineName}
                                </div>
                                {pallets.map((p) => {
                                    const isActive = activePallet?.id === p.id;
                                    return (
                                        <div
                                            key={p.id}
                                            className={`px-4 py-3 flex items-center justify-between gap-3 border-b border-om-line ${isActive ? 'bg-om-selected' : ''}`}
                                        >
                                            <div className="min-w-0">
                                                <span className="font-mono font-semibold text-om-ink">{p.pallet_no}</span>
                                                <span className="text-[13px] text-om-muted">
                                                    &nbsp;&middot;&nbsp; {p.order_no}
                                                    &nbsp;&middot;&nbsp; <span className="font-semibold text-om-ink">{p.qty} {__('pcs')}</span>
                                                    {p.location ? <>&nbsp;&middot;&nbsp; {p.location}</> : null}
                                                </span>
                                            </div>
                                            <div className="shrink-0">
                                                {isActive ? (
                                                    <StatusPill status="running" label={__('Pallet active')} />
                                                ) : (
                                                    <Button
                                                        variant="accent"
                                                        onClick={() => resumePallet(p)}
                                                        className="px-5 py-3"
                                                    >
                                                        {__('Resume')}
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ))
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    {/* Last scan */}
                    <div className="bg-om-card border border-om-line rounded-om p-5">
                        <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink border-b border-om-line pb-2.5 mb-3">
                            {__('Last scan')}
                        </h2>
                        {!lastScan ? (
                            <div className="py-8 text-center text-om-faint text-[12.5px]">
                                {__('Scan an EAN code…')}
                            </div>
                        ) : (
                            <div>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xl font-semibold tracking-[-0.01em] text-om-ink">{lastScan.product}</p>
                                        <p className="text-[12.5px] text-om-muted mt-0.5">
                                            EAN: <span className="font-mono">{lastScan.ean}</span>
                                            &nbsp;&middot;&nbsp; {lastScan.scanned_at}
                                        </p>
                                    </div>
                                    <StatusPill
                                        className="shrink-0"
                                        status={lastScan.success ? 'running' : 'blocked'}
                                        label={lastScan.success ? __('OK') : __('Error')}
                                    />
                                </div>
                                {lastScan.success && (
                                    <div className="mt-3 flex items-center gap-3">
                                        <div className="flex-1 bg-om-line rounded-full h-2">
                                            <div
                                                className={`h-2 rounded-full transition-all duration-500 ${lastScan.progress >= 100 ? 'bg-om-running' : lastScan.progress >= 50 ? 'bg-om-downtime' : 'bg-om-accent'}`}
                                                style={{ width: `${lastScan.progress ?? 0}%` }}
                                            />
                                        </div>
                                        <span className="font-mono text-[13px] font-semibold text-om-ink">
                                            {lastScan.packed_qty} / {lastScan.planned_qty} {__('pcs')}
                                        </span>
                                    </div>
                                )}
                                {!lastScan.success && lastScan.error && (
                                    <div className="mt-3 text-[13px] text-om-blocked font-medium">{lastScan.error}</div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Flash overlay */}
                    <div className={`border rounded-om flex items-center justify-center min-h-[120px] ${flashBg}`}>
                        {flash === null && (
                            <div className="text-center text-om-faint text-[12.5px] select-none">
                                <svg className="mx-auto w-10 h-10 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5"
                                        d="M12 4v1m6.364 1.636l-.707.707M20 12h-1M17.657 17.657l-.707-.707M12 20v-1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l.707.707" />
                                </svg>
                                {__('Waiting for scan…')}
                            </div>
                        )}
                        {flash === 'success' && (
                            <div className="text-center">
                                <svg className="mx-auto w-14 h-14 text-om-running" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <p className="text-om-running font-semibold mt-2">{__('Scanned!')}</p>
                            </div>
                        )}
                        {flash === 'error' && (
                            <div className="text-center">
                                <svg className="mx-auto w-14 h-14 text-om-blocked" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <p className="text-om-blocked font-semibold mt-2">{__('Scan error')}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Items to pack */}
                <div className="bg-om-card border border-om-line rounded-om overflow-hidden mb-6">
                    <div className="px-4 py-3 border-b border-om-line flex items-center justify-between">
                        <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">
                            {__('Orders to pack')}
                        </h2>
                        <Badge variant="neutral">{__(':count items', { count: items.length })}</Badge>
                    </div>
                    <DataTable
                        data={items}
                        columns={itemColumns}
                        searchable
                        columnToggle
                        paginated
                        searchPlaceholder={__('Search orders…')}
                        emptyLabel={__('No orders with assigned EAN codes')}
                    />
                </div>

                {/* Scan log */}
                <div className="bg-om-card border border-om-line rounded-om overflow-hidden">
                    <div className="px-4 py-3 border-b border-om-line">
                        <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">
                            {__('Scan history (shift)')}
                        </h2>
                    </div>
                    <DataTable
                        data={history}
                        columns={historyColumns}
                        searchable
                        columnToggle
                        paginated
                        searchPlaceholder={__('Search scans…')}
                        emptyLabel={__('No scans this shift')}
                    />
                </div>
            </div>
        </>
    );
}

Station.layout = (page) => <AppLayout>{page}</AppLayout>;
