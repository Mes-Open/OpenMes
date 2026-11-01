import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Badge, Button, Dropdown, Icon, Modal, ProgressBar, StatusPill, Tabs, TextField, useToast } from '@openmes/ui';
import AppDataTable from '../../components/AppDataTable';
import AppLayout from '../../layouts/AppLayout';
import OperatorLayout from '../../layouts/OperatorLayout';
import LabelPreviewModal from '../../components/LabelPreviewModal';
import LabelPrintMenu from '../../components/LabelPrintMenu';
import { BigButton, Chip, ResultSlot, ScanInput } from '../../components/station/ScanKit';
import useScanBuffer from '../../lib/useScanBuffer';
import { __, formatTime } from '../../lib/i18n';
import { readParam, writeParams } from '../../lib/urlState';

function csrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

const XHR = { 'X-Requested-With': 'XMLHttpRequest' };

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

// A handful of orders or batches are chips; a long list is a searchable dropdown.
const CHIP_MAX = 6;
// Past this many slots a carton or pallet is drawn as a count only.
const SLOT_MAX = 40;

/**
 * The packing station. The bench sits on the left: the scan (the PSN or the
 * serial number, and the weight when the step weighs), where the unit goes,
 * the outcome of the last scan, and the operator's own carton and pallet. The
 * right holds what the station works on: the routing's packing steps with
 * their packaging, the open pallets anyone may take over, and, where the plant
 * still packs by EAN, the EAN orders and scans.
 */
