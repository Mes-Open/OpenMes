import { useState, useEffect, useCallback, useRef } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Button, Dropdown, Icon, Modal, SegmentedControl, StatusBadge, useToast } from '@openmes/ui';
import LabelPreviewModal from '../../../components/LabelPreviewModal';
import { BigButton, Chip, ResultSlot, ScanInput } from '../../../components/station/ScanKit';
import OperatorLayout from '../../../layouts/OperatorLayout';
import { __, formatDateTime } from '../../../lib/i18n';
import { readParam, writeParams } from '../../../lib/urlState';
import { serialStatusBadge, serialStatusLabel } from '../../../lib/serialStatus';

function csrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// Never throws: a dropped connection comes back as a refusal the screen can show.
async function post(url, body) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return { res, data: await res.json().catch(() => ({})) };
    } catch {
        return { res: { ok: false, status: 0 }, data: { message: __('Network error, try again.') } };
    }
}

/** A refusal in words: the validation errors, else the server's message, else the fallback. */
const refusal = (data, fallback) => (data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || fallback));

const UNIT_STATUSES = ['in_production', 'completed', 'blocked', 'scrapped', 'shipped'];
const BATCH_MAX = 500;

/**
 * The label station: the operator scans the unit's process serial, then the
 * serial number on the label that goes onto the unit, and the two are bound.
 * A wedge scanner sends Enter after each code, so Enter in the first field
 * moves to the second and Enter in the second submits - no clicking between
 * scans. What the identifiers must look like, and whether the process serial
 * is required at all, is configured in Settings, not here.
 *
 * The scans sit in a panel on the left, one numbered step per scan with the
 * step the scanner is filling outlined; the outcome of the last scan stays in
 * the slot under them. The line's recent units fill the right.
 */
