// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls (scanning logic untouched).
import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Badge, Button, Dropdown, Icon, InlineAlert, Modal, ProgressBar, StatusPill, TextField, useToast } from '@openmes/ui';
import AppDataTable from '../../components/AppDataTable';
import AppLayout from '../../layouts/AppLayout';
import OperatorLayout from '../../layouts/OperatorLayout';
import LabelPreviewModal from '../../components/LabelPreviewModal';
import useScanBuffer from '../../lib/useScanBuffer';
import LabelPrintMenu from '../../components/LabelPrintMenu';
import { __, formatTime } from '../../lib/i18n';
import { readParam, writeParams } from '../../lib/urlState';

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

function ShiftLabel() {
    const h = new Date().getHours();
    return h >= 6 && h < 18 ? '06:00 – 18:00' : '18:00 – 06:00';
}

export default function Station() {
    const { auth, labelTemplates = [], currentShift = null, packingSteps: initialPackingSteps = [], scannerMode = 'hid' } = usePage().props;
    // Staff get the shift and login line under the title; the operator shell
    // already names the line and the user in its header.
    const staff = (auth?.user?.roles ?? []).some((r) => r === 'Admin' || r === 'Supervisor');

    const [items, setItems] = useState([]);
    const [palletOrders, setPalletOrders] = useState([]); // every packable order, for the pallet picker
    const [history, setHistory] = useState([]);
    const [stats, setStats] = useState({ today_packed: 0, plan: 0, backlog: 0 });
    const [lastScan, setLastScan] = useState(null);
    // EAN packing (products without serial numbers): its cards show only when it is in use.
    const usesEan = items.length > 0 || history.length > 0 || lastScan != null;
    const [psn, setPsn] = useState('');
    const [psnBusy, setPsnBusy] = useState(false);
    const [psnResult, setPsnResult] = useState(null); // { success, unit?, error? }
    // Weight check: the step wants the unit weighed before it goes in the box.
    // The scan comes back asking for the weight; the scale (or the operator)
    // types it here and Enter sends the same scan again with it.
    const [weighing, setWeighing] = useState(null); // { psn, expected, tolerance }
    const [weight, setWeight] = useState('');
    const weightRef = useRef(null);
    const [cartonLabel, setCartonLabel] = useState(null); // { unit?, pdf, zpl?, title?, subtitle? } shown in the preview modal
    // Labels arrive faster than they are printed: the unit's, then the carton it
    // just filled, then the pallet that carton filled. Each waits its turn
    // behind the one on screen, so none is lost.
    const labelQueueRef = useRef([]);
    const showLabel = useCallback((label) => {
        setCartonLabel((current) => {
            if (current) { labelQueueRef.current.push(label); return current; }
            return label;
        });
    }, []);
    const closeLabel = useCallback(() => setCartonLabel(labelQueueRef.current.shift() ?? null), []);
    const onPalletClosedRef = useRef(null); // closeCarton is declared before the pallet helpers; it reaches them through this
    const [packingSteps, setPackingSteps] = useState(initialPackingSteps); // the routing's packing steps this station works on
    const toast = useToast();
    const [scanTarget, setScanTarget] = useState('auto'); // 'auto' | 'carton:<id>' | 'pallet:<id>' | 'none'
    const [cartons, setCartons] = useState([]); // open cartons
    const [activeCarton, setActiveCarton] = useState(null);
    const [cartonUnits, setCartonUnits] = useState([]);
    const [cartonBusy, setCartonBusy] = useState(false);

    // Packaging from the BOM: with lot tracking on, the carton's lot is picked
    // here before the first unit goes into the box.
    const [pickStep, setPickStep] = useState(null); // { step, materials, candidates, picks: {material_id: lot_id}, error }
    const openPicks = useCallback(async (step) => {
        setPickStep({ step, materials: step.materials ?? [], candidates: [], picks: {}, error: null, loading: true });
        try {
            const res = await fetch(`/packaging/packing-steps/${step.id}/materials`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            const picks = {};
            (data.candidates ?? []).forEach((c) => { picks[c.material_id] = String(c.proposed?.[0]?.material_lot_id ?? c.candidates?.[0]?.id ?? ''); });
            setPickStep({ step, materials: data.materials ?? [], candidates: data.candidates ?? [], picks, error: null, loading: false });
        } catch {
            setPickStep((p) => (p ? { ...p, loading: false, error: __('Network error, try again.') } : p));
        }
    }, []);
    const confirmPicks = useCallback(async () => {
        if (!pickStep) return;
        const picks = {};
        for (const c of pickStep.candidates) {
            const lotId = pickStep.picks[c.material_id];
            if (!lotId) { setPickStep((p) => ({ ...p, error: __('Choose a lot for every material.') })); return; }
            picks[c.material_id] = [{ material_lot_id: Number(lotId), picked_qty: c.required_qty }];
        }
        setPickStep((p) => ({ ...p, loading: true, error: null }));
        try {
            const res = await fetch(`/packaging/packing-steps/${pickStep.step.id}/start`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ picks }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { setPickStep((p) => ({ ...p, loading: false, error: data.message || __('Error') })); return; }
            setPickStep(null);
            loadPackingSteps();
        } catch {
            setPickStep((p) => ({ ...p, loading: false, error: __('Network error, try again.') }));
        }
    }, [pickStep]); // eslint-disable-line react-hooks/exhaustive-deps

    const loadPackingSteps = useCallback(async () => {
        try {
            const res = await fetch('/packaging/packing-steps', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) setPackingSteps((await res.json()).steps ?? []);
        } catch { /* keeps the last list */ }
    }, []);

    const loadCartons = useCallback(async () => {
        try {
            const res = await fetch('/packaging/cartons', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const list = (await res.json()).cartons ?? [];
            setCartons(list);
            setActiveCarton(list.find((c) => c.active_by_id === auth?.user?.id) ?? null);
        } catch { /* keeps the last list */ }
    }, [auth?.user?.id]);
    const loadCartonUnits = useCallback(async (carton) => {
        if (!carton) { setCartonUnits([]); return; }
        try {
            const res = await fetch(`/packaging/cartons/${carton.id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                const data = await res.json();
                setCartonUnits(data.units ?? []);
                setActiveCarton((c) => (c && c.id === carton.id ? { ...c, ...data.carton } : c));
            }
        } catch { /* keeps the last list */ }
    }, []);
    useEffect(() => { loadCartons(); }, [loadCartons]);
    useEffect(() => { loadCartonUnits(activeCarton); }, [activeCarton?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    const cartonCall = useCallback(async (url, body = {}) => {
        setCartonBusy(true);
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                setPsnResult({ success: false, error: data.message || __('Error'), scanned_at: formatTime(new Date()) });
                return null;
            }
            return data;
        } catch {
            setPsnResult({ success: false, error: __('Connection error'), scanned_at: formatTime(new Date()) });
            return null;
        } finally {
            setCartonBusy(false);
        }
    }, []);
    const openCarton = useCallback(async () => {
        const active = activePalletRef.current;
        const data = await cartonCall('/packaging/cartons', { work_order_id: active?.work_order_id ?? null, pallet_id: active?.id ?? null });
        if (data?.carton) { setActiveCarton(data.carton); loadCartons(); }
    }, [cartonCall, loadCartons]);
    // The bench's box by default; a scan that filled a box names that one (it may
    // be a box the server just opened for the unit's order).
    const closeCarton = useCallback(async (target) => {
        const box = target?.carton_no ? target : activeCarton;
        if (!box) return;
        const data = await cartonCall(`/packaging/cartons/${box.id}/close`);
        if (data?.carton) {
            showLabel({ unit: null, pdf: data.label_pdf, zpl: null, title: __('Carton label'), subtitle: data.carton.carton_no });
            setActiveCarton((current) => (current?.id === box.id ? null : current));
            loadCartons();
            onPalletClosedRef.current?.(data.pallet_closed);
        }
    }, [activeCarton, cartonCall, loadCartons, showLabel]);
    const putCartonOnPallet = useCallback(async () => {
        const active = activePalletRef.current;
        if (!activeCarton || !active) return;
        const data = await cartonCall(`/packaging/cartons/${activeCarton.id}/pallet`, { pallet_id: active.id });
        if (data?.carton) { setActiveCarton(data.carton); loadCartons(); }
    }, [activeCarton, cartonCall, loadCartons]);
    const [flash, setFlash] = useState(null); // 'success' | 'error' | null
    // The pallet and carton being filled are this operator's bench - state the
    // server holds (active_by), so a refresh, another device, or another
    // station's screen all agree on who is filling what.
    const [activePallet, setActivePallet] = useState(null); // { id, pallet_no, work_order_id, order_no, qty, active_by_id }
    const [openPallets, setOpenPallets] = useState([]); // all currently open pallets (persist across shifts)
    const [palletWoId, setPalletWoIdState] = useState(() => readParam('work_order') ?? ''); // selected work order for a new pallet, kept in the address
    const setPalletWoId = useCallback((v) => { setPalletWoIdState(v); writeParams({ work_order: v }); }, []);
    const [palletBatchId, setPalletBatchId] = useState(''); // selected batch (when the WO has several)
    const [palletBusy, setPalletBusy] = useState(false);
    const lastHistoryIdRef = useRef(0);
    const [manualCode, setManualCode] = useState('');
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
                <Badge key={ean} variant="neutral" className="mr-1 mb-0.5">{ean}</Badge>
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
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <ProgressBar value={row.original.progress} className="h-[5px] flex-1" color={row.original.done ? 'var(--color-om-running)' : undefined} />
                    <span className="w-8 text-right font-mono text-[11px] text-om-faint">{row.original.progress}%</span>
                </div>
            ),
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
                setPalletOrders(data.pallet_orders ?? data.items ?? []);
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
            // Mine is whichever open pallet the server says I am filling.
            const mine = list.find((p) => p.active_by_id === auth?.user?.id) ?? null;
            setActivePallet(mine);
        } catch {}
    }, [auth?.user?.id]);
    // A pallet that closed itself because it is full: the bench drops it, its label queues behind the current one.
    const onPalletClosed = useCallback((closed) => {
        if (!closed) return;
        setActivePallet(null);
        setPalletWoId('');
        fetchOpenPallets();
        showLabel({ unit: null, pdf: closed.label_pdf, zpl: closed.label_zpl, title: __('Pallet label'), subtitle: closed.message });
    }, [fetchOpenPallets, showLabel]);
    onPalletClosedRef.current = onPalletClosed;

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

    // Where the next scanned unit goes. 'auto' follows the bench (my carton,
    // else my pallet); an explicit target lets a unit be packed elsewhere -
    // topping up another pallet - without switching the bench.
    const scanDestination = () => {
        if (scanTarget.startsWith('carton:')) return { carton_id: Number(scanTarget.slice(7)), pallet_id: null };
        if (scanTarget.startsWith('pallet:')) return { carton_id: null, pallet_id: Number(scanTarget.slice(7)) };
        if (scanTarget === 'none') return { carton_id: null, pallet_id: null };
        return { carton_id: activeCarton?.id ?? null, pallet_id: activeCarton ? null : (activePalletRef.current?.id ?? null) };
    };
    const scanTargetOptions = () => [
        { value: 'auto', label: activeCarton ? __('My carton :carton', { carton: activeCarton.carton_no }) : activePallet ? __('My pallet :pallet', { pallet: activePallet.pallet_no }) : __('Pack only (no carton, no pallet)') },
        ...cartons.filter((c) => c.id !== activeCarton?.id).map((c) => ({ value: `carton:${c.id}`, label: `${__('Carton')} ${c.carton_no} · ${c.qty} ${__('pcs')}${c.active_by ? ` · ${c.active_by}` : ''}` })),
        ...openPallets.filter((p) => p.id !== activePallet?.id).map((p) => ({ value: `pallet:${p.id}`, label: `${__('Pallet')} ${p.pallet_no} · ${p.order_no} · ${p.qty} ${__('pcs')}${p.active_by ? ` · ${p.active_by}` : ''}` })),
        ...((activeCarton || activePallet) ? [{ value: 'none', label: __('Pack only (no carton, no pallet)') }] : []),
    ];
    // A target that closed (by its size, by hand, at another station) is no longer
    // offered: the bench goes back to "auto" instead of refusing every next scan.
    useEffect(() => {
        const gone = (scanTarget.startsWith('carton:') && !cartons.some((c) => `carton:${c.id}` === scanTarget))
            || (scanTarget.startsWith('pallet:') && !openPallets.some((p) => `pallet:${p.id}` === scanTarget));
        if (gone) setScanTarget('auto');
    }, [scanTarget, cartons, openPallets]);

    // The packing step of the scanned unit's order says how many go in a box.
    // Scan the process serial number to open the matching carton label for printing.
    const submitPsnLabel = useCallback(async (e) => {
        e?.preventDefault();
        const term = (weighing?.psn ?? psn).trim();
        if (!term || psnBusy) return;
        if (weighing && weight.trim() === '') { weightRef.current?.focus(); return; }
        setPsnBusy(true);
        try {
            const res = await fetch('/packaging/scan-unit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                // With the bench on "auto" and no box open, the server opens a carton
                // for an order that packs into cartons.
                // On "auto" the server may also swap the bench's box for one of the unit's own
                // order (the open carton belongs to another order) instead of refusing the unit.
                body: JSON.stringify({ psn: term, ...scanDestination(), auto_carton: scanTarget === 'auto', weight_g: weighing ? Number(weight.replace(',', '.')) : undefined }),
            });
            const data = await res.json();
            if (!res.ok && data.needs_weight) {
                setWeighing({ psn: term, expected: data.expected_g, tolerance: data.tolerance_g });
                setWeight('');
                setTimeout(() => weightRef.current?.focus(), 0);
            }
            if (res.ok) { setWeighing(null); setWeight(''); }
            if (!res.ok && data.needs_picks) {
                const step = packingSteps.find((st) => st.id === data.packing_step_id);
                if (step) openPicks(step);
            }
            if (res.ok) {
                setPsnResult({ success: true, alreadyPacked: data.already_packed === true, unit: data.unit, scanned_at: formatTime(new Date()) });
                setFlash('success');
                // A unit labelled upstream only needs the confirmation; the print
                // dialog shows when the label is still missing or the step prints one.
                if (data.label_needed !== false) showLabel({ unit: data.unit, pdf: data.label_pdf, zpl: data.label_zpl, title: __('Unit label'), subtitle: data.unit?.serial_no });
                onPalletClosed(data.pallet_closed);
                setPsn('');
                if (activeCarton) loadCartonUnits(activeCarton);
                loadCartons(); // picks up a carton the scan just opened for this bench
                fetchOpenPallets();
                loadPackingSteps();
                // A box that just reached its configured size closes itself and prints
                // its list; the server says so, whichever box the unit went into.
                if (data.carton_full && data.unit?.carton) {
                    const full = data.unit.carton;
                    setTimeout(() => closeCarton(full), 400);
                }
            } else {
                setPsnResult({ success: false, error: data.message, scanned_at: formatTime(new Date()) });
                setFlash('error');
            }
        } catch {
            setPsnResult({ success: false, error: __('Connection error'), scanned_at: formatTime(new Date()) });
            setFlash('error');
        } finally {
            setPsnBusy(false);
        }
        setTimeout(() => setFlash(null), 2000);
    }, [psn, psnBusy, weighing, weight, activeCarton, activePallet, scanTarget, cartons, openPallets, packingSteps, loadCartonUnits, loadCartons, fetchOpenPallets, loadPackingSteps, closeCarton, onPalletClosed, showLabel, openPicks]);

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
    const resumePallet = useCallback(async (pallet) => {
        try {
            const res = await fetch(`/packaging/pallets/${pallet.id}/activate`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => ({}));
            if (res.ok) { setActivePallet(data.pallet); fetchOpenPallets(); }
            else toast({ severity: 'error', title: data.message || __('Could not take the pallet') });
        } catch {
            toast({ severity: 'error', title: __('Connection error') });
        }
    }, [fetchOpenPallets, toast]);

    const releasePallet = useCallback(async () => {
        const pallet = activePalletRef.current;
        if (!pallet) return;
        try {
            await fetch(`/packaging/pallets/${pallet.id}/release`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } });
        } catch { /* best effort */ }
        setActivePallet(null);
        fetchOpenPallets();
    }, [fetchOpenPallets]);

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
            } else {
                // Closed meanwhile at another station, or refused: say so and refresh the list.
                const data = await res.json().catch(() => ({}));
                toast({ severity: 'error', title: data.message || __('Could not close the pallet') });
                fetchOpenPallets();
            }
        } catch {
            toast({ severity: 'error', title: __('Connection error') });
        } finally {
            setPalletBusy(false);
        }
    }, [palletBusy, fetchOpenPallets, toast]);

    useEffect(() => {
        Promise.all([fetchItems(), fetchHistory(), fetchStats(), fetchOpenPallets()]);
        const interval = setInterval(poll, 3000);
        // Refresh the open-pallets list on a slower cadence so a pallet opened on
        // another device/shift shows up here too.
        const palletInterval = setInterval(() => { fetchOpenPallets(); loadPackingSteps(); }, 5000);
        return () => {
            clearInterval(interval);
            clearInterval(palletInterval);
        };
    }, [fetchItems, fetchHistory, fetchStats, fetchOpenPallets, loadPackingSteps, poll]);

    // A reader in `hid` mode types into the page; in `manual` mode the operator
    // types into the field below, so the global listener would only get in the
    // way. Settings → System decides which.
    useScanBuffer(handleScan, { enabled: scannerMode !== 'manual' });

    const submitManualCode = useCallback((e) => {
        e.preventDefault();
        const code = manualCode.trim();
        if (! code) return;
        setManualCode('');
        handleScan(code);
    }, [manualCode, handleScan]);

    // Work order selected for a new pallet + its batches (one lookup, reused by
    // the batch picker and the create-button guard). A single batch auto-links
    // server-side, so the picker only shows when there are 2+.
    const palletWo = palletOrders.find((it) => String(it.id) === String(palletWoId));
    const palletBatches = palletWo?.batches ?? [];

    const completionTone = realizacja >= 100 ? 'text-om-running' : realizacja >= 50 ? 'text-om-downtime' : 'text-om-blocked';

    return (
        <>
            <Head title={__('Packing Station')} />
            <div className="mx-auto max-w-7xl p-6">
                {/* Header */}
                <div className="mb-5 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-[-0.02em] text-om-ink">
                            <Icon name="package" size={22} className="text-om-accent" />
                            {__('Packing Station')}
                        </h1>
                        {staff && <p className="mt-1 text-[13px] text-om-muted">
                            {__('Shift')}:{' '}
                            <span className="font-medium text-om-ink">
                                {currentShift ? `${currentShift.name} (${currentShift.start}–${currentShift.end})` : <ShiftLabel />}
                            </span>
                            {' · '}{__('Logged in')}: <span className="font-medium text-om-ink">{auth?.user?.name}</span>
                        </p>}
                    </div>
                    <StatusPill status={flash === 'error' ? 'blocked' : 'running'} label={flash === 'error' ? __('Scan error') : flash === 'success' ? __('Scanned!') : scannerMode === 'manual' ? __('Manual entry') : __('Scanning active')} />
                </div>

                {/* KPIs */}
                <div className="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Stat label={__('Packed (shift)')} value={stats.today_packed ?? '—'} />
                    <Stat label={__('Total plan')} value={stats.plan ?? '—'} tone="text-om-muted" />
                    <Stat label={__('Station backlog')} value={stats.backlog ?? '—'} tone={(stats.backlog ?? 0) > 0 ? 'text-om-blocked' : 'text-om-running'} />
                    <Stat label={__('Completion')} value={`${realizacja}%`} tone={completionTone}>
                        <ProgressBar value={realizacja} className="mt-3 h-[5px]" color={realizacja >= 100 ? 'var(--color-om-running)' : undefined} />
                    </Stat>
                </div>

                <div className={`mb-5 grid grid-cols-1 gap-5 ${usesEan ? 'lg:grid-cols-2' : ''}`}>
                    {/* Pallet: the one being filled, or the form to start one */}
                    <Card
                        title={__('Pallet')}
                        action={activePallet ? <StatusPill status="running" label={__('Pallet active')} /> : <Badge variant="neutral">{__(':count open', { count: openPallets.length })}</Badge>}
                        bodyClassName=""
                    >
                        {activePallet ? (
                            <div className="px-[22px] pb-5">
                                <div className="flex flex-wrap items-end justify-between gap-3">
                                    <div>
                                        <div className="font-mono text-[34px] leading-none font-semibold tracking-[-0.02em] text-om-ink">{activePallet.pallet_no}</div>
                                        <p className="mt-2 text-[13px] text-om-muted">
                                            <span className="font-mono font-medium text-om-ink">{activePallet.order_no}</span>
                                            {' · '}<span className="font-mono font-medium text-om-ink">{activePallet.qty ?? 0}</span> {__('pcs')}
                                            {activePallet.location ? ` · ${activePallet.location}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <LabelPrintMenu kind="pallet" id={activePallet.id} templates={labelTemplates} label={__('Label')} preferredTemplateId={activePallet.label_template_id ?? null} />
                                        <Button variant="outline" leftIcon={<Icon name="file-text" size={14} />} onClick={() => window.open(`/packaging/labels/pallet/${activePallet.id}/packing-list`, '_blank', 'noopener')} title={__('Every carton and serial number on the pallet, as a document')}>
                                            {__('Packing list')}
                                        </Button>
                                        <Button variant="ghost" onClick={releasePallet} disabled={palletBusy} title={__('Leave the pallet open for another station')}>
                                            {__('Put down')}
                                        </Button>
                                        <Button variant="primary" onClick={closePallet} loading={palletBusy} disabled={palletBusy} leftIcon={<Icon name="package-check" size={14} />}>
                                            {__('Close pallet')}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="px-[22px] pb-5">
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <div className={FIELD_LABEL}>{__('Create pallet for order')}</div>
                                            <Dropdown
                                                aria-label={__('Create pallet for order')}
                                                value={palletWoId == null ? '' : String(palletWoId)}
                                                onChange={(v) => { setPalletWoId(v); setPalletBatchId(''); }}
                                                placeholder={__('— Select order —')}
                                                options={palletOrders.map((it) => ({ value: String(it.id), label: `${it.order_no} — ${it.product}` }))}
                                                className="w-full"
                                            />
                                        </div>
                                        {palletBatches.length >= 2 && (
                                            <div>
                                                <div className={FIELD_LABEL}>{__('Batch')}</div>
                                                <Dropdown
                                                    aria-label={__('Batch')}
                                                    value={palletBatchId == null ? '' : String(palletBatchId)}
                                                    onChange={(v) => setPalletBatchId(v)}
                                                    placeholder={__('— Select batch —')}
                                                    options={palletBatches.map((b) => ({ value: String(b.id), label: b.label }))}
                                                    className="w-full"
                                                />
                                            </div>
                                        )}
                                    </div>
                                    <Button
                                        variant="primary"
                                        onClick={createPallet}
                                        loading={palletBusy}
                                        disabled={!palletWoId || palletBusy || (palletBatches.length >= 2 && !palletBatchId)}
                                        leftIcon={<Icon name="plus" size={14} />}
                                    >
                                        {__('Create pallet')}
                                    </Button>
                                </div>
                            </div>
                        )}

                        {/* Open pallets, grouped by line: resume one instead of starting another. */}
                        <div className="border-t border-om-line2">
                            {openPallets.length === 0 ? (
                                <p className="px-[22px] py-4 text-[12.5px] text-om-faint">{__('No open pallets — create one above')}</p>
                            ) : (
                                groupByLine(openPallets).map(([lineName, pallets]) => (
                                    <div key={lineName}>
                                        <div className="bg-om-bg px-[22px] py-1.5 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{lineName}</div>
                                        {pallets.map((p) => {
                                            const isActive = activePallet?.id === p.id;
                                            return (
                                                <div key={p.id} className={`flex items-center justify-between gap-3 border-t border-om-line2 px-[22px] py-2.5 ${isActive ? 'bg-om-selected' : ''}`}>
                                                    <div className="min-w-0 text-[13px]">
                                                        <span className="font-mono font-semibold text-om-ink">{p.pallet_no}</span>
                                                        <span className="text-om-muted"> · {p.order_no} · <span className="font-medium text-om-ink">{p.qty} {__('pcs')}</span>{p.location ? ` · ${p.location}` : ''}</span>
                                                    </div>
                                                    <span className="flex items-center gap-2">
                                                        {p.active_by && !isActive && (
                                                            <Badge variant="outline" title={p.active_workstation ?? undefined}>{__('Being filled by :name', { name: p.active_by })}</Badge>
                                                        )}
                                                        {isActive
                                                            ? <StatusPill status="running" label={__('Pallet active')} />
                                                            : <Button variant="outline" size="sm" onClick={() => resumePallet(p)}>{p.active_by ? __('Take over') : __('Resume')}</Button>}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ))
                            )}
                        </div>
                    </Card>

                    {/* Scanning: EAN wedge scans land here without a field; the last one stays on screen.
                        Shown when the plant packs by EAN at all - a serialised line has no use for it. */}
                    {usesEan && <Card title={__('Last scan')} action={<Badge variant="neutral">EAN</Badge>}>
                        {/* `manual` scanner mode (Settings → System): the operator types the EAN here. */}
                        {scannerMode === 'manual' && (
                            <form onSubmit={submitManualCode} className="mb-3 flex items-end gap-2">
                                <TextField
                                    mono
                                    label={__('Enter EAN code')}
                                    value={manualCode}
                                    onChange={setManualCode}
                                    placeholder={__('Enter EAN code')}
                                    autoFocus
                                    className="flex-1"
                                />
                                <Button type="submit" disabled={!manualCode.trim()}>{__('Confirm')}</Button>
                            </form>
                        )}
                        {!lastScan ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center text-om-faint">
                                <Icon name="scan-line" size={36} className="mb-2 opacity-40" />
                                <p className="text-[12.5px]">{__('Scan an EAN code…')}</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                <InlineAlert
                                    severity={lastScan.success ? 'success' : 'error'}
                                    title={lastScan.success ? lastScan.product : (lastScan.error || __('Scan error'))}
                                >
                                    <span className="font-mono">EAN {lastScan.ean}</span> · {lastScan.scanned_at}
                                </InlineAlert>
                                {lastScan.success && (
                                    <div className="flex items-center gap-3">
                                        <ProgressBar
                                            value={lastScan.progress ?? 0}
                                            className="h-2 flex-1"
                                            color={lastScan.progress >= 100 ? 'var(--color-om-running)' : undefined}
                                        />
                                        <span className="font-mono text-[13px] font-semibold text-om-ink">
                                            {lastScan.packed_qty} / {lastScan.planned_qty} {__('pcs')}
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}
                    </Card>}
                </div>

                {/* The routing's packing steps for this station: what each order packs
                    into what, and how far it is - packing is a step, not a side activity. */}
                {packingSteps.length > 0 && (
                    <div className="mb-5">
                        <Card title={__('Packing steps')} action={<Badge variant="neutral">{__(':count items', { count: packingSteps.length })}</Badge>} bodyClassName="">
                            <ul className="divide-y divide-om-line2">
                                {packingSteps.map((st) => {
                                    const cfg = st.config ?? {};
                                    const pct = st.target_qty > 0 ? Math.min(100, Math.round((st.passed_qty / st.target_qty) * 100)) : 0;
                                    return (
                                        <li key={st.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 px-[22px] py-3">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2 text-[13px]">
                                                    <span className="font-mono font-semibold text-om-ink">{st.order_no}</span>
                                                    <span className="text-om-muted">{st.product}</span>
                                                    <span className="text-om-faint">· {__('Step :n', { n: st.step_number })} {st.name}</span>
                                                    {st.workstation && <Badge variant="outline">{st.workstation}</Badge>}
                                                    <StatusPill status={st.status === 'IN_PROGRESS' ? 'running' : st.status === 'READY' ? 'pending' : 'pending'} pulse={st.status === 'IN_PROGRESS'} label={st.status === 'IN_PROGRESS' ? __('In Progress') : st.status === 'READY' ? __('Ready') : __('Pending')} />
                                                </div>
                                                <div className="mt-1 text-[11.5px] text-om-faint">
                                                    {[
                                                        cfg.unit === 'pallet' ? __('Packs onto pallets') : cfg.unit === 'unit' ? __('Labels single units') : __('Packs into cartons'),
                                                        cfg.carton_capacity ? __(':n per carton', { n: cfg.carton_capacity }) : null,
                                                        cfg.pallet_capacity ? __(':n cartons per pallet', { n: cfg.pallet_capacity }) : null,
                                                        cfg.weight_expected_g ? __('weight :expected g ± :tolerance g', { expected: cfg.weight_expected_g, tolerance: cfg.weight_tolerance_g ?? 0 }) : null,
                                                    ].filter(Boolean).join(' · ')}
                                                </div>
                                                {(st.materials?.length ?? 0) > 0 && (
                                                    <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                                        <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Packaging (BOM)')}</span>
                                                        {st.materials.map((m) => (
                                                            <Badge key={m.material_id} variant={m.status === 'consumed' ? 'neutral' : m.status === 'allocated' ? 'outline' : 'neutral'} title={`${m.code} · ${m.required_qty} ${m.unit_of_measure}`}>
                                                                {m.name} · {m.quantity_per_basis} {m.unit_of_measure} / {m.per === 'carton' ? __('carton') : m.per === 'pallet' ? __('pallet') : __('unit')}
                                                                {m.status === 'pending' ? ` · ${__('to pick')}` : ` · ${__('used')} ${m.consumed_qty} / ${m.allocated_qty}`}
                                                                {m.lots?.length > 0 && ` · ${__('lot')} ${m.lots.map((l) => l.lot_number).join(', ')}`}
                                                            </Badge>
                                                        ))}
                                                        {st.materials.some((m) => m.needs_pick) && (
                                                            <Button variant="primary" size="sm" leftIcon={<Icon name="package-open" size={13} />} onClick={() => openPicks(st)}>
                                                                {__('Pick packaging')}
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex w-56 items-center gap-3">
                                                <ProgressBar value={pct} className="h-[5px] flex-1" color={pct >= 100 ? 'var(--color-om-running)' : undefined} />
                                                <span className="font-mono text-[12px] text-om-ink">{st.passed_qty} / {st.target_qty}</span>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </Card>
                    </div>
                )}

                {/* Serialised units: scan the process serial, the unit goes into the
                    open carton (and onto the active pallet), and its label shows. */}
                <Card
                    title={__('Serial units - carton')}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <Dropdown
                                aria-label={__('Open carton')}
                                value={activeCarton ? String(activeCarton.id) : ''}
                                onChange={async (v) => {
                                    const chosen = cartons.find((c) => String(c.id) === v) ?? null;
                                    if (!chosen && activeCarton) await cartonCall(`/packaging/cartons/${activeCarton.id}/release`);
                                    if (chosen) await cartonCall(`/packaging/cartons/${chosen.id}/activate`);
                                    loadCartons();
                                }}
                                placeholder={__('No carton - units go straight to the pallet')}
                                options={[
                                    { value: '', label: __('No carton - units go straight to the pallet') },
                                    ...cartons.map((c) => ({ value: String(c.id), label: `${c.carton_no} · ${c.qty} ${__('pcs')}${c.order_no ? ` · ${c.order_no}` : ''}${c.active_by && c.active_by_id !== auth?.user?.id ? ` · ${c.active_by}` : ''}` })),
                                ]}
                                className="w-72"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                leftIcon={<Icon name="package-plus" size={14} />}
                                loading={cartonBusy}
                                disabled={cartonBusy || (activeCarton != null && cartonUnits.length === 0)}
                                title={activeCarton != null && cartonUnits.length === 0 ? __('Fill or close the open carton first.') : undefined}
                                onClick={openCarton}
                            >
                                {__('New carton')}
                            </Button>
                        </div>
                    }
                >
                    <form onSubmit={submitPsnLabel} className="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                        {weighing ? (
                            <TextField
                                ref={weightRef}
                                mono
                                inputMode="decimal"
                                label={__('Weight (g) · :psn', { psn: weighing.psn })}
                                placeholder={__(':expected g ± :tolerance g', { expected: weighing.expected, tolerance: weighing.tolerance })}
                                value={weight}
                                onChange={setWeight}
                                onKeyDown={(e) => { if (e.key === 'Escape') { setWeighing(null); setWeight(''); } }}
                                data-testid="weight-input"
                            />
                        ) : (
                            <TextField
                                mono
                                label={__('PSN or serial number')}
                                placeholder={__('Scan the PSN or the serial number…')}
                                value={psn}
                                onChange={setPsn}
                            />
                        )}
                        <div>
                            <div className={FIELD_LABEL}>{__('Pack into')}</div>
                            <Dropdown
                                aria-label={__('Pack into')}
                                value={scanTarget}
                                onChange={setScanTarget}
                                options={scanTargetOptions()}
                                className="w-full"
                            />
                        </div>
                        <Button variant="primary" type="submit" loading={psnBusy} disabled={psnBusy || (weighing ? !weight.trim() : !psn.trim())} leftIcon={<Icon name="package" size={14} />}>
                            {__('Pack unit')}
                        </Button>
                    </form>

                    {psnResult && (
                        <InlineAlert
                            // A rescan of a unit already in its box: nothing moved, so it
                            // reads as information, with when it was packed.
                            severity={!psnResult.success ? 'error' : psnResult.alreadyPacked ? 'info' : 'success'}
                            title={!psnResult.success ? psnResult.error : psnResult.alreadyPacked
                                ? __(':sn is already packed - nothing changed', { sn: psnResult.unit.serial_no })
                                : psnResult.unit.serial_no}
                            className="mt-3"
                        >
                            {psnResult.success && [
                                psnResult.unit.work_order?.order_no,
                                psnResult.unit.carton && `${psnResult.unit.carton.carton_no} (${psnResult.unit.carton.qty})`,
                                psnResult.unit.pallet_no,
                                psnResult.alreadyPacked && psnResult.unit.packed_at
                                    ? __('packed at :time', { time: psnResult.unit.packed_at })
                                    : psnResult.scanned_at,
                            ].filter(Boolean).join(' · ')}
                        </InlineAlert>
                    )}

                    {activeCarton && (
                        <div className="mt-4 border-t border-om-line2 pt-4">
                            <div className="flex flex-wrap items-center gap-2 text-[13px]">
                                <span className="font-mono text-[15px] font-semibold text-om-ink">{activeCarton.carton_no}</span>
                                <Badge variant="neutral">{__(':count pcs', { count: cartonUnits.length })}</Badge>
                                {activeCarton.pallet_no
                                    ? <Badge variant="outline">{__('Pallet')} {activeCarton.pallet_no}</Badge>
                                    : activePallet && (
                                        <Button variant="outline" size="sm" disabled={cartonBusy} onClick={putCartonOnPallet} leftIcon={<Icon name="layers" size={13} />}>
                                            {__('Put on pallet :pallet', { pallet: activePallet.pallet_no })}
                                        </Button>
                                    )}
                                <span className="ml-auto flex gap-2">
                                    <Button variant="outline" size="sm" leftIcon={<Icon name="printer" size={13} />} onClick={() => setCartonLabel({ unit: null, pdf: activeCarton.label_pdf, zpl: null, title: __('Carton label'), subtitle: activeCarton.carton_no })}>
                                        {__('Units list')}
                                    </Button>
                                    <Button variant="primary" size="sm" leftIcon={<Icon name="package-check" size={13} />} loading={cartonBusy} disabled={cartonBusy || cartonUnits.length === 0} onClick={closeCarton}>
                                        {__('Close carton & print')}
                                    </Button>
                                </span>
                            </div>
                            {cartonUnits.length > 0 && (
                                <ul className="mt-2.5 flex flex-wrap gap-1.5">
                                    {cartonUnits.map((u) => <li key={u.id}><Badge variant="neutral">{u.serial_no}</Badge></li>)}
                                </ul>
                            )}
                        </div>
                    )}

                    <p className="mt-3 text-[11.5px] text-om-faint">
                        {__('Scan the PSN or the serial number. The unit is packed into the open carton of its order (a unit of another order gets its own), or straight onto the active pallet, and its label is shown for printing. Closing the carton prints the list of units inside.')}
                    </p>
                    <Modal
                        open={pickStep != null}
                        onClose={() => setPickStep(null)}
                        title={__('Pick the lots')}
                        subtitle={pickStep ? `${pickStep.step.order_no} · ${__('Step :n', { n: pickStep.step.step_number })} ${pickStep.step.name}` : ''}
                        closeLabel={__('Close')}
                        footer={(
                            <div className="flex items-center justify-end gap-2">
                                <Button variant="secondary" onClick={() => setPickStep(null)}>{__('Cancel')}</Button>
                                <Button variant="primary" loading={!!pickStep?.loading} disabled={!!pickStep?.loading || !(pickStep?.candidates?.length)} onClick={confirmPicks}>{__('Start packing')}</Button>
                            </div>
                        )}
                    >
                        {pickStep && (
                            <div className="space-y-4">
                                {pickStep.error && <InlineAlert severity="error" title={pickStep.error} />}
                                {pickStep.candidates.length === 0 && !pickStep.loading && (
                                    <InlineAlert severity="warning" title={__('No lot with enough stock - receive the packaging first.')} />
                                )}
                                {pickStep.candidates.map((c) => (
                                    <div key={c.material_id}>
                                        <div className={FIELD_LABEL}>{c.material_name} · {c.required_qty} {c.unit_of_measure}</div>
                                        <Dropdown
                                            aria-label={c.material_name}
                                            value={pickStep.picks[c.material_id] ?? ''}
                                            onChange={(v) => setPickStep((p) => ({ ...p, picks: { ...p.picks, [c.material_id]: v } }))}
                                            placeholder={__('Lot')}
                                            options={(c.candidates ?? []).map((l) => ({ value: String(l.id), label: `${l.lot_number} · ${__('avail.')} ${l.quantity_available} ${c.unit_of_measure}` }))}
                                            className="w-full"
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </Modal>
                    <LabelPreviewModal
                        open={cartonLabel != null}
                        onClose={closeLabel}
                        title={cartonLabel?.title ?? __('Unit label')}
                        subtitle={cartonLabel?.subtitle ?? cartonLabel?.unit?.serial_no}
                        pdfUrl={cartonLabel?.pdf ?? null}
                        zplUrl={cartonLabel?.zpl ?? null}
                    />
                </Card>

                {usesEan && (
                <div className="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[3fr_2fr]">
                    <Card title={__('Orders to pack')} action={<Badge variant="neutral">{__(':count items', { count: items.length })}</Badge>} bodyClassName="">
                        <AppDataTable data={items} columns={itemColumns} searchable columnToggle paginated emptyLabel={__('No orders with assigned EAN codes')} />
                    </Card>
                    <Card title={__('Scan history (shift)')} bodyClassName="">
                        <AppDataTable data={history} columns={historyColumns} searchable={false} filterable={false} columnToggle={false} paginated emptyLabel={__('No scans this shift')} />
                    </Card>
                </div>
                )}
            </div>
        </>
    );
}

const FIELD_LABEL = 'mb-[7px] block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint';

/** A titled panel - the same shell the work-order page is made of. */
function Card({ title, action, children, bodyClassName = 'px-[22px] pb-5' }) {
    return (
        <section className="overflow-hidden rounded-om border border-om-line bg-om-card">
            <div className="flex flex-wrap items-center justify-between gap-3 px-[22px] pt-4 pb-3">
                <h2 className="text-[15px] font-semibold text-om-ink">{title}</h2>
                {action}
            </div>
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

/** One number the shift is measured by. */
function Stat({ label, value, tone = 'text-om-ink', children }) {
    return (
        <div className="rounded-om border border-om-line bg-om-card px-5 py-4">
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</div>
            <div className={`mt-1.5 font-mono text-[32px] leading-none font-semibold tracking-[-0.02em] ${tone}`}>{value}</div>
            {children}
        </div>
    );
}

// An operator works this screen from the shop-floor shell (line in the header,
// Queue / Workstation / Labels / Packing across the top); supervisors and
// admins come from the sidebar and keep it. Role decides, not the URL.
function StationShell({ children }) {
    const roles = usePage().props.auth?.user?.roles ?? [];
    const staff = roles.includes('Admin') || roles.includes('Supervisor');
    return staff ? <AppLayout>{children}</AppLayout> : <OperatorLayout>{children}</OperatorLayout>;
}

Station.layout = (page) => <StationShell>{page}</StationShell>;