export default function Station() {
    const { auth, labelTemplates = [], currentShift = null, packingSteps: initialPackingSteps = [], scannerMode = 'hid' } = usePage().props;
    // Staff get the shift and login line under the title; the operator shell
    // already names the line and the user in its header.
    const staff = (auth?.user?.roles ?? []).some((r) => r === 'Admin' || r === 'Supervisor');
    const toast = useToast();

    const [items, setItems] = useState([]);
    const [palletOrders, setPalletOrders] = useState([]); // every packable order, for the pallet picker
    const [history, setHistory] = useState([]);
    const [stats, setStats] = useState({ today_packed: 0, plan: 0, backlog: 0 });
    const [eanScanned, setEanScanned] = useState(false);
    // EAN packing (products without serial numbers): its tab shows only when it is in use.
    const usesEan = items.length > 0 || history.length > 0 || eanScanned;

    // The bench's scan, and the outcome of the last one (with this sitting's tally).
    const [psn, setPsn] = useState('');
    const [psnBusy, setPsnBusy] = useState(false);
    const [result, setResult] = useState(null); // { severity, title, body? }
    const [tally, setTally] = useState({ ok: 0, err: 0 });
    const [flash, setFlash] = useState(null); // 'success' | 'error' | null
    const flashTimer = useRef(null);
    const report = useCallback((severity, title, body = null) => {
        setResult({ severity, title, body });
        if (severity === 'success') setTally((t) => ({ ...t, ok: t.ok + 1 }));
        if (severity === 'error') setTally((t) => ({ ...t, err: t.err + 1 }));
        if (severity === 'success' || severity === 'error') {
            setFlash(severity);
            clearTimeout(flashTimer.current);
            flashTimer.current = setTimeout(() => setFlash(null), 2000);
        }
    }, []);
    useEffect(() => () => clearTimeout(flashTimer.current), []);
    const scanRef = useRef(null);
    const focusScan = () => setTimeout(() => scanRef.current?.focus(), 0);

    // Weight check: the step wants the unit weighed before it goes in the box.
    // The scan comes back asking for the weight; the scale (or the operator)
    // types it here and Enter sends the same scan again with it.
    const [weighing, setWeighing] = useState(null); // { psn, expected, tolerance }
    const [weight, setWeight] = useState('');
    const weightRef = useRef(null);
    const cancelWeighing = () => { setWeighing(null); setWeight(''); focusScan(); };

    // Labels arrive faster than they are printed: the unit's, then the carton it
    // just filled, then the pallet that carton filled. Each waits its turn
    // behind the one on screen, so none is lost.
    const [labels, setLabels] = useState([]);
    const showLabel = useCallback((label) => setLabels((queue) => [...queue, label]), []);
    const closeLabel = useCallback(() => setLabels((queue) => queue.slice(1)), []);
    const label = labels[0] ?? null;

    const [packingSteps, setPackingSteps] = useState(initialPackingSteps); // the routing's packing steps this station works on
    const [scanTarget, setScanTarget] = useState('auto'); // 'auto' | 'carton:<id>' | 'pallet:<id>' | 'none'
    const [cartons, setCartons] = useState([]); // open cartons
    const [activeCarton, setActiveCarton] = useState(null);
    const [cartonUnits, setCartonUnits] = useState([]);
    const [cartonBusy, setCartonBusy] = useState(false);
    const onPalletClosedRef = useRef(null); // closeCarton is declared before the pallet helpers; it reaches them through this

    // The pallet and carton being filled are this operator's bench - state the
    // server holds (active_by), so a refresh, another device, or another
    // station's screen all agree on who is filling what.
    const [activePallet, setActivePallet] = useState(null); // { id, pallet_no, work_order_id, order_no, qty, cartons_closed, active_by_id }
    const [openPallets, setOpenPallets] = useState([]); // all currently open pallets (persist across shifts)
    const [palletWoId, setPalletWoIdState] = useState(() => readParam('work_order') ?? ''); // selected work order for a new pallet, kept in the address
    const setPalletWoId = useCallback((v) => { setPalletWoIdState(v); writeParams({ work_order: v }); }, []);
    const [palletBatchId, setPalletBatchId] = useState(''); // selected batch (when the WO has several)
    const [palletBusy, setPalletBusy] = useState(false);
    const lastHistoryIdRef = useRef(0);
    const [manualCode, setManualCode] = useState('');
    const activePalletRef = useRef(null);
    useEffect(() => { activePalletRef.current = activePallet; }, [activePallet]);

    const [tab, setTab] = useState('steps');
    const [openStepId, setOpenStepId] = useState(null);

    // Packaging from the BOM: with lot tracking on, the lots are picked here
    // before the first unit goes into the box.
    const [pickStep, setPickStep] = useState(null); // { step, materials, candidates, picks: {material_id: lot_id}, error }
    const openPicks = useCallback(async (step) => {
        setTab('steps');
        setOpenStepId(step.id);
        setPickStep({ step, materials: step.materials ?? [], candidates: [], picks: {}, error: null, loading: true });
        try {
            const res = await fetch(`/packaging/packing-steps/${step.id}/materials`, { headers: XHR });
            const data = await res.json();
            const picks = {};
            (data.candidates ?? []).forEach((c) => { picks[c.material_id] = String(c.proposed?.[0]?.material_lot_id ?? c.candidates?.[0]?.id ?? ''); });
            setPickStep({ step, materials: data.materials ?? [], candidates: data.candidates ?? [], picks, error: null, loading: false });
        } catch {
            setPickStep((p) => (p ? { ...p, loading: false, error: __('Network error, try again.') } : p));
        }
    }, []);

    const loadPackingSteps = useCallback(async () => {
        try {
            const res = await fetch('/packaging/packing-steps', { headers: XHR });
            if (res.ok) setPackingSteps((await res.json()).steps ?? []);
        } catch { /* keeps the last list */ }
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...XHR },
                body: JSON.stringify({ picks }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { setPickStep((p) => ({ ...p, loading: false, error: data.message || __('Error') })); return; }
            setPickStep(null);
            toast({ severity: 'success', title: __('Packaging picked · packing started') });
            loadPackingSteps();
            focusScan();
        } catch {
            setPickStep((p) => ({ ...p, loading: false, error: __('Network error, try again.') }));
        }
    }, [pickStep, loadPackingSteps, toast]);

    const loadCartons = useCallback(async () => {
        try {
            const res = await fetch('/packaging/cartons', { headers: XHR });
            if (!res.ok) return;
            const list = (await res.json()).cartons ?? [];
            setCartons(list);
            setActiveCarton(list.find((c) => c.active_by_id === auth?.user?.id) ?? null);
        } catch { /* keeps the last list */ }
    }, [auth?.user?.id]);
    const loadCartonUnits = useCallback(async (carton) => {
        if (!carton) { setCartonUnits([]); return; }
        try {
            const res = await fetch(`/packaging/cartons/${carton.id}`, { headers: XHR });
            if (res.ok) {
                const data = await res.json();
                setCartonUnits(data.units ?? []);
                setActiveCarton((c) => (c && c.id === carton.id ? { ...c, ...data.carton } : c));
            }
        } catch { /* keeps the last list */ }
    }, []);
    useEffect(() => { loadCartons(); }, [loadCartons]);
    useEffect(() => { loadCartonUnits(activeCarton); }, [activeCarton?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    // A refusal lands in the result slot, where the operator is already looking.
    const cartonCall = useCallback(async (url, body = {}) => {
        setCartonBusy(true);
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...XHR },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                report('error', data.message || __('Error'));
                return null;
            }
            return data;
        } catch {
            report('error', __('Connection error'));
            return null;
        } finally {
            setCartonBusy(false);
        }
    }, [report]);
    const openCarton = useCallback(async () => {
        const active = activePalletRef.current;
        const data = await cartonCall('/packaging/cartons', { work_order_id: active?.work_order_id ?? null, pallet_id: active?.id ?? null });
        if (data?.carton) {
            setActiveCarton(data.carton);
            setScanTarget('auto');
            loadCartons();
            toast({ severity: 'success', title: data.message });
            focusScan();
        }
    }, [cartonCall, loadCartons, toast]);
    // The bench's box by default; a scan that filled a box names that one (it may
    // be a box the server just opened for the unit's order).
    const closeCarton = useCallback(async (target) => {
        const box = target?.carton_no ? target : activeCarton;
        if (!box) return;
        const data = await cartonCall(`/packaging/cartons/${box.id}/close`);
        if (data?.carton) {
            toast({ severity: 'success', title: data.message });
            showLabel({ pdf: data.label_pdf, zpl: null, title: __('Carton label'), subtitle: data.carton.carton_no });
            setActiveCarton((current) => (current?.id === box.id ? null : current));
            loadCartons();
            onPalletClosedRef.current?.(data.pallet_closed);
        }
    }, [activeCarton, cartonCall, loadCartons, showLabel, toast]);
    const putCartonOnPallet = useCallback(async () => {
        const active = activePalletRef.current;
        if (!activeCarton || !active) return;
        const data = await cartonCall(`/packaging/cartons/${activeCarton.id}/pallet`, { pallet_id: active.id });
        if (data?.carton) { setActiveCarton(data.carton); loadCartons(); toast({ severity: 'success', title: data.message }); }
    }, [activeCarton, cartonCall, loadCartons, toast]);
    const takeCarton = useCallback(async (id) => {
        const data = await cartonCall(`/packaging/cartons/${id}/activate`);
        if (data) { setScanTarget('auto'); loadCartons(); focusScan(); }
    }, [cartonCall, loadCartons]);
    const putCartonDown = useCallback(async () => {
        if (!activeCarton) return;
        const data = await cartonCall(`/packaging/cartons/${activeCarton.id}/release`);
        if (data) { toast({ severity: 'success', title: __(':no left open for another station', { no: activeCarton.carton_no }) }); loadCartons(); }
    }, [activeCarton, cartonCall, loadCartons, toast]);

    const realizacja = stats.plan > 0 ? Math.min(100, Math.round((stats.today_packed / stats.plan) * 100)) : 0;

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
            const res = await fetch('/packaging/items', { headers: XHR });
            if (res.ok) {
                const data = await res.json();
                setItems(data.items ?? []);
                setPalletOrders(data.pallet_orders ?? data.items ?? []);
            }
        } catch {}
    }, []);

    const fetchHistory = useCallback(async () => {
        try {
            const res = await fetch('/packaging/history', { headers: XHR });
            if (!res.ok) return;
            const hist = (await res.json()).history ?? [];
            setHistory(hist);
            if (hist.length > 0) lastHistoryIdRef.current = Math.max(...hist.map((h) => h.id));
        } catch {}
    }, []);

    const fetchStats = useCallback(async () => {
        try {
            const res = await fetch('/packaging/stats', { headers: XHR });
            if (res.ok) setStats(await res.json());
        } catch {}
    }, []);

    const fetchOpenPallets = useCallback(async () => {
        try {
            const res = await fetch('/packaging/pallets', { headers: XHR });
            if (!res.ok) return;
            const list = (await res.json()).pallets ?? [];
            setOpenPallets(list);
            // Mine is whichever open pallet the server says I am filling: a pallet
            // closed or taken over elsewhere drops off the bench here too.
            setActivePallet(list.find((p) => p.active_by_id === auth?.user?.id) ?? null);
        } catch {}
    }, [auth?.user?.id]);
    // A pallet that closed itself because it is full: the bench drops it, its label queues behind the current one.
    const onPalletClosed = useCallback((closed) => {
        if (!closed) return;
        setActivePallet(null);
        setPalletWoId('');
        fetchOpenPallets();
        toast({ severity: 'success', title: closed.message });
        showLabel({ pdf: closed.label_pdf, zpl: closed.label_zpl, title: __('Pallet label'), subtitle: closed.pallet_no });
    }, [fetchOpenPallets, setPalletWoId, showLabel, toast]);
    onPalletClosedRef.current = onPalletClosed;

    const poll = useCallback(async () => {
        try {
            const res = await fetch(`/packaging/history/poll?after_id=${lastHistoryIdRef.current}`, { headers: XHR });
            if (!res.ok) return;
            const newEntries = (await res.json()).history ?? [];
            if (newEntries.length > 0) {
                setHistory((prev) => [...newEntries, ...prev].slice(0, 100));
                lastHistoryIdRef.current = Math.max(lastHistoryIdRef.current, ...newEntries.map((h) => h.id));
                await Promise.all([fetchItems(), fetchStats()]);
            }
        } catch {}
    }, [fetchItems, fetchStats]);

    // EAN goods: a wedge scan lands here without a field (or from the manual
    // field on the EAN tab) and packs one piece onto the bench's pallet.
    const handleScan = useCallback(async (ean) => {
        setEanScanned(true);
        try {
            const res = await fetch('/packaging/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...XHR },
                body: JSON.stringify({ ean, pallet_id: activePalletRef.current?.id ?? null }),
            });
            const data = await res.json();
            if (res.ok) {
                const wo = data.work_order;
                if (data.pallet) setActivePallet((p) => (p && p.id === data.pallet.id ? { ...p, ...data.pallet } : p));
                report('success', wo.product, `EAN ${ean} · ${wo.packed_qty} / ${wo.planned_qty} ${__('pcs')} · ${formatTime(new Date())}`);
                await Promise.all([fetchItems(), fetchStats(), fetchOpenPallets()]);
                setHistory((prev) => [{ id: Date.now(), ean, product_name: wo.product, scanned_at: formatTime(new Date()) }, ...prev].slice(0, 100));
            } else {
                report('error', data.message || __('Scan error'), `EAN ${ean}`);
            }
        } catch {
            report('error', __('Connection error'), `EAN ${ean}`);
        }
    }, [fetchItems, fetchStats, fetchOpenPallets, report]);

    // Where the next scanned unit goes. 'auto' follows the bench (my carton,
    // else my pallet); an explicit target lets a unit be packed elsewhere -
    // topping up another pallet - without switching the bench.
    const scanDestination = () => {
        if (scanTarget.startsWith('carton:')) return { carton_id: Number(scanTarget.slice(7)), pallet_id: null };
        if (scanTarget.startsWith('pallet:')) return { carton_id: null, pallet_id: Number(scanTarget.slice(7)) };
        if (scanTarget === 'none') return { carton_id: null, pallet_id: null };
        return { carton_id: activeCarton?.id ?? null, pallet_id: activeCarton ? null : (activePalletRef.current?.id ?? null) };
    };
    const packOnly = __('Pack only (no carton, no pallet)');
    const targetOptions = [
        { value: 'auto', label: activeCarton ? __('My carton :carton', { carton: activeCarton.carton_no }) : activePallet ? __('My pallet :pallet', { pallet: activePallet.pallet_no }) : packOnly, kind: activeCarton ? 'carton' : activePallet ? 'pallet' : 'none' },
        ...cartons.filter((c) => c.id !== activeCarton?.id).map((c) => ({ value: `carton:${c.id}`, label: `${__('Carton')} ${c.carton_no} · ${c.qty} ${__('pcs')}${c.active_by ? ` · ${c.active_by}` : ''}`, kind: 'carton' })),
        ...openPallets.filter((p) => p.id !== activePallet?.id).map((p) => ({ value: `pallet:${p.id}`, label: `${__('Pallet')} ${p.pallet_no} · ${p.order_no} · ${p.qty} ${__('pcs')}${p.active_by ? ` · ${p.active_by}` : ''}`, kind: 'pallet' })),
        ...((activeCarton || activePallet) ? [{ value: 'none', label: packOnly, kind: 'none' }] : []),
    ];
    const target = targetOptions.find((o) => o.value === scanTarget) ?? targetOptions[0];
    // A target that closed (by its size, by hand, at another station) is no longer
    // offered: the bench goes back to "auto" instead of refusing every next scan.
    useEffect(() => {
        const gone = (scanTarget.startsWith('carton:') && !cartons.some((c) => `carton:${c.id}` === scanTarget))
            || (scanTarget.startsWith('pallet:') && !openPallets.some((p) => `pallet:${p.id}` === scanTarget));
        if (gone) setScanTarget('auto');
    }, [scanTarget, cartons, openPallets]);

    // Scan the process serial (or the serial number): the unit goes where the
    // bench says, and its label shows for printing.
    const submitPsnLabel = useCallback(async (e) => {
        e?.preventDefault();
        const term = (weighing?.psn ?? psn).trim();
        if (psnBusy) return;
        if (!term) { report('error', __('Scan the PSN or the serial number first.')); focusScan(); return; }
        if (weighing && weight.trim() === '') { weightRef.current?.focus(); return; }
        setPsnBusy(true);
        try {
            const res = await fetch('/packaging/scan-unit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...XHR },
                // With the bench on "auto" and no box open, the server opens a carton
                // for an order that packs into cartons; it may also swap the bench's
                // box for one of the unit's own order instead of refusing the unit.
                body: JSON.stringify({ psn: term, ...scanDestination(), auto_carton: scanTarget === 'auto', weight_g: weighing ? Number(weight.replace(',', '.')) : undefined }),
            });
            const data = await res.json();
            if (res.ok) {
                setWeighing(null);
                setWeight('');
                const unit = data.unit ?? {};
                if (data.already_packed === true) {
                    // A rescan of a unit already in its box: nothing moved, so it
                    // reads as information, with when it was packed.
                    report('info', __(':sn is already packed - nothing changed', { sn: unit.serial_no }), [
                        unit.carton?.carton_no, unit.pallet_no, unit.packed_at && __('packed at :time', { time: unit.packed_at }),
                    ].filter(Boolean).join(' · '));
                } else {
                    report('success', unit.serial_no ?? term, [
                        unit.work_order?.order_no,
                        unit.carton && `${unit.carton.carton_no} (${unit.carton.qty})`,
                        unit.pallet_no,
                        weighing && `${weight.replace(',', '.')} g`,
                        formatTime(new Date()),
                    ].filter(Boolean).join(' · '));
                }
                // A unit labelled upstream only needs the confirmation; the print
                // dialog shows when the label is still missing or the step prints one.
                if (data.label_needed !== false) showLabel({ pdf: data.label_pdf, zpl: data.label_zpl, title: __('Unit label'), subtitle: unit.serial_no });
                onPalletClosed(data.pallet_closed);
                setPsn('');
                if (activeCarton) loadCartonUnits(activeCarton);
                loadCartons(); // picks up a carton the scan just opened for this bench
                fetchOpenPallets();
                loadPackingSteps();
                // A box that just reached its configured size closes itself and prints
                // its list; the server says so, whichever box the unit went into.
                if (data.carton_full && unit.carton) {
                    const full = unit.carton;
                    setTimeout(() => closeCarton(full), 400);
                }
                focusScan();
            } else if (data.needs_weight) {
                setWeighing({ psn: term, expected: data.expected_g, tolerance: data.tolerance_g });
                setWeight('');
                setResult({ severity: 'info', title: data.message || __('Weigh the unit'), body: term });
                setTimeout(() => weightRef.current?.focus(), 0);
            } else {
                report('error', data.message || __('Error'), term);
                // The refused code stays readable but selected: the next scan replaces it.
                if (weighing) { setWeight(''); setTimeout(() => weightRef.current?.focus(), 0); } else setTimeout(() => scanRef.current?.select(), 0);
                if (data.needs_picks) {
                    const step = packingSteps.find((st) => st.id === data.packing_step_id);
                    if (step) openPicks(step);
                }
            }
        } catch {
            report('error', __('Connection error'), term);
        } finally {
            setPsnBusy(false);
        }
    }, [psn, psnBusy, weighing, weight, activeCarton, activePallet, scanTarget, cartons, openPallets, packingSteps, loadCartonUnits, loadCartons, fetchOpenPallets, loadPackingSteps, closeCarton, onPalletClosed, showLabel, openPicks, report]); // eslint-disable-line react-hooks/exhaustive-deps

    const createPallet = useCallback(async () => {
        if (!palletWoId || palletBusy) return;
        setPalletBusy(true);
        try {
            const res = await fetch('/packaging/pallets', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...XHR },
                body: JSON.stringify({
                    work_order_id: Number(palletWoId),
                    ...(palletBatchId ? { batch_id: Number(palletBatchId) } : {}),
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                setActivePallet(data.pallet);
                setPalletWoId('');
                setPalletBatchId('');
                fetchOpenPallets();
                toast({ severity: 'success', title: data.message });
                focusScan();
            } else {
                report('error', data.message || __('Error'));
            }
        } catch {
            report('error', __('Connection error'));
        } finally {
            setPalletBusy(false);
        }
    }, [palletWoId, palletBatchId, palletBusy, fetchOpenPallets, setPalletWoId, report, toast]);

    // Resume an already-open pallet (e.g. one started on a previous shift) so the
    // next scans keep filling it instead of creating a new pallet.
    const resumePallet = useCallback(async (pallet) => {
        try {
            const res = await fetch(`/packaging/pallets/${pallet.id}/activate`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), ...XHR } });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                setActivePallet(data.pallet);
                setScanTarget('auto');
                fetchOpenPallets();
                toast({ severity: 'success', title: pallet.active_by ? __('Taken over :no', { no: pallet.pallet_no }) : __('Resumed :no', { no: pallet.pallet_no }) });
                focusScan();
            } else toast({ severity: 'error', title: data.message || __('Could not take the pallet') });
        } catch {
            toast({ severity: 'error', title: __('Connection error') });
        }
    }, [fetchOpenPallets, toast]);

    const releasePallet = useCallback(async () => {
        const pallet = activePalletRef.current;
        if (!pallet) return;
        try {
            await fetch(`/packaging/pallets/${pallet.id}/release`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), ...XHR } });
            toast({ severity: 'success', title: __(':no left open for another station', { no: pallet.pallet_no }) });
        } catch { /* best effort */ }
        setActivePallet(null);
        fetchOpenPallets();
    }, [fetchOpenPallets, toast]);

    const closePallet = useCallback(async () => {
        const pallet = activePalletRef.current;
        if (!pallet || palletBusy) return;
        setPalletBusy(true);
        try {
            const res = await fetch(`/packaging/pallets/${pallet.id}/close`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), ...XHR } });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                setActivePallet(null);
                setPalletWoId('');
                fetchOpenPallets();
                toast({ severity: 'success', title: data.message || __('Pallet :no closed', { no: pallet.pallet_no }) });
            } else {
                // Closed meanwhile at another station, or refused: say so and refresh the list.
                toast({ severity: 'error', title: data.message || __('Could not close the pallet') });
                fetchOpenPallets();
            }
        } catch {
            toast({ severity: 'error', title: __('Connection error') });
        } finally {
            setPalletBusy(false);
        }
    }, [palletBusy, fetchOpenPallets, setPalletWoId, toast]);

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
    // types into the field on the EAN tab, so the global listener would only get
    // in the way. Settings → System decides which.
    useScanBuffer(handleScan, { enabled: scannerMode !== 'manual' });

    const submitManualCode = useCallback((e) => {
        e.preventDefault();
        const code = manualCode.trim();
        if (!code) return;
        setManualCode('');
        handleScan(code);
    }, [manualCode, handleScan]);

    // Work order selected for a new pallet + its batches. A single batch
    // auto-links server-side, so the picker only shows when there are 2+.
    const palletWo = palletOrders.find((it) => String(it.id) === String(palletWoId));
    const palletBatches = palletWo?.batches ?? [];
    const canCreatePallet = !!palletWoId && !palletBusy && (palletBatches.length < 2 || !!palletBatchId);

    // What the order's packing step says about its boxes and pallets.
    const configFor = (workOrderId) => packingSteps.find((st) => st.work_order_id === workOrderId)?.config ?? {};
    const cartonCap = activeCarton ? Number(configFor(activeCarton.work_order_id).carton_capacity) || 0 : 0;
    const palletCfg = activePallet ? configFor(activePallet.work_order_id) : {};
    const palletByUnits = palletCfg.unit === 'pallet';
    const palletCap = Number(palletCfg.pallet_capacity) || 0;
    const palletFilling = activePallet ? cartons.filter((c) => c.pallet_id === activePallet.id).length : 0;
    const resumableCartons = cartons.filter((c) => c.id !== activeCarton?.id);

    const completionTone = realizacja >= 100 ? 'text-om-running' : realizacja >= 50 ? 'text-om-downtime' : 'text-om-blocked';
    const completionColor = realizacja >= 100 ? 'var(--color-om-running)' : realizacja >= 50 ? 'var(--color-om-downtime)' : 'var(--color-om-blocked)';

    const tabs = [
        { value: 'steps', label: <TabLabel count={packingSteps.length}>{__('Packing steps')}</TabLabel> },
        { value: 'pallets', label: <TabLabel count={openPallets.length}>{__('Open pallets')}</TabLabel> },
        ...(usesEan ? [{ value: 'ean', label: <TabLabel count={items.length}>EAN</TabLabel> }] : []),
    ];

    return (
        <>
            <Head title={__('Packing Station')} />
            <div className="flex h-full min-h-0 flex-col">
                {/* Station header: the screen's name, the scanner, and the shift's numbers. */}
                <div className="flex shrink-0 flex-wrap items-center gap-x-7 gap-y-3 border-b border-om-line2 bg-om-card px-5 py-3">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 className="m-0 whitespace-nowrap text-[19px] font-semibold tracking-[-0.015em] text-om-ink">{__('Packing Station')}</h1>
                        <StatusPill
                            status={flash === 'error' ? 'blocked' : 'running'}
                            label={flash === 'error' ? __('Scan error') : flash === 'success' ? __('Scanned!') : scannerMode === 'manual' ? __('Manual entry') : __('Scanning active')}
                        />
                        {staff && (
                            <span className="text-[12.5px] text-om-muted">
                                {currentShift ? `${currentShift.name} (${currentShift.start}–${currentShift.end})` : <ShiftLabel />}
                                {' · '}{auth?.user?.name}
                            </span>
                        )}
                    </div>
                    <div className="flex-1" />
                    <div className="flex flex-wrap items-center gap-x-[26px] gap-y-2">
                        <Kpi label={__('Packed (shift)')} value={stats.today_packed ?? '—'} />
                        <Kpi label={__('Total plan')} value={stats.plan ?? '—'} tone="text-om-muted" />
                        <Kpi label={__('Station backlog')} value={stats.backlog ?? '—'} tone={(stats.backlog ?? 0) > 0 ? 'text-om-blocked' : 'text-om-running'} />
                        <div className="w-[150px]">
                            <div className="flex items-baseline justify-between">
                                <span className={KPI_LABEL}>{__('Completion')}</span>
                                <span className={`font-mono text-[15px] font-semibold ${completionTone}`}>{realizacja}%</span>
                            </div>
                            <ProgressBar value={realizacja} className="mt-[7px] h-[5px]" color={completionColor} />
                        </div>
                    </div>
                </div>

                <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                    {/* LEFT: the bench */}
                    <div className="flex shrink-0 flex-col gap-[18px] overflow-y-auto border-b border-om-line bg-om-card px-5 pt-4 pb-6 lg:w-[400px] lg:border-b-0 lg:border-r xl:w-[460px]">
                        {/* The scan */}
                        <form className="flex flex-col gap-2.5" onSubmit={submitPsnLabel}>
                            <div className="flex items-baseline justify-between gap-3">
                                <h2 className="m-0 text-[15px] font-semibold text-om-ink">{__('Pack a unit')}</h2>
                                <span className="font-mono text-[11px] text-om-faint">{__(':ok packed · :err errors', { ok: tally.ok, err: tally.err })}</span>
                            </div>
                            {weighing ? (
                                <div className="flex flex-col gap-2 rounded-xl border-[1.5px] border-om-downtime bg-om-downtime-bg px-3.5 py-3">
                                    <div className="flex items-center gap-2">
                                        <label htmlFor="om-weight" className="text-[13px] font-semibold text-om-ink">{__('Weigh the unit')}</label>
                                        <span className="min-w-0 truncate font-mono text-[11px] text-om-muted">{weighing.psn}</span>
                                        <span className="flex-1" />
                                        <button type="button" onClick={cancelWeighing} className="cursor-pointer text-[12px] font-medium text-om-muted hover:text-om-ink">{__('Cancel')}</button>
                                    </div>
                                    <div className="flex items-center gap-2.5">
                                        <ScanInput
                                            id="om-weight"
                                            ref={weightRef}
                                            inputMode="decimal"
                                            value={weight}
                                            onChange={(v) => setWeight(v.replace(/[^0-9.,]/g, ''))}
                                            onKeyDown={(e) => { if (e.key === 'Escape') cancelWeighing(); }}
                                            placeholder={__('Weight in grams')}
                                            aria-label={__('Weight (g) · :psn', { psn: weighing.psn })}
                                            data-testid="weight-input"
                                            className="bg-om-card text-[22px]"
                                        />
                                        <div className="shrink-0 text-right">
                                            <div className={KPI_LABEL}>{__('Expected')}</div>
                                            <div className="whitespace-nowrap font-mono text-[14px] font-semibold text-om-ink">{__(':expected g ± :tolerance g', { expected: weighing.expected, tolerance: weighing.tolerance })}</div>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col gap-1.5">
                                    <label htmlFor="om-scan" className={FIELD_LABEL}>{__('PSN or serial number')}</label>
                                    <ScanInput
                                        id="om-scan"
                                        ref={scanRef}
                                        autoFocus
                                        value={psn}
                                        onChange={setPsn}
                                        placeholder={__('Scan the PSN or the serial number…')}
                                        className="h-[60px] rounded-xl border-[1.5px] text-[20px] focus:border-om-accent focus:shadow-[0_0_0_4px_var(--om-accent-bg)]"
                                    />
                                </div>
                            )}
                            <div className="flex flex-col gap-1.5">
                                <span id="om-pack-into" className={FIELD_LABEL}>{__('Pack into')}</span>
                                <Dropdown
                                    aria-label={__('Pack into')}
                                    value={target.value}
                                    onChange={(v) => { setScanTarget(v); focusScan(); }}
                                    options={targetOptions.map(({ value, label: text }) => ({ value, label: text }))}
                                    leftIcon={<TargetIcon kind={target.kind} />}
                                    className="w-full"
                                />
                            </div>
                            <BigButton type="submit" ready={weighing ? !!weight.trim() : !!psn.trim()} disabled={psnBusy}>
                                {weighing ? __('Pack with this weight') : __('Pack unit')}
                            </BigButton>
                            <ResultSlot result={result} />
                        </form>

                        {/* My carton */}
                        <section aria-labelledby="my-carton" className="flex flex-col gap-2.5 border-t border-om-line2 pt-4">
                            <div className="flex items-center gap-2.5">
                                <Icon name="package" size={16} className="text-om-muted" />
                                <h2 id="my-carton" className="m-0 text-[15px] font-semibold text-om-ink">{__('My carton')}</h2>
                                <span className="flex-1" />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    leftIcon={<Icon name="plus" size={13} />}
                                    loading={cartonBusy}
                                    disabled={cartonBusy || (activeCarton != null && cartonUnits.length === 0)}
                                    title={activeCarton != null && cartonUnits.length === 0 ? __('Fill or close the open carton first.') : undefined}
                                    onClick={openCarton}
                                >
                                    {__('New carton')}
                                </Button>
                            </div>
                            {activeCarton ? (
                                <BenchCard>
                                    <div className="flex items-end gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-mono text-[20px] font-semibold tracking-[-0.01em] text-om-ink">{activeCarton.carton_no}</div>
                                            <div className="mt-0.5 text-[12px] text-om-muted">
                                                {[activeCarton.order_no, activeCarton.pallet_no ? __('on :pallet', { pallet: activeCarton.pallet_no }) : __('not on a pallet')].filter(Boolean).join(' · ')}
                                            </div>
                                        </div>
                                        <Count value={cartonUnits.length} of={cartonCap || null} />
                                    </div>
                                    {cartonCap > 0 && cartonCap <= SLOT_MAX && (
                                        <div aria-hidden="true" className="flex gap-[3px]">
                                            {Array.from({ length: cartonCap }, (_, i) => (
                                                <span key={i} className={`h-2 flex-1 rounded-[3px] ${i < cartonUnits.length ? 'bg-om-accent' : 'bg-om-chip'}`} />
                                            ))}
                                        </div>
                                    )}
                                    {cartonUnits.length > 0 && (
                                        <ul className="m-0 flex list-none flex-wrap gap-[5px] p-0">
                                            {cartonUnits.map((u) => (
                                                <li key={u.id} className="rounded-md border border-om-line2 bg-om-card px-[7px] py-[3px] font-mono text-[11px] text-om-ink">{u.serial_no ?? u.psn}</li>
                                            ))}
                                        </ul>
                                    )}
                                    <div className="flex flex-wrap gap-[7px]">
                                        {!activeCarton.pallet_no && activePallet && (
                                            <Button variant="outline" size="sm" disabled={cartonBusy} onClick={putCartonOnPallet} leftIcon={<Icon name="layers" size={13} />}>
                                                {__('Put on pallet :pallet', { pallet: activePallet.pallet_no })}
                                            </Button>
                                        )}
                                        <Button variant="outline" size="sm" leftIcon={<Icon name="printer" size={13} />} onClick={() => showLabel({ pdf: activeCarton.label_pdf, zpl: null, title: __('Carton label'), subtitle: activeCarton.carton_no })}>
                                            {__('Units list')}
                                        </Button>
                                        <Button variant="ghost" size="sm" className="border-transparent text-om-muted" disabled={cartonBusy} onClick={putCartonDown} title={__('Leave the carton open for another station')}>
                                            {__('Put down')}
                                        </Button>
                                        <span className="flex-1" />
                                        <Button variant="primary" size="sm" leftIcon={<Icon name="package-check" size={13} />} loading={cartonBusy} disabled={cartonBusy || cartonUnits.length === 0} onClick={() => closeCarton()}>
                                            {__('Close carton & print')}
                                        </Button>
                                    </div>
                                </BenchCard>
                            ) : (
                                <div className="flex flex-col gap-2.5 rounded-xl border-[1.5px] border-dashed border-om-line px-3.5 py-3.5">
                                    <span className="text-[12.5px] text-om-muted">{__('No carton - units go straight to the pallet')}</span>
                                    {resumableCartons.length > 0 && (
                                        <Dropdown
                                            aria-label={__('Open carton')}
                                            value=""
                                            onChange={(v) => v && takeCarton(v)}
                                            placeholder={__('Resume an open carton…')}
                                            options={resumableCartons.map((c) => ({ value: String(c.id), label: `${c.carton_no} · ${c.qty} ${__('pcs')}${c.order_no ? ` · ${c.order_no}` : ''}${c.active_by ? ` · ${c.active_by}` : ''}` }))}
                                            className="w-full"
                                        />
                                    )}
                                </div>
                            )}
                        </section>

                        {/* My pallet */}
                        <section aria-labelledby="my-pallet" className="flex flex-col gap-2.5 border-t border-om-line2 pt-4">
                            <div className="flex items-center gap-2.5">
                                <Icon name="layers" size={16} className="text-om-muted" />
                                <h2 id="my-pallet" className="m-0 text-[15px] font-semibold text-om-ink">{__('My pallet')}</h2>
                            </div>
                            {activePallet ? (
                                <BenchCard>
                                    <div className="flex items-end gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-mono text-[20px] font-semibold tracking-[-0.01em] text-om-ink">{activePallet.pallet_no}</div>
                                            <div className="mt-0.5 text-[12px] text-om-muted">{[activePallet.order_no, activePallet.location].filter(Boolean).join(' · ')}</div>
                                        </div>
                                        {palletByUnits ? (
                                            <Count value={activePallet.qty ?? 0} of={palletCap || null} unit={__('pcs')} />
                                        ) : (
                                            <div className="text-right">
                                                <Count value={activePallet.cartons_closed ?? 0} of={palletCap || null} unit={__('cartons')} />
                                                <div className="font-mono text-[11px] text-om-faint">{activePallet.qty ?? 0} {__('pcs')}</div>
                                            </div>
                                        )}
                                    </div>
                                    {palletCap > 0 && palletCap <= SLOT_MAX && (
                                        <div aria-hidden="true" className="flex gap-1">
                                            {Array.from({ length: palletCap }, (_, i) => {
                                                const done = palletByUnits ? activePallet.qty ?? 0 : activePallet.cartons_closed ?? 0;
                                                const filling = palletByUnits ? 0 : palletFilling;
                                                return (
                                                    <span key={i} className={`h-[22px] flex-1 rounded-[5px] ${i < done ? 'bg-om-running' : i < done + filling ? 'bg-om-accent-bg shadow-[inset_0_0_0_1.5px_var(--om-accent)]' : 'bg-om-chip'}`} />
                                                );
                                            })}
                                        </div>
                                    )}
                                    <div className="flex flex-wrap gap-[7px]">
                                        <LabelPrintMenu kind="pallet" id={activePallet.id} templates={labelTemplates} label={__('Label')} size="sm" preferredTemplateId={activePallet.label_template_id ?? null} />
                                        <Button variant="outline" size="sm" leftIcon={<Icon name="file-text" size={13} />} onClick={() => window.open(`/packaging/labels/pallet/${activePallet.id}/packing-list`, '_blank', 'noopener')} title={__('Every carton and serial number on the pallet, as a document')}>
                                            {__('Packing list')}
                                        </Button>
                                        <Button variant="ghost" size="sm" className="border-transparent text-om-muted" onClick={releasePallet} disabled={palletBusy} title={__('Leave the pallet open for another station')}>
                                            {__('Put down')}
                                        </Button>
                                        <span className="flex-1" />
                                        <Button variant="primary" size="sm" onClick={closePallet} loading={palletBusy} disabled={palletBusy} leftIcon={<Icon name="package-check" size={13} />}>
                                            {__('Close pallet')}
                                        </Button>
                                    </div>
                                </BenchCard>
                            ) : (
                                <BenchCard>
                                    <span id="pallet-order" className={FIELD_LABEL}>{__('Create pallet for order')}</span>
                                    {palletOrders.length === 0 ? (
                                        <span className="text-[12.5px] text-om-faint">{__('No order is ready to pack.')}</span>
                                    ) : palletOrders.length <= CHIP_MAX ? (
                                        <div role="radiogroup" aria-labelledby="pallet-order" className="flex flex-wrap gap-1.5">
                                            {palletOrders.map((o) => (
                                                <Chip key={o.id} on={String(palletWoId) === String(o.id)} onClick={() => { setPalletWoId(String(o.id)); setPalletBatchId(''); }}>
                                                    {o.order_no}
                                                </Chip>
                                            ))}
                                        </div>
                                    ) : (
                                        <Dropdown
                                            aria-label={__('Create pallet for order')}
                                            value={palletWoId == null ? '' : String(palletWoId)}
                                            onChange={(v) => { setPalletWoId(v); setPalletBatchId(''); }}
                                            placeholder={__('— Select order —')}
                                            options={palletOrders.map((it) => ({ value: String(it.id), label: `${it.order_no} — ${it.product}` }))}
                                            className="w-full"
                                        />
                                    )}
                                    {palletWo && palletOrders.length <= CHIP_MAX && <span className="-mt-1 text-[12px] text-om-muted">{palletWo.product}</span>}
                                    {palletBatches.length >= 2 && (
                                        <>
                                            <span id="pallet-batch" className={`${FIELD_LABEL} mt-0.5`}>{__('Batch')}</span>
                                            {palletBatches.length <= CHIP_MAX ? (
                                                <div role="radiogroup" aria-labelledby="pallet-batch" className="flex flex-wrap gap-1.5">
                                                    {palletBatches.map((b) => (
                                                        <Chip key={b.id} on={String(palletBatchId) === String(b.id)} onClick={() => setPalletBatchId(String(b.id))}>{b.label}</Chip>
                                                    ))}
                                                </div>
                                            ) : (
                                                <Dropdown
                                                    aria-label={__('Batch')}
                                                    value={palletBatchId == null ? '' : String(palletBatchId)}
                                                    onChange={(v) => setPalletBatchId(v)}
                                                    placeholder={__('— Select batch —')}
                                                    options={palletBatches.map((b) => ({ value: String(b.id), label: b.label }))}
                                                    className="w-full"
                                                />
                                            )}
                                        </>
                                    )}
                                    <Button variant="primary" className="w-full" onClick={createPallet} loading={palletBusy} disabled={!canCreatePallet} leftIcon={<Icon name="plus" size={14} />}>
                                        {__('Create pallet')}
                                    </Button>
                                </BenchCard>
                            )}
                        </section>
                    </div>

                    {/* RIGHT: what the station works on */}
                    <section aria-label={__('Station work')} className="flex min-h-[420px] min-w-0 flex-1 flex-col overflow-hidden bg-om-card lg:min-h-0">
                        <Tabs tabs={tabs} value={tab} onChange={setTab} label={__('Station work')} panels className="shrink-0 px-4 pt-1.5" />
                        <div id={`${tab}-panel`} role="tabpanel" aria-labelledby={`${tab}-tab`} className="min-h-0 flex-1 overflow-y-auto [scrollbar-gutter:stable]">
                            {tab === 'steps' && (
                                packingSteps.length === 0
                                    ? <Empty>{__('Nothing to pack at this station yet.')}</Empty>
                                    : packingSteps.map((st) => (
                                        <PackingStepRow
                                            key={st.id}
                                            step={st}
                                            open={openStepId === st.id}
                                            onToggle={() => setOpenStepId((id) => (id === st.id ? null : st.id))}
                                            onPick={() => openPicks(st)}
                                        />
                                    ))
                            )}

                            {tab === 'pallets' && (
                                openPallets.length === 0
                                    ? <Empty>{__('No open pallets — create one above')}</Empty>
                                    : groupByLine(openPallets).map(([lineName, pallets]) => (
                                        <div key={lineName}>
                                            <div className="border-b border-om-line2 bg-om-bg px-[18px] py-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{lineName}</div>
                                            {pallets.map((p) => (
                                                <OpenPalletRow key={p.id} pallet={p} mine={activePallet?.id === p.id} onTake={() => resumePallet(p)} />
                                            ))}
                                        </div>
                                    ))
                            )}

                            {tab === 'ean' && (
                                <div className="flex flex-col gap-5 p-4">
                                    {/* `manual` scanner mode (Settings → System): the operator types the EAN here. */}
                                    {scannerMode === 'manual' && (
                                        <form onSubmit={submitManualCode} className="flex items-end gap-2">
                                            <TextField mono label={__('Enter EAN code')} value={manualCode} onChange={setManualCode} placeholder={__('Enter EAN code')} className="flex-1" />
                                            <Button type="submit" disabled={!manualCode.trim()}>{__('Confirm')}</Button>
                                        </form>
                                    )}
                                    <div>
                                        <h3 className="mb-2 text-[13.5px] font-semibold text-om-ink">{__('Orders to pack')}</h3>
                                        <AppDataTable data={items} columns={itemColumns} searchable columnToggle paginated emptyLabel={__('No orders with assigned EAN codes')} />
                                    </div>
                                    <div>
                                        <h3 className="mb-2 text-[13.5px] font-semibold text-om-ink">{__('Scan history (shift)')}</h3>
                                        <AppDataTable data={history} columns={historyColumns} searchable={false} filterable={false} columnToggle={false} paginated emptyLabel={__('No scans this shift')} />
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>

            {/* The packaging lots, picked before the step's first unit goes into the box. */}
            <Modal
                open={pickStep != null}
                onClose={() => setPickStep(null)}
                title={__('Pick the lots')}
                subtitle={pickStep ? `${pickStep.step.order_no} · ${__('Step :n', { n: pickStep.step.step_number })} ${pickStep.step.name}` : ''}
                closeLabel={__('Close')}
                footer={(
                    <>
                        <Button variant="secondary" onClick={() => setPickStep(null)}>{__('Cancel')}</Button>
                        <Button variant="accent" loading={!!pickStep?.loading} disabled={!!pickStep?.loading || !(pickStep?.candidates?.length)} onClick={confirmPicks}>{__('Start packing')}</Button>
                    </>
                )}
            >
                {pickStep && (
                    <div className="flex flex-col gap-4">
                        {pickStep.candidates.length === 0 && !pickStep.loading && !pickStep.error && (
                            <ModalNote tone="warning">{__('No lot with enough stock - receive the packaging first.')}</ModalNote>
                        )}
                        {pickStep.candidates.map((c) => (
                            <div key={c.material_id}>
                                <div className="mb-[7px] flex items-baseline justify-between gap-3">
                                    <span id={`pick-${c.material_id}`} className="text-[13px] font-semibold text-om-ink">{c.material_name}</span>
                                    <span className="whitespace-nowrap font-mono text-[11px] text-om-muted">{c.required_qty} {c.unit_of_measure}</span>
                                </div>
                                <div role="radiogroup" aria-labelledby={`pick-${c.material_id}`} className="flex flex-col gap-[5px]">
                                    {(c.candidates ?? []).map((l) => {
                                        const on = String(pickStep.picks[c.material_id] ?? '') === String(l.id);
                                        return (
                                            <button
                                                key={l.id}
                                                type="button"
                                                role="radio"
                                                aria-checked={on}
                                                onClick={() => setPickStep((p) => ({ ...p, error: null, picks: { ...p.picks, [c.material_id]: String(l.id) } }))}
                                                className={`flex cursor-pointer items-center gap-[11px] rounded-[9px] border px-3 py-2.5 text-left ${on ? 'border-om-accent bg-om-accent-bg' : 'border-om-line2 bg-om-card hover:border-om-line'}`}
                                            >
                                                <span aria-hidden="true" className={`h-4 w-4 shrink-0 rounded-full ${on ? 'border-[5px] border-om-accent' : 'border-2 border-om-faintest'}`} />
                                                <span className="min-w-0 flex-1 truncate font-mono text-[12.5px] font-semibold text-om-ink">{l.lot_number}</span>
                                                <span className="whitespace-nowrap font-mono text-[11px] text-om-muted">{__('avail.')} {l.quantity_available} {c.unit_of_measure}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                        {pickStep.error && <ModalNote tone="error">{pickStep.error}</ModalNote>}
                    </div>
                )}
            </Modal>

            <LabelPreviewModal
                open={label != null}
                onClose={closeLabel}
                title={label?.title ?? __('Unit label')}
                subtitle={[label?.subtitle, labels.length > 1 ? __('+:count waiting', { count: labels.length - 1 }) : null].filter(Boolean).join(' · ')}
                pdfUrl={label?.pdf ?? null}
                zplUrl={label?.zpl ?? null}
            />
        </>
    );
}

const FIELD_LABEL = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint';
const KPI_LABEL = 'font-mono text-[9px] uppercase tracking-[0.08em] text-om-faint';

/** One number the shift is measured by. */
function Kpi({ label, value, tone = 'text-om-ink' }) {
    return (
        <div>
            <div className={KPI_LABEL}>{label}</div>
            <div className={`font-mono text-[21px] font-semibold leading-[1.15] ${tone}`}>{value}</div>
        </div>
    );
}

function TabLabel({ count, children }) {
    return (
        <span className="inline-flex items-center gap-2">
            <span>{children}</span>
            <span className="rounded-[10px] bg-om-chip px-[7px] py-px font-mono text-[10.5px] text-om-faint">{count}</span>
        </span>
    );
}

const TARGET_ICON = { carton: ['package', 'text-om-accent'], pallet: ['layers', 'text-om-running'], none: ['package-open', 'text-om-muted'] };
function TargetIcon({ kind }) {
    const [name, tone] = TARGET_ICON[kind] ?? TARGET_ICON.none;
    return <Icon name={name} size={15} className={tone} />;
}

/** The operator's carton or pallet. */
function BenchCard({ children }) {
    return <div className="flex flex-col gap-3 rounded-xl border border-om-line2 bg-om-bg p-3.5">{children}</div>;
}

/** "3 / 8": how full, against the capacity the packing step sets (when it sets one). */
function Count({ value, of = null, unit = null }) {
    return (
        <div className="whitespace-nowrap text-right">
            <span className="font-mono text-[22px] font-semibold text-om-ink">{value}</span>
            {(of != null || unit) && <span className="font-mono text-[13px] text-om-faint">{of != null ? ` / ${of}` : ''}{unit ? ` ${unit}` : ''}</span>}
        </div>
    );
}

function Empty({ children }) {
    return <div className="p-10 text-center text-[13px] text-om-faint">{children}</div>;
}

function ModalNote({ tone, children }) {
    const cls = tone === 'error' ? 'bg-om-blocked-bg text-om-blocked' : 'bg-om-downtime-bg text-om-downtime';
    return <div role={tone === 'error' ? 'alert' : undefined} className={`rounded-[9px] px-3 py-2.5 text-[12.5px] font-medium ${cls}`}>{children}</div>;
}

const PER_LABEL = { unit: 'Per unit', carton: 'Per carton', pallet: 'Per pallet' };

/**
 * One packing step of the routing: what the order packs into what, how far it
 * is, and - opened - the packaging its BOM books, per unit, carton and pallet.
 */
function PackingStepRow({ step, open, onToggle, onPick }) {
    const cfg = step.config ?? {};
    const run = step.status === 'IN_PROGRESS';
    const pct = step.target_qty > 0 ? Math.min(100, Math.round((step.passed_qty / step.target_qty) * 100)) : 0;
    const materials = step.materials ?? [];
    const toPick = materials.some((m) => m.needs_pick);
    const facts = [
        cfg.carton_capacity ? { text: __(':n per carton', { n: cfg.carton_capacity }) } : null,
        cfg.pallet_capacity ? { text: __(':n cartons per pallet', { n: cfg.pallet_capacity }) } : null,
        cfg.weight_expected_g ? { text: __('weight :expected g ± :tolerance g', { expected: cfg.weight_expected_g, tolerance: cfg.weight_tolerance_g ?? 0 }), warn: true } : null,
        toPick ? { text: __('to pick'), warn: true } : null,
    ].filter(Boolean);
    const groups = ['unit', 'carton', 'pallet']
        .map((per) => ({ per, items: materials.filter((m) => (m.per ?? 'unit') === per) }))
        .filter((g) => g.items.length > 0);
    const panelId = `packing-step-${step.id}`;

    return (
        <div className={`border-b border-om-line2 ${open ? 'bg-om-panel' : ''}`}>
            <button type="button" onClick={onToggle} aria-expanded={open} aria-controls={panelId}
                className="flex w-full cursor-pointer items-center gap-4 px-[18px] py-3.5 text-left">
                <Icon name="chevron-right" size={14} className={`shrink-0 text-om-faint transition-transform duration-150 ${open ? 'rotate-90' : ''}`} />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-[9px] gap-y-1">
                        <span className="font-mono text-[14px] font-semibold text-om-ink">{step.order_no}</span>
                        <span className="text-[13.5px] text-om-ink">{step.product}</span>
                        <StatusPill status={run ? 'running' : 'pending'} pulse={run} label={run ? __('In Progress') : step.status === 'READY' ? __('Ready') : __('Pending')} />
                    </div>
                    <div className="mt-[3px] text-[12px] text-om-muted">
                        {[
                            `${__('Step :n', { n: step.step_number })} ${step.name}`,
                            step.workstation,
                            cfg.unit === 'pallet' ? __('Packs onto pallets') : cfg.unit === 'unit' ? __('Labels single units') : __('Packs into cartons'),
                        ].filter(Boolean).join(' · ')}
                    </div>
                    {facts.length > 0 && (
                        <div className="mt-[7px] flex flex-wrap gap-[5px]">
                            {facts.map((f) => (
                                <span key={f.text} className={`whitespace-nowrap rounded-md px-2 py-[3px] font-mono text-[10.5px] ${f.warn ? 'bg-om-downtime-bg text-om-downtime' : 'bg-om-chip text-om-muted'}`}>{f.text}</span>
                            ))}
                        </div>
                    )}
                </div>
                <div className="w-[150px] shrink-0 text-right">
                    <div className="font-mono text-[18px] font-semibold text-om-ink">
                        {step.passed_qty}<span className="text-[12px] font-normal text-om-faint"> / {step.target_qty}</span>
                    </div>
                    <ProgressBar value={pct} className="mt-1.5 h-[5px]" color={pct >= 100 ? 'var(--color-om-running)' : undefined} />
                </div>
            </button>
            {open && (
                <div id={panelId} className="pb-4 pl-12 pr-[18px]">
                    <div className="mb-2.5 flex flex-wrap items-center gap-2.5">
                        <span className={FIELD_LABEL}>{__('Packaging (BOM)')}</span>
                        <span className="font-mono text-[11px] text-om-muted">
                            {materials.length === 0 ? __('No packaging in the BOM.') : toPick ? __(':count materials · not picked yet', { count: materials.length }) : __(':count materials', { count: materials.length })}
                        </span>
                        <span className="flex-1" />
                        {toPick && (
                            <Button variant="accent" size="sm" leftIcon={<Icon name="package-open" size={13} />} onClick={onPick}>{__('Pick packaging')}</Button>
                        )}
                    </div>
                    {groups.length > 0 && (
                        <div className="overflow-hidden rounded-[10px] border border-om-line2 bg-om-card">
                            {groups.map((g) => (
                                <div key={g.per}>
                                    <div className="border-b border-om-line2 bg-om-panel px-3 py-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__(PER_LABEL[g.per])}</div>
                                    {g.items.map((m) => {
                                        const used = m.allocated_qty > 0 ? Math.min(100, Math.round((m.consumed_qty / m.allocated_qty) * 100)) : 0;
                                        return (
                                            <div key={m.material_id} className="grid grid-cols-[minmax(0,1fr)_90px_170px] items-center gap-3 border-b border-om-line2 px-3 py-2 last:border-b-0">
                                                <div className="min-w-0" title={m.code}>
                                                    <div className="truncate text-[13px] text-om-ink">{m.name}</div>
                                                    {m.lots?.length > 0 && <div className="truncate font-mono text-[10px] text-om-faint">{__('LOT')} {m.lots.map((l) => l.lot_number).join(', ')}</div>}
                                                </div>
                                                <span className="font-mono text-[11.5px] text-om-muted">{m.quantity_per_basis} {m.unit_of_measure}</span>
                                                {m.status === 'pending' ? (
                                                    <span className="text-[11.5px] font-medium text-om-downtime">{m.needs_pick ? __('To pick') : __('Pending')}</span>
                                                ) : (
                                                    <div className="flex items-center gap-2">
                                                        <ProgressBar value={used} className="h-1 flex-1" color={used >= 100 ? 'var(--color-om-running)' : undefined} />
                                                        <span className="whitespace-nowrap font-mono text-[11px] text-om-ink">{m.consumed_qty} / {m.allocated_qty}</span>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function initials(name) {
    return (name ?? '').split(/\s+/).filter(Boolean).map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

/** An open pallet anyone may pick up: mine, someone else's (take over), or nobody's (resume). */
function OpenPalletRow({ pallet, mine, onTake }) {
    return (
        <div className={`flex flex-wrap items-center gap-x-3.5 gap-y-2 border-b border-om-line2 px-[18px] py-[13px] ${mine ? 'bg-om-accent-bg' : ''}`}>
            <div className="min-w-[190px] flex-1">
                <div className="flex flex-wrap items-baseline gap-x-2.5">
                    <span className="whitespace-nowrap font-mono text-[14px] font-semibold text-om-ink">{pallet.pallet_no}</span>
                    <span className="font-mono text-[12px] text-om-muted">{pallet.order_no}</span>
                </div>
                <div className="mt-0.5 text-[12px] text-om-muted">
                    {[
                        `${pallet.qty} ${__('pcs')}`,
                        __(':count cartons closed', { count: pallet.cartons_closed ?? 0 }),
                        pallet.batch_lot ? `${__('LOT')} ${pallet.batch_lot}` : null,
                        pallet.location,
                    ].filter(Boolean).join(' · ')}
                </div>
            </div>
            {mine ? (
                <StatusPill status="running" label={__('Mine')} />
            ) : (
                <>
                    {pallet.active_by && (
                        <span className="flex min-w-0 items-center gap-[7px] text-[12px] text-om-muted" title={pallet.active_workstation ?? undefined}>
                            <span aria-hidden="true" className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-om-chip text-[9px] font-semibold text-om-ink">{initials(pallet.active_by)}</span>
                            {__('Being filled by :name', { name: pallet.active_by })}
                        </span>
                    )}
                    <Button variant="outline" size="sm" onClick={onTake}>{pallet.active_by ? __('Take over') : __('Resume')}</Button>
                </>
            )}
        </div>
    );
}

// An operator works this screen from the shop-floor shell (line in the header,
// Queue / Workstation / Labels / Packing across the top); supervisors and
// admins come from the sidebar and keep it. Role decides, not the URL.
function StationShell({ children }) {
    const roles = usePage().props.auth?.user?.roles ?? [];
    const staff = roles.includes('Admin') || roles.includes('Supervisor');
    return staff ? <AppLayout>{children}</AppLayout> : <OperatorLayout flush>{children}</OperatorLayout>;
}

Station.layout = (page) => <StationShell>{page}</StationShell>;