export default function Station() {
    const { workOrders = [], materials = [], holdReasons = [], canUnblock = false, labelActions = ['start', 'issue', 'label', 'components', 'subassembly'], auth } = usePage().props;
    // What this bench does here: the first bench starts units, the labelling bench binds the ready label, ...
    const can = (action) => labelActions.includes(action);
    const canRebind = (auth?.user?.roles ?? []).some((r) => r === 'Supervisor' || r === 'Admin');
    const toast = useToast();

    // 'label': bind the unit's own serial to its process serial.
    // 'component': scan a part (sub-assembly serial, lot, vendor serial) into a unit.
    // 'subassembly': register a sub-assembly this order makes by its own serial.
    const [mode, setMode] = useState(() => (can('label') || can('start') || can('issue') ? 'label' : can('components') ? 'component' : 'subassembly'));
    const [subMaterialId, setSubMaterialId] = useState('');
    const [subSerial, setSubSerial] = useState('');
    const subSerialRef = useRef(null);
    const [materialId, setMaterialId] = useState('');
    const [identifier, setIdentifier] = useState('');
    const [unitNo, setUnitNo] = useState(''); // the unit the components go into
    const [components, setComponents] = useState({ unit: null, items: [] });
    const unitRef = useRef(null);
    const identifierRef = useRef(null);

    // Start on the order that is running: that is what the operator is
    // labelling, and the issued numbers come from its product's sequences.
    // A `?work_order=` in the address wins (the choice is written back there,
    // so a reload or a shared link keeps it); otherwise the running order.
    const [woId, setWoIdState] = useState(() => {
        const fromUrl = readParam('work_order');
        if (fromUrl != null && workOrders.some((wo) => String(wo.id) === fromUrl)) return fromUrl;
        if (fromUrl === '') return '';
        return String(workOrders.find((wo) => wo.status === 'IN_PROGRESS')?.id ?? '');
    });
    const setWoId = (v) => { setWoIdState(v); writeParams({ work_order: v }); setResult(null); };
    const selectedWo = workOrders.find((wo) => String(wo.id) === woId);
    // What the selected order makes that is registered by its own serial.
    const produces = selectedWo?.produces ?? [];
    const anyProduces = workOrders.some((wo) => (wo.produces ?? []).length > 0);
    const modeOptions = [
        (can('label') || can('start') || can('issue')) && { value: 'label', label: can('label') ? __('Serial label') : __('New units') },
        can('components') && { value: 'component', label: __('Components') },
        can('subassembly') && anyProduces && { value: 'subassembly', label: __('Sub-assemblies') },
    ].filter(Boolean);
    useEffect(() => {
        // One thing made: nothing to choose.
        setSubMaterialId(produces.length === 1 ? String(produces[0].id) : '');
    }, [woId]); // eslint-disable-line react-hooks/exhaustive-deps
    const [psn, setPsn] = useState('');
    const [serialNo, setSerialNo] = useState('');
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState(null); // { severity, title, body?, rebindable? }
    const [units, setUnits] = useState([]);
    const serialRef = useRef(null);
    const psnRef = useRef(null);
    // Which scan the scanner is filling: that step is outlined and says SCAN.
    const [focus, setFocus] = useState('psn');
    // This sitting's tally, so the operator sees the run of scans going through.
    const [tally, setTally] = useState({ ok: 0, err: 0 });

    // The outcome of a scan goes into the slot under the steps; a success or a
    // refusal counts towards the tally, an issued number only fills a field.
    const report = (severity, title, extra = {}) => {
        setResult({ severity, title, ...extra });
        if (severity === 'success') setTally((t) => ({ ...t, ok: t.ok + 1 }));
        if (severity === 'error') setTally((t) => ({ ...t, err: t.err + 1 }));
    };

    const fetchUnits = useCallback(async () => {
        try {
            const qs = woId ? `?work_order_id=${encodeURIComponent(woId)}` : '';
            const res = await fetch(`/operator/unit-labels/units${qs}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) setUnits((await res.json()).units ?? []);
        } catch {
            // Non-critical: the table keeps its last state.
        }
    }, [woId]);

    useEffect(() => { fetchUnits(); }, [fetchUnits]);

    const applyLabel = async (extra = {}) => {
        const sn = serialNo.trim();
        if (!sn) {
            report('error', __('Scan or type the serial number first.'));
            serialRef.current?.focus();
            return false;
        }
        setBusy(true);
        const { res, data } = await post('/operator/unit-labels/apply', { serial_no: sn, psn: psn.trim() || null, work_order_id: woId ? Number(woId) : null, ...extra });
        setBusy(false);
        if (res.ok) {
            report('success', data.message, {
                body: [data.unit?.serial_no, data.unit?.psn && `${__('Process serial (PSN)')} ${data.unit.psn}`, data.unit?.work_order].filter(Boolean).join(' · '),
            });
            setPsn('');
            setSerialNo('');
            psnRef.current?.focus();
        } else {
            // The service refuses a unit that already carries another process
            // serial; a supervisor can override that with a reason.
            report('error', refusal(data, __('Could not apply label.')), { rebindable: canRebind && res.status === 422 && !extra.force && data.rebindable === true });
        }
        fetchUnits();
        return res.ok;
    };

    const loadComponents = useCallback(async (sn) => {
        if (!sn) { setComponents({ unit: null, items: [] }); return; }
        try {
            const res = await fetch(`/operator/unit-labels/components?serial_no=${encodeURIComponent(sn)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                const data = await res.json();
                setComponents({ unit: data.unit, items: data.components ?? [] });
                // A scan that names no unit says so where the next result would be.
                if (!data.unit) report('error', __('No unit :sn.', { sn }));
            }
        } catch {
            // Non-critical.
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const bindComponent = async () => {
        const sn = unitNo.trim();
        const id = identifier.trim();
        if (!sn) { report('error', __('Scan or type the serial number first.')); unitRef.current?.focus(); return; }
        if (!id) { report('error', __('Scan the component identifier.')); identifierRef.current?.focus(); return; }
        setBusy(true);
        const { res, data } = await post('/operator/unit-labels/component', { serial_no: sn, identifier: id, material_id: materialId ? Number(materialId) : null });
        setBusy(false);
        if (res.ok) {
            report('success', data.message, { body: data.component?.material ? `${data.component.material.code} · ${data.component.material.name}` : null });
            // The unit stays: the next scan is its next component.
            setIdentifier('');
            identifierRef.current?.focus();
            loadComponents(sn);
        } else {
            report('error', refusal(data, __('Could not bind the component.')));
        }
    };

    const registerSubassembly = async () => {
        const sn = subSerial.trim();
        if (!woId) { report('error', __('Choose the work order that makes the sub-assembly.')); return; }
        if (!subMaterialId) { report('error', __('Choose what was made.')); return; }
        if (!sn) { report('error', __('Scan the serial number of the sub-assembly.')); subSerialRef.current?.focus(); return; }
        setBusy(true);
        const { res, data } = await post('/operator/unit-labels/subassembly', { work_order_id: Number(woId), material_id: Number(subMaterialId), serial_no: sn });
        setBusy(false);
        if (res.ok) {
            report('success', data.message);
            // Next piece: the same material, a new serial.
            setSubSerial('');
            subSerialRef.current?.focus();
            fetchUnits();
        } else {
            report('error', refusal(data, __('Could not register the sub-assembly.')));
        }
    };

    // The next number from the product's sequence, put into the field as a scan
    // would be - for lines that print their own labels instead of receiving them.
    const issue = async (purpose) => {
        setBusy(true);
        const { res, data } = await post('/operator/unit-labels/issue', { purpose, work_order_id: woId ? Number(woId) : null });
        setBusy(false);
        if (!res.ok) { report('error', data.message || __('Could not issue a number.')); return; }
        if (purpose === 'process_serial') {
            setPsn(data.identifier);
            (can('label') ? serialRef : psnRef).current?.focus();
        } else {
            setSerialNo(data.identifier);
            serialRef.current?.focus();
        }
        report('info', __('Issued :id', { id: data.identifier }));
    };

    // The line's first station: the unit starts on its process serial (the one
    // in the field, scanned off a label, or the next from the sequence) and its
    // PSN label opens for printing. The SN comes later, with the product label.
    const startUnit = async () => {
        if (!woId && !psn.trim()) { report('error', __('Select a work order first.')); return; }
        setBusy(true);
        const { res, data } = await post('/operator/unit-labels/start', { psn: psn.trim() || null, work_order_id: woId ? Number(woId) : null });
        setBusy(false);
        psnRef.current?.focus();
        if (!res.ok) { report('error', refusal(data, __('Could not start the unit.'))); return; }
        report('success', data.message, { body: data.unit?.work_order ?? null });
        setPsn('');
        setLabelFor(data.unit);
        fetchUnits();
    };

    // Row actions: print the unit's label, continue with its components, block, release or scrap it.
    const [labelFor, setLabelFor] = useState(null); // unit whose label is previewed
    const openComponents = (unit) => {
        const no = unit.serial_no ?? unit.psn;
        setMode('component');
        setUnitNo(no);
        setResult(null);
        loadComponents(no);
        setTimeout(() => identifierRef.current?.focus(), 0);
    };
    const unitName = (u) => u?.serial_no ?? u?.psn ?? '';

    // The dialogs that take a reason (release, scrap, re-bind, remove a component).
    // `run(reason)` resolves to an error message, or null once it went through.
    const [ask, setAsk] = useState(null); // { title, subtitle?, label, placeholder, confirmLabel, danger?, required, run, reason, error }
    const askFor = (opts) => setAsk({ required: true, label: __('Reason'), reason: '', error: null, ...opts });
    const confirmAsk = async () => {
        const reason = ask.reason.trim();
        if (ask.required && !reason) { setAsk((a) => ({ ...a, error: __('Enter a reason.') })); return; }
        setBusy(true);
        const error = await ask.run(reason);
        setBusy(false);
        if (error) setAsk((a) => ({ ...a, error }));
        else setAsk(null);
    };
    // A unit action from the dialog: the outcome is a toast, the list refreshes itself.
    const unitAction = async (url, body, severity) => {
        const { res, data } = await post(url, body);
        if (!res.ok) return refusal(data, __('Error'));
        toast({ severity, title: data.message || __('Saved') });
        if (data.unit && components.unit?.id === data.unit.id) setComponents((c) => ({ ...c, unit: data.unit }));
        fetchUnits();
        return null;
    };
    const scrapUnit = (unit) => askFor({
        title: __('Scrap unit :sn', { sn: unitName(unit) }), placeholder: __('Why is it scrapped?'), confirmLabel: __('Scrap it'), danger: true,
        run: (reason) => unitAction(`/operator/unit-labels/${unit.id}/scrap`, { reason }, 'warning'),
    });
    const unblockUnit = (unit) => askFor({
        title: __('Release unit :sn', { sn: unitName(unit) }), placeholder: __('Why is it released?'), confirmLabel: __('Release unit'),
        run: (note) => unitAction(`/operator/unit-labels/${unit.id}/unblock`, { note }, 'success'),
    });
    // A component comes out (repair, wrong scan): the reason stays on the unit's history.
    const unbindComponent = (c) => askFor({
        title: __('Remove component :id', { id: c.identifier }), label: __('Reason (optional)'), placeholder: __('Repair, wrong scan…'), confirmLabel: __('Remove'), danger: true, required: false,
        run: async (reason) => {
            const { res, data } = await post(`/operator/unit-labels/components/${c.id}/unbind`, { reason });
            if (!res.ok) return refusal(data, __('Error'));
            toast({ severity: 'warning', title: data.message || __('Saved') });
            setComponents({ unit: data.unit, items: data.components ?? [] });
            return null;
        },
    });
    const rebind = () => askFor({
        title: __('Re-bind process serial'), subtitle: `${serialNo.trim()} → ${psn.trim() || '—'}`, placeholder: __('Why is the label moved?'), confirmLabel: __('Re-bind'),
        // The outcome lands in the result slot, as for any label scan.
        run: async (reason) => { await applyLabel({ force: true, reason }); return null; },
    });

    // Hold a non-conforming unit with an error code; a supervisor releases it.
    const [blockFor, setBlockFor] = useState(null); // { unit, reasonId, note, error }
    const confirmBlock = async () => {
        if (!blockFor?.reasonId) { setBlockFor((b) => ({ ...b, error: __('Choose the error code.') })); return; }
        setBusy(true);
        const error = await unitAction(`/operator/unit-labels/${blockFor.unit.id}/block`, { scrap_reason_id: Number(blockFor.reasonId), note: blockFor.note || null }, 'warning');
        setBusy(false);
        if (error) setBlockFor((b) => ({ ...b, error }));
        else setBlockFor(null);
    };
    const blockUnit = (unit) => setBlockFor({ unit, reasonId: '', note: '', error: null });

    // Numbers ahead of the line: N units registered on the order, their labels in one file.
    // psnOnly: the units start on their process serial alone; the SN comes later.
    const [batchAsk, setBatchAsk] = useState(null); // { psnOnly, qty, error }
    const [batchLabel, setBatchLabel] = useState(null); // { count, pdf, zpl }
    const issueBatch = (psnOnly = false) => {
        if (!woId) { report('error', __('Select a work order first.')); return; }
        setBatchAsk({ psnOnly, qty: '10', error: null });
    };
    const confirmBatch = async () => {
        const quantity = parseInt(batchAsk.qty, 10) || 0;
        if (quantity < 1 || quantity > BATCH_MAX) { setBatchAsk((b) => ({ ...b, error: __('Between 1 and :max units.', { max: BATCH_MAX }) })); return; }
        setBusy(true);
        const { res, data } = await post('/operator/unit-labels/issue-batch', { work_order_id: Number(woId), quantity, psn_only: batchAsk.psnOnly });
        setBusy(false);
        if (!res.ok) { setBatchAsk((b) => ({ ...b, error: refusal(data, __('Could not issue a number.')) })); return; }
        setBatchAsk(null);
        toast({ severity: 'success', title: data.message });
        setBatchLabel({ count: data.count, pdf: data.label_pdf, zpl: data.label_zpl });
        fetchUnits();
    };
    const setBatchQty = (n) => setBatchAsk((b) => ({ ...b, qty: String(Math.min(BATCH_MAX, Math.max(1, n))), error: null }));

    // The recent units: narrowed by the search, then by the status chip.
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const q = query.trim().toLowerCase();
    const searched = q ? units.filter((u) => [u.serial_no, u.psn, u.work_order].filter(Boolean).join(' ').toLowerCase().includes(q)) : units;
    const shown = statusFilter === 'all' ? searched : searched.filter((u) => u.status === statusFilter);
    const filterChips = [
        { value: 'all', label: __('All'), count: searched.length },
        ...UNIT_STATUSES.map((s) => ({ value: s, label: serialStatusLabel(s), count: searched.filter((u) => u.status === s).length })),
    ];

    const switchMode = (m) => {
        setMode(m);
        setResult(null);
        const target = m === 'label' ? psnRef : m === 'component' ? unitRef : subSerialRef;
        setTimeout(() => target.current?.focus(), 0);
    };

    const startPrimary = !can('label') && can('start');
    const startSecondary = can('label') && can('start');

    return (
        <>
            <Head title={__('SN Label Station')} />
            <div className="flex h-full min-h-0 flex-col">
                {/* Station header: the screen's name, what it does here, and the order it works on. */}
                <div className="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 border-b border-om-line2 bg-om-card px-5 py-3">
                    <h1 className="m-0 whitespace-nowrap text-[19px] font-semibold tracking-[-0.015em] text-om-ink">{__('SN Label Station')}</h1>
                    {modeOptions.length > 1 && <SegmentedControl label={__('Station mode')} value={mode} onChange={switchMode} options={modeOptions} />}
                    <div className="flex-1" />
                    <div className="w-full sm:w-[330px]">
                        <Dropdown
                            aria-label={__('Work order')}
                            value={woId}
                            onChange={setWoId}
                            placeholder={__('All work orders')}
                            leftIcon={<span aria-hidden="true" className={`h-2 w-2 shrink-0 rounded-full ${selectedWo ? (selectedWo.status === 'IN_PROGRESS' ? 'bg-om-running' : 'bg-om-faint') : 'bg-om-faintest'}`} />}
                            options={[
                                { value: '', label: __('All work orders') },
                                ...workOrders.map((wo) => ({ value: String(wo.id), label: [wo.order_no, wo.product].filter(Boolean).join(' · ') })),
                            ]}
                        />
                    </div>
                </div>

                <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                    {/* LEFT: the scans (narrower on a landscape tablet, so the table keeps its actions in view) */}
                    <div className="flex shrink-0 flex-col gap-3.5 overflow-y-auto border-b border-om-line bg-om-card px-5 py-4 lg:w-[380px] lg:border-b-0 lg:border-r xl:w-[440px]">
                        {mode === 'label' && (
                            <>
                                <form className="flex flex-col gap-3" onSubmit={(e) => { e.preventDefault(); if (!busy) (can('label') ? applyLabel() : startUnit()); }}>
                                    <div className="flex items-baseline justify-between gap-3">
                                        <h2 className="m-0 text-[15px] font-semibold text-om-ink">{can('label') ? __('Bind serial label') : __('Start new units')}</h2>
                                        <span className="font-mono text-[11px] text-om-faint">{__(':ok applied · :err errors', { ok: tally.ok, err: tally.err })}</span>
                                    </div>

                                    <ScanStep n={1} htmlFor="om-psn" title={__('Process serial (PSN)')} active={focus === 'psn'} done={!!psn.trim()}>
                                        <div className="flex gap-2">
                                            <ScanInput
                                                id="om-psn"
                                                ref={psnRef}
                                                autoFocus
                                                value={psn}
                                                onChange={setPsn}
                                                onFocus={() => setFocus('psn')}
                                                placeholder={__('Scan the process serial…')}
                                                // Enter (the scanner's) moves on to the label, or starts the unit where nothing is labelled.
                                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); if (can('label')) serialRef.current?.focus(); else if (!busy) startUnit(); } }}
                                            />
                                            {can('issue') && <IssueButton disabled={busy} onClick={() => issue('process_serial')} title={__('Next number from the product\'s process serial sequence')} />}
                                        </div>
                                    </ScanStep>

                                    {can('label') && (
                                        <>
                                            <ScanStep n={2} htmlFor="om-sn" title={__('Serial number (label)')} active={focus === 'sn'} done={!!serialNo.trim()}>
                                                <div className="flex gap-2">
                                                    <ScanInput
                                                        id="om-sn"
                                                        ref={serialRef}
                                                        value={serialNo}
                                                        onChange={setSerialNo}
                                                        onFocus={() => setFocus('sn')}
                                                        placeholder={__('Scan the serial number label…')}
                                                    />
                                                    {can('issue') && <IssueButton disabled={busy} onClick={() => issue('unit_serial')} title={__('Next number from the product\'s unit serial sequence')} />}
                                                </div>
                                            </ScanStep>
                                            <BigButton type="submit" ready={!!serialNo.trim()} disabled={busy || !serialNo.trim()}>{__('Apply label')}</BigButton>
                                        </>
                                    )}
                                    {startPrimary && (
                                        <BigButton type="button" ready disabled={busy} onClick={startUnit} data-testid="start-unit" title={__('First station: the unit starts on its process serial (the one in the field, or the next from the sequence) and its PSN label prints; the serial number follows with the product label.')}>
                                            {__('Start unit on PSN')}
                                        </BigButton>
                                    )}

                                    <ResultSlot result={result}>
                                        {result?.rebindable && (
                                            <button type="button" onClick={rebind}
                                                className="mt-2 cursor-pointer rounded-lg border border-om-line bg-om-card px-[11px] py-[7px] text-[12px] font-semibold text-om-ink hover:bg-om-chip">
                                                {__('Re-bind process serial')}
                                            </button>
                                        )}
                                    </ResultSlot>
                                </form>

                                {(startSecondary || can('issue')) && (
                                    <div className="flex flex-wrap gap-2">
                                        {startSecondary && (
                                            <SideButton icon="plus" disabled={busy} onClick={startUnit} data-testid="start-unit" title={__('First station: the unit starts on its process serial (the one in the field, or the next from the sequence) and its PSN label prints; the serial number follows with the product label.')}>
                                                {__('Start unit on PSN')}
                                            </SideButton>
                                        )}
                                        {can('issue') && (
                                            <>
                                                <SideButton icon="layers" disabled={busy} onClick={() => issueBatch(false)} title={__('Numbers issued ahead of the line: the labels can be printed now and are applied by scanning them.')}>
                                                    {__('Issue batch…')}
                                                </SideButton>
                                                <SideButton icon="layers" disabled={busy} onClick={() => issueBatch(true)} title={__('Numbers issued ahead of the line: the labels can be printed now and are applied by scanning them.')}>
                                                    {__('Issue PSN batch…')}
                                                </SideButton>
                                            </>
                                        )}
                                    </div>
                                )}
                            </>
                        )}

                        {mode === 'component' && (
                            <form className="flex flex-col gap-3" onSubmit={(e) => { e.preventDefault(); if (!busy) bindComponent(); }}>
                                <h2 className="m-0 text-[15px] font-semibold text-om-ink">{__('Bind components')}</h2>
                                <ScanStep n={1} htmlFor="om-unit" title={__('Unit (SN or PSN)')} active={focus === 'unit'} done={!!components.unit}>
                                    <ScanInput
                                        id="om-unit"
                                        ref={unitRef}
                                        autoFocus
                                        value={unitNo}
                                        onChange={(v) => { setUnitNo(v); if (components.unit) setComponents({ unit: null, items: [] }); }}
                                        onFocus={() => setFocus('unit')}
                                        placeholder={__('Scan the unit…')}
                                        onBlur={() => { if (!components.unit) loadComponents(unitNo.trim()); }}
                                        // Enter moves on to the component; leaving the field loads the unit (once).
                                        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); identifierRef.current?.focus(); } }}
                                    />
                                    {components.unit && (
                                        <div className="flex items-center gap-[9px] rounded-[10px] border border-om-line2 bg-om-bg px-3 py-2.5">
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate font-mono text-[13px] font-semibold text-om-ink">{components.unit.serial_no ?? __('No serial number yet')}</div>
                                                <div className="truncate font-mono text-[10.5px] text-om-faint">{components.unit.psn || '—'}</div>
                                            </div>
                                            <StatusBadge size="sm" {...serialStatusBadge(components.unit.status)} />
                                            {components.unit.status !== 'blocked' && !['scrapped', 'shipped'].includes(components.unit.status) && holdReasons.length > 0 && (
                                                <RowAction icon="ban" label={__('Block unit')} danger onClick={() => blockUnit(components.unit)} />
                                            )}
                                        </div>
                                    )}
                                </ScanStep>
                                <ScanStep n={2} htmlFor="om-ident" title={__('Component')} active={focus === 'ident'} done={false}>
                                    {/* A handful of materials are chips; a long list is a searchable dropdown. */}
                                    {materials.length <= 8 ? (
                                        <div role="radiogroup" aria-label={__('Component material')} className="flex flex-wrap gap-1.5">
                                            {[{ id: '', label: __('From the identifier') }, ...materials.map((m) => ({ id: String(m.id), label: `${m.code} · ${m.name}` }))].map((m) => (
                                                <Chip key={m.id} on={materialId === m.id} onClick={() => { setMaterialId(m.id); identifierRef.current?.focus(); }}>{m.label}</Chip>
                                            ))}
                                        </div>
                                    ) : (
                                        <Dropdown
                                            aria-label={__('Component material')}
                                            value={materialId}
                                            onChange={setMaterialId}
                                            placeholder={__('From the identifier')}
                                            options={[
                                                { value: '', label: __('From the identifier') },
                                                ...materials.map((m) => ({ value: String(m.id), label: `${m.code} · ${m.name}` })),
                                            ]}
                                        />
                                    )}
                                    <ScanInput
                                        id="om-ident"
                                        ref={identifierRef}
                                        value={identifier}
                                        onChange={setIdentifier}
                                        onFocus={() => setFocus('ident')}
                                        placeholder={__('Scan the component…')}
                                    />
                                </ScanStep>
                                <BigButton type="submit" ready={!!unitNo.trim() && !!identifier.trim()} disabled={busy || !unitNo.trim() || !identifier.trim()}>{__('Bind component')}</BigButton>
                                <ResultSlot result={result} />

                                {components.unit && (
                                    <div className="border-t border-om-line2 pt-3">
                                        <div className="mb-[9px] flex justify-between">
                                            <span className="font-mono text-[9.5px] uppercase tracking-[0.1em] text-om-faint">{__('Bound components')}</span>
                                            <span className="font-mono text-[11px] text-om-muted">{__(':count components', { count: components.items.length })}</span>
                                        </div>
                                        <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
                                            {components.items.map((c) => (
                                                <li key={c.id} className="flex items-center gap-2.5 rounded-[9px] border border-om-line2 py-2 pl-3 pr-2" title={c.material ? `${c.material.code} · ${c.material.name}` : undefined}>
                                                    <span className="w-[62px] shrink-0 truncate font-mono text-[10.5px] text-om-faint">{c.material?.code ?? '—'}</span>
                                                    <span className="min-w-0 flex-1 truncate font-mono text-[12.5px] text-om-ink">{c.identifier}</span>
                                                    <button type="button" onClick={() => unbindComponent(c)} aria-label={__('Remove component :id', { id: c.identifier })} title={__('Remove')}
                                                        className="flex h-7 w-7 cursor-pointer items-center justify-center rounded-[7px] text-om-faint hover:bg-om-chip hover:text-om-blocked">
                                                        <Icon name="x" size={13} />
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </form>
                        )}

                        {mode === 'subassembly' && (
                            <form className="flex flex-col gap-3" onSubmit={(e) => { e.preventDefault(); if (!busy) registerSubassembly(); }}>
                                <h2 className="m-0 text-[15px] font-semibold text-om-ink">{__('Register sub-assembly')}</h2>
                                {produces.length === 0 ? (
                                    <div className="rounded-[10px] border border-om-line2 bg-om-bg px-3.5 py-[13px]">
                                        <div className="text-[13.5px] font-semibold text-om-ink">{__('This order makes no serial-tracked sub-assembly.')}</div>
                                        <div className="mt-[3px] text-[12.5px] text-om-muted">{__('Choose the work order of the sub-assembly station above.')}</div>
                                    </div>
                                ) : (
                                    <>
                                        <div>
                                            <div className="mb-2 text-[13px] font-semibold text-om-ink">{__('What was made')}</div>
                                            <div role="radiogroup" aria-label={__('What was made')} className="flex flex-wrap gap-1.5">
                                                {produces.map((m) => (
                                                    <Chip key={m.id} on={subMaterialId === String(m.id)} onClick={() => setSubMaterialId(String(m.id))}>{`${m.code} · ${m.name}`}</Chip>
                                                ))}
                                            </div>
                                        </div>
                                        <ScanStep htmlFor="om-sub" title={__('Serial number of the sub-assembly')} active>
                                            <ScanInput
                                                id="om-sub"
                                                ref={subSerialRef}
                                                autoFocus
                                                value={subSerial}
                                                onChange={setSubSerial}
                                                placeholder={__('Scan the sub-assembly…')}
                                            />
                                        </ScanStep>
                                        <BigButton type="submit" ready={!!subMaterialId && !!subSerial.trim()} disabled={busy || !subMaterialId || !subSerial.trim()}>{__('Register sub-assembly')}</BigButton>
                                        <ResultSlot result={result} />
                                    </>
                                )}
                            </form>
                        )}
                    </div>

                    {/* RIGHT: the line's recent units */}
                    <section aria-labelledby="recent-units" className="flex min-h-[420px] min-w-0 flex-1 flex-col overflow-hidden bg-om-card lg:min-h-0">
                        <div className="flex shrink-0 flex-wrap items-center gap-3 px-4 pb-2.5 pt-3.5">
                            <h2 id="recent-units" className="m-0 text-[15px] font-semibold text-om-ink">{__('Recent units')}</h2>
                            <span className="font-mono text-[10.5px] uppercase text-om-faint">{__('Last 50')}</span>
                            <div className="flex-1" />
                            <label className="flex w-full items-center gap-2 rounded-[9px] border border-om-line bg-om-bg px-[11px] sm:w-[230px]">
                                <Icon name="search" size={14} className="shrink-0 text-om-faint" />
                                <input
                                    type="search"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder={__('Search SN, PSN, order')}
                                    aria-label={__('Search SN, PSN, order')}
                                    className="h-9 min-w-0 flex-1 border-none bg-transparent font-sans text-[13px] text-om-ink outline-none placeholder:text-om-faintest"
                                />
                            </label>
                        </div>
                        <div role="group" aria-label={__('Status')} className="flex shrink-0 flex-wrap gap-1.5 border-b border-om-line2 px-4 pb-3">
                            {filterChips.map((f) => (
                                <button
                                    key={f.value}
                                    type="button"
                                    aria-pressed={statusFilter === f.value}
                                    onClick={() => setStatusFilter(f.value)}
                                    className={`inline-flex cursor-pointer items-center gap-[7px] whitespace-nowrap rounded-full px-[11px] py-1.5 text-[12.5px] font-medium ${statusFilter === f.value ? 'bg-om-ink text-om-on-ink' : 'border border-om-line2 text-om-muted hover:text-om-ink'}`}
                                >
                                    {f.label}
                                    <span className={`font-mono text-[10.5px] ${statusFilter === f.value ? 'text-om-on-ink opacity-70' : 'text-om-faint'}`}>{f.count}</span>
                                </button>
                            ))}
                        </div>
                        <UnitTable
                            units={shown}
                            can={can}
                            canBlock={holdReasons.length > 0}
                            canUnblock={canUnblock}
                            selectedId={mode === 'component' ? components.unit?.id : null}
                            onLabel={setLabelFor}
                            onComponents={openComponents}
                            onBlock={blockUnit}
                            onRelease={unblockUnit}
                            onScrap={scrapUnit}
                        />
                    </section>
                </div>
            </div>

            {/* Block: the error code is the reason a unit is held. */}
            <Modal
                open={blockFor != null}
                onClose={() => setBlockFor(null)}
                title={__('Block unit :sn', { sn: unitName(blockFor?.unit) })}
                subtitle={__('A held unit is not packed and fails its pallet’s quality gate until a supervisor releases it.')}
                closeLabel={__('Close')}
                footer={(
                    <>
                        <Button variant="secondary" onClick={() => setBlockFor(null)}>{__('Cancel')}</Button>
                        <Button variant="danger" onClick={confirmBlock} loading={busy} data-testid="confirm-block">{__('Block unit')}</Button>
                    </>
                )}
            >
                <div className="flex flex-col gap-3.5">
                    <div>
                        <div id="block-code" className="mb-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Error code')}</div>
                        <div role="radiogroup" aria-labelledby="block-code" className="flex flex-col gap-1.5">
                            {holdReasons.map((r) => {
                                const on = String(blockFor?.reasonId) === String(r.id);
                                return (
                                    <button
                                        key={r.id}
                                        type="button"
                                        role="radio"
                                        aria-checked={on}
                                        onClick={() => setBlockFor((b) => ({ ...b, reasonId: String(r.id), error: null }))}
                                        className={`flex cursor-pointer items-center gap-3 rounded-[10px] border px-[13px] py-3 text-left ${on ? 'border-om-blocked bg-om-blocked-bg' : 'border-om-line2 bg-om-card hover:border-om-line'}`}
                                    >
                                        <span aria-hidden="true" className={`h-[18px] w-[18px] shrink-0 rounded-full ${on ? 'border-[5px] border-om-blocked' : 'border-2 border-om-faintest'}`} />
                                        <span className="w-[84px] shrink-0 truncate font-mono text-[12px] font-semibold text-om-ink">{r.code}</span>
                                        <span className="min-w-0 text-[13px] text-om-muted">{r.name}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                    <ReasonField label={__('Note (optional)')} value={blockFor?.note ?? ''} onChange={(v) => setBlockFor((b) => ({ ...b, note: v }))} placeholder={__('What was found?')} />
                    {blockFor?.error && <ModalError>{blockFor.error}</ModalError>}
                </div>
            </Modal>

            {/* Release, scrap, re-bind, remove a component: a reason in words. */}
            <Modal
                open={ask != null}
                onClose={() => setAsk(null)}
                title={ask?.title ?? ''}
                subtitle={ask?.subtitle}
                closeLabel={__('Close')}
                footer={(
                    <>
                        <Button variant="secondary" onClick={() => setAsk(null)}>{__('Cancel')}</Button>
                        <Button variant={ask?.danger ? 'danger' : 'primary'} onClick={confirmAsk} loading={busy}>{ask?.confirmLabel}</Button>
                    </>
                )}
            >
                <div className="flex flex-col gap-3.5">
                    <ReasonField autoFocus label={ask?.label} value={ask?.reason ?? ''} onChange={(v) => setAsk((a) => ({ ...a, reason: v, error: null }))} placeholder={ask?.placeholder} />
                    {ask?.error && <ModalError>{ask.error}</ModalError>}
                </div>
            </Modal>

            {/* How many numbers to issue ahead of the line. */}
            <Modal
                open={batchAsk != null}
                onClose={() => setBatchAsk(null)}
                title={batchAsk?.psnOnly ? __('Issue a batch of process serials') : __('Issue a batch of numbers')}
                subtitle={selectedWo?.order_no}
                closeLabel={__('Close')}
                footer={(
                    <>
                        <Button variant="secondary" onClick={() => setBatchAsk(null)}>{__('Cancel')}</Button>
                        <Button variant="primary" onClick={confirmBatch} loading={busy}>{__('Issue and print')}</Button>
                    </>
                )}
            >
                <form className="flex flex-col gap-3.5" onSubmit={(e) => { e.preventDefault(); if (!busy) confirmBatch(); }}>
                    <div>
                        <label htmlFor="om-batch-qty" className="mb-2 block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('How many units?')}</label>
                        <div className="flex w-[260px] items-center overflow-hidden rounded-[11px] border border-om-line">
                            <button type="button" aria-label={__('Decrease')} onClick={() => setBatchQty((parseInt(batchAsk?.qty, 10) || 0) - 1)}
                                className="flex h-14 w-14 shrink-0 cursor-pointer select-none items-center justify-center bg-om-panel text-[24px] text-om-muted hover:text-om-ink">−</button>
                            <input
                                id="om-batch-qty"
                                autoFocus
                                inputMode="numeric"
                                value={batchAsk?.qty ?? ''}
                                onChange={(e) => setBatchAsk((b) => ({ ...b, qty: e.target.value.replace(/[^0-9]/g, '').slice(0, 3), error: null }))}
                                className="h-14 w-0 min-w-0 flex-1 border-none bg-om-card text-center font-mono text-[24px] text-om-ink outline-none"
                            />
                            <button type="button" aria-label={__('Increase')} onClick={() => setBatchQty((parseInt(batchAsk?.qty, 10) || 0) + 1)}
                                className="flex h-14 w-14 shrink-0 cursor-pointer select-none items-center justify-center bg-om-panel text-[24px] text-om-ink">+</button>
                        </div>
                        <div className="mt-2.5 font-mono text-[11.5px] text-om-muted">{__('Between 1 and :max units.', { max: BATCH_MAX })}</div>
                    </div>
                    {batchAsk?.error && <ModalError>{batchAsk.error}</ModalError>}
                </form>
            </Modal>

            <LabelPreviewModal
                open={labelFor != null}
                onClose={() => setLabelFor(null)}
                title={__('Unit label')}
                subtitle={unitName(labelFor)}
                pdfUrl={labelFor ? `/packaging/labels/serial-unit/${labelFor.id}/pdf` : null}
                zplUrl={labelFor ? `/packaging/labels/serial-unit/${labelFor.id}/zpl` : null}
            />
            <LabelPreviewModal
                open={batchLabel != null}
                onClose={() => setBatchLabel(null)}
                title={__('Unit labels (:count)', { count: batchLabel?.count ?? 0 })}
                subtitle={selectedWo?.order_no}
                pdfUrl={batchLabel?.pdf ?? null}
                zplUrl={batchLabel?.zpl ?? null}
            />
        </>
    );
}

Station.layout = (page) => <OperatorLayout flush>{page}</OperatorLayout>;

/**
 * One scan: a numbered card whose title labels the field. The step the scanner
 * is filling is outlined in the accent and says SCAN; a filled step it has
 * moved on from turns its number into a green tick.
 */
function ScanStep({ n, htmlFor, title, active = false, done = false, children }) {
    const ticked = done && !active;
    return (
        <div className={`flex flex-col gap-2.5 rounded-xl border-[1.5px] bg-om-card px-3.5 py-3 transition-shadow ${active ? 'border-om-accent shadow-[0_0_0_4px_var(--om-accent-bg)]' : 'border-om-line2'}`}>
            <div className="flex items-center gap-[9px]">
                {n != null && (
                    <span aria-hidden="true" className={`flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full font-mono text-[10.5px] font-bold ${ticked ? 'bg-om-running text-white' : active ? 'bg-om-accent text-white' : 'bg-om-chip text-om-faint'}`}>
                        {ticked ? '✓' : n}
                    </span>
                )}
                <label htmlFor={htmlFor} className="text-[13px] font-semibold text-om-ink">{title}</label>
                <span className="flex-1" />
                {active && n != null && (
                    <span className="flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-[0.06em] text-om-accent">
                        <span aria-hidden="true" className="h-1.5 w-1.5 animate-pulse rounded-full bg-om-accent" />
                        {__('Scan')}
                    </span>
                )}
            </div>
            {children}
        </div>
    );
}

function IssueButton({ onClick, disabled, title }) {
    return (
        <button type="button" onClick={onClick} disabled={disabled} title={title}
            className="flex h-14 shrink-0 cursor-pointer items-center rounded-[10px] border border-om-line bg-om-card px-[15px] text-[13px] font-medium text-om-ink hover:bg-om-chip disabled:cursor-not-allowed disabled:opacity-60">
            {__('Issue number')}
        </button>
    );
}

function SideButton({ icon, children, ...props }) {
    return (
        <button type="button"
            className="inline-flex cursor-pointer items-center gap-[7px] rounded-[9px] border border-om-line bg-om-card px-[13px] py-2.5 text-[13px] font-medium text-om-ink hover:bg-om-chip disabled:cursor-not-allowed disabled:opacity-60"
            {...props}>
            <Icon name={icon} size={14} />{children}
        </button>
    );
}

function RowAction({ icon, label, danger = false, onClick }) {
    return (
        <button type="button" onClick={onClick} aria-label={label} title={label}
            className={`flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg border bg-om-card ${danger ? 'border-om-blocked/30 text-om-blocked hover:bg-om-blocked-bg' : 'border-om-line text-om-muted hover:bg-om-chip hover:text-om-ink'}`}>
            <Icon name={icon} size={15} />
        </button>
    );
}

const UNIT_COLS = 'grid grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_124px_96px_144px] items-center gap-3';

/**
 * The line's last 50 units. The serial is the row's identity: clicking it (or
 * double-clicking the row) shows the label. A unit still waiting for its SN
 * says so, and is named by its PSN until the label goes on.
 */
function UnitTable({ units, can, canBlock, canUnblock, selectedId, onLabel, onComponents, onBlock, onRelease, onScrap }) {
    const head = 'font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint';
    return (
        <div role="table" aria-labelledby="recent-units" className="flex min-h-0 flex-1 flex-col overflow-x-auto">
            <div role="rowgroup" className="min-w-[600px] shrink-0">
                <div role="row" className={`${UNIT_COLS} border-b border-om-line2 bg-om-panel px-4 py-[9px] [scrollbar-gutter:stable]`}>
                    <span role="columnheader" className={head}>{__('Serial No')}</span>
                    <span role="columnheader" className={head}>{__('PSN · Order')}</span>
                    <span role="columnheader" className={head}>{__('Status')}</span>
                    <span role="columnheader" className={head}>{__('Applied At')}</span>
                    <span role="columnheader" className={`${head} text-right`}>{__('Actions')}</span>
                </div>
            </div>
            <div role="rowgroup" className="min-h-0 min-w-[600px] flex-1 overflow-y-auto [scrollbar-gutter:stable]">
                {units.map((u) => {
                    const live = !['scrapped', 'shipped'].includes(u.status);
                    return (
                        <div
                            key={u.id}
                            role="row"
                            onDoubleClick={() => onLabel(u)}
                            className={`${UNIT_COLS} border-b border-om-line2 px-4 py-[11px] ${selectedId === u.id ? 'bg-om-accent-bg' : ''} ${live ? '' : 'opacity-60'}`}
                        >
                            <div role="cell" className="min-w-0">
                                {u.serial_no
                                    ? <button type="button" onClick={() => onLabel(u)} title={u.serial_no} className="block max-w-full cursor-pointer truncate font-mono text-[13px] font-semibold text-om-accent hover:underline">{u.serial_no}</button>
                                    : <span className="text-[12px] text-om-faint">{__('No SN yet')}</span>}
                            </div>
                            <div role="cell" className="min-w-0">
                                <div className="truncate font-mono text-[12px] text-om-muted">{u.psn || '—'}</div>
                                <div className="truncate font-mono text-[10.5px] text-om-faint">{u.work_order || '—'}</div>
                            </div>
                            <div role="cell" className="min-w-0">
                                <StatusBadge size="sm" {...serialStatusBadge(u.status)} />
                                {/* A held unit says why: the error code, or the failed tests. */}
                                {u.hold && (
                                    <div className="mt-[3px] truncate text-[11px] text-om-blocked">
                                        {u.hold.source === 'manual' ? `${u.hold.reason_code} · ${u.hold.reason}` : __('Failed tests')}
                                    </div>
                                )}
                            </div>
                            <span role="cell" className="whitespace-nowrap font-mono text-[11px] text-om-faint">
                                {u.applied_at ? formatDateTime(u.applied_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'}
                            </span>
                            <div role="cell" className="flex justify-end gap-[5px]">
                                <RowAction icon="printer" label={__('Label')} onClick={() => onLabel(u)} />
                                {live && can('components') && <RowAction icon="cpu" label={__('Components')} onClick={() => onComponents(u)} />}
                                {live && u.status !== 'blocked' && canBlock && <RowAction icon="ban" label={__('Block unit')} danger onClick={() => onBlock(u)} />}
                                {u.status === 'blocked' && canUnblock && <RowAction icon="lock-open" label={__('Release unit')} onClick={() => onRelease(u)} />}
                                {live && <RowAction icon="trash-2" label={__('Scrap unit')} danger onClick={() => onScrap(u)} />}
                            </div>
                        </div>
                    );
                })}
                {units.length === 0 && <div className="p-10 text-center text-[13px] text-om-faint">{__('No units match.')}</div>}
            </div>
        </div>
    );
}

function ReasonField({ label, value, onChange, placeholder, autoFocus = false }) {
    return (
        <label className="block">
            <span className="mb-2 block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</span>
            <textarea
                rows={3}
                autoFocus={autoFocus}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className="w-full resize-none rounded-[10px] border border-om-line bg-om-bg px-[13px] py-[11px] font-sans text-[14px] text-om-ink outline-none placeholder:text-om-faintest focus:border-om-faint"
            />
        </label>
    );
}

function ModalError({ children }) {
    return <div role="alert" className="rounded-[9px] bg-om-blocked-bg px-3 py-2.5 text-[12.5px] font-medium text-om-blocked">{children}</div>;
}
