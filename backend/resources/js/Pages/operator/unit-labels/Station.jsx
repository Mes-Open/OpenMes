import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Badge, Button, Dropdown, Icon, InlineAlert, Modal, SegmentedControl, StatusBadge, TextField } from '@openmes/ui';
import AppDataTable from '../../../components/AppDataTable';
import usePrompt from '../../../components/usePrompt';
import LabelPreviewModal from '../../../components/LabelPreviewModal';
import OperatorLayout from '../../../layouts/OperatorLayout';
import { __, formatDateTime } from '../../../lib/i18n';
import { readParam, writeParams } from '../../../lib/urlState';
import { serialStatusBadge, serialStatusLabel } from '../../../lib/serialStatus';

function csrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

/**
 * The label station: the operator scans the unit's process serial, then the
 * serial number on the label that goes onto the unit, and the two are bound.
 * A wedge scanner sends Enter after each code, so Enter in the first field
 * moves to the second and Enter in the second submits - no clicking between
 * scans. What the identifiers must look like, and whether the process serial
 * is required at all, is configured in Settings, not here.
 */
export default function Station() {
    const { workOrders = [], materials = [], holdReasons = [], canUnblock = false, labelActions = ['start', 'issue', 'label', 'components', 'subassembly'], auth } = usePage().props;
    // What this bench does here: the first bench starts units, the labelling bench binds the ready label, ...
    const can = (action) => labelActions.includes(action);
    const canRebind = (auth?.user?.roles ?? []).some((r) => r === 'Supervisor' || r === 'Admin');

    // 'label': bind the unit's own serial to its process serial.
    // 'component': scan a part (sub-assembly serial, lot, vendor serial) into a unit.
    // 'subassembly': register a sub-assembly this order makes by its own serial.
    const [mode, setMode] = useState(() => (can('label') || can('start') || can('issue') ? 'label' : can('components') ? 'component' : 'subassembly'));
    const [subMaterialId, setSubMaterialId] = useState('');
    const [subSerial, setSubSerial] = useState('');
    const subSerialRef = useRef(null);
    const [materialId, setMaterialId] = useState('');
    const [identifier, setIdentifier] = useState('');
    const [components, setComponents] = useState({ unit: null, items: [] });
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
    const setWoId = (v) => { setWoIdState(v); writeParams({ work_order: v }); };
    // What the selected order makes that is registered by its own serial.
    const produces = workOrders.find((wo) => String(wo.id) === woId)?.produces ?? [];
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
    const { prompt, dialog: promptDialog } = usePrompt();

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

    const applyLabel = useCallback(async (extra = {}) => {
        const sn = serialNo.trim();
        if (!sn) {
            setResult({ severity: 'error', title: __('Scan or type the serial number first.') });
            serialRef.current?.focus();
            return;
        }
        setBusy(true);
        try {
            const res = await fetch('/operator/unit-labels/apply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ serial_no: sn, psn: psn.trim() || null, work_order_id: woId ? Number(woId) : null, ...extra }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                setResult({
                    severity: 'success',
                    title: data.message,
                    body: [data.unit?.serial_no, data.unit?.psn && `${__('Process serial (PSN)')} ${data.unit.psn}`, data.unit?.work_order].filter(Boolean).join(' · '),
                });
                setPsn('');
                setSerialNo('');
                psnRef.current?.focus();
            } else {
                const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || __('Could not apply label.'));
                // The service refuses a unit that already carries another process
                // serial; a supervisor can override that with a reason.
                setResult({ severity: 'error', title: msg, rebindable: canRebind && res.status === 422 && !extra.force && data.rebindable === true });
            }
            fetchUnits();
        } catch {
            setResult({ severity: 'error', title: __('Network error, try again.') });
        } finally {
            setBusy(false);
        }
    }, [psn, serialNo, woId, fetchUnits, canRebind]);

    const loadComponents = useCallback(async (sn) => {
        if (!sn) { setComponents({ unit: null, items: [] }); return; }
        try {
            const res = await fetch(`/operator/unit-labels/components?serial_no=${encodeURIComponent(sn)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                const data = await res.json();
                setComponents({ unit: data.unit, items: data.components ?? [] });
            }
        } catch {
            // Non-critical.
        }
    }, []);

    const bindComponent = useCallback(async () => {
        const sn = serialNo.trim();
        const id = identifier.trim();
        if (!sn) { setResult({ severity: 'error', title: __('Scan or type the serial number first.') }); serialRef.current?.focus(); return; }
        if (!id) { setResult({ severity: 'error', title: __('Scan the component identifier.') }); identifierRef.current?.focus(); return; }
        setBusy(true);
        try {
            const res = await fetch('/operator/unit-labels/component', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ serial_no: sn, identifier: id, material_id: materialId ? Number(materialId) : null }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                setResult({ severity: 'success', title: data.message, body: data.component?.material ? `${data.component.material.code} · ${data.component.material.name}` : null });
                // The unit stays: the next scan is its next component.
                setIdentifier('');
                identifierRef.current?.focus();
                loadComponents(sn);
            } else {
                const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || __('Could not bind the component.'));
                setResult({ severity: 'error', title: msg });
            }
        } catch {
            setResult({ severity: 'error', title: __('Network error, try again.') });
        } finally {
            setBusy(false);
        }
    }, [serialNo, identifier, materialId, loadComponents]);

    const registerSubassembly = useCallback(async () => {
        const sn = subSerial.trim();
        if (!woId) { setResult({ severity: 'error', title: __('Choose the work order that makes the sub-assembly.') }); return; }
        if (!subMaterialId) { setResult({ severity: 'error', title: __('Choose what was made.') }); return; }
        if (!sn) { setResult({ severity: 'error', title: __('Scan the serial number of the sub-assembly.') }); subSerialRef.current?.focus(); return; }
        setBusy(true);
        try {
            const res = await fetch('/operator/unit-labels/subassembly', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ work_order_id: Number(woId), material_id: Number(subMaterialId), serial_no: sn }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                setResult({ severity: 'success', title: data.message });
                // Next piece: the same material, a new serial.
                setSubSerial('');
                subSerialRef.current?.focus();
                fetchUnits();
            } else {
                const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || __('Could not register the sub-assembly.'));
                setResult({ severity: 'error', title: msg });
            }
        } catch {
            setResult({ severity: 'error', title: __('Network error, try again.') });
        } finally {
            setBusy(false);
        }
    }, [subSerial, subMaterialId, woId, fetchUnits]);

    // The next number from the product's sequence, put into the field as a scan
    // would be - for lines that print their own labels instead of receiving them.
    const issue = useCallback(async (purpose) => {
        setBusy(true);
        try {
            const res = await fetch('/operator/unit-labels/issue', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ purpose, work_order_id: woId ? Number(woId) : null }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                setResult({ severity: 'error', title: data.message || __('Could not issue a number.') });
                return;
            }
            if (purpose === 'process_serial') { setPsn(data.identifier); serialRef.current?.focus(); }
            else { setSerialNo(data.identifier); }
            setResult({ severity: 'info', title: __('Issued :id', { id: data.identifier }) });
        } catch {
            setResult({ severity: 'error', title: __('Network error, try again.') });
        } finally {
            setBusy(false);
        }
    }, [woId]);

    // The line's first station: the unit starts on its process serial (the one
    // in the field, scanned off a label, or the next from the sequence) and its
    // PSN label opens for printing. The SN comes later, with the product label.
    const startUnit = useCallback(async () => {
        if (!woId && !psn.trim()) { setResult({ severity: 'error', title: __('Select a work order first.') }); return; }
        setBusy(true);
        try {
            const res = await fetch('/operator/unit-labels/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ psn: psn.trim() || null, work_order_id: woId ? Number(woId) : null }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                setResult({ severity: 'error', title: data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || __('Could not start the unit.')) });
                return;
            }
            setResult({ severity: 'success', title: data.message });
            setPsn('');
            setLabelFor(data.unit);
            fetchUnits();
        } catch {
            setResult({ severity: 'error', title: __('Network error, try again.') });
        } finally {
            setBusy(false);
            psnRef.current?.focus();
        }
    }, [psn, woId, fetchUnits]);

    // Row actions: print the unit's label, continue with its components, or scrap it.
    const [labelFor, setLabelFor] = useState(null); // unit whose label is previewed
    const printLabel = (unit) => setLabelFor(unit);
    const openComponents = (unit) => {
        setMode('component');
        setSerialNo(unit.serial_no ?? unit.psn);
        setResult(null);
        loadComponents(unit.serial_no ?? unit.psn);
        setTimeout(() => identifierRef.current?.focus(), 0);
    };
    // Never throws: a dropped connection comes back as a refusal the screen can show.
    const post = async (url, body) => {
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body),
            });
            return { res, data: await res.json().catch(() => ({})) };
        } catch {
            return { res: { ok: false }, data: { message: __('Connection error') } };
        }
    };
    const scrapUnit = (unit) => prompt(
        { title: __('Scrap unit :sn', { sn: unit.serial_no ?? unit.psn }), label: __('Reason'), confirmLabel: __('Scrap it') },
        async (reason) => {
            const { res, data } = await post(`/operator/unit-labels/${unit.id}/scrap`, { reason });
            setResult({ severity: res.ok ? 'warning' : 'error', title: data.message || (res.ok ? __('Saved') : __('Error')) });
            fetchUnits();
        },
    );

    // Hold a non-conforming unit with an error code; a supervisor releases it.
    const [blockFor, setBlockFor] = useState(null); // { unit, reasonId, note, error }
    const confirmBlock = async () => {
        if (!blockFor?.reasonId) { setBlockFor((b) => ({ ...b, error: __('Choose the error code.') })); return; }
        const { res, data } = await post(`/operator/unit-labels/${blockFor.unit.id}/block`, { scrap_reason_id: Number(blockFor.reasonId), note: blockFor.note || null });
        if (!res.ok) { setBlockFor((b) => ({ ...b, error: data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || __('Error')) })); return; }
        setBlockFor(null);
        setResult({ severity: 'warning', title: data.message });
        if (components.unit?.id === data.unit?.id) setComponents((c) => ({ ...c, unit: data.unit }));
        fetchUnits();
    };
    const unblockUnit = (unit) => prompt(
        { title: __('Release unit :sn', { sn: unit.serial_no ?? unit.psn }), label: __('Reason'), confirmLabel: __('Release unit') },
        async (note) => {
            const { res, data } = await post(`/operator/unit-labels/${unit.id}/unblock`, { note });
            setResult({ severity: res.ok ? 'success' : 'error', title: data.message || (res.ok ? __('Saved') : __('Error')) });
            if (res.ok && components.unit?.id === data.unit?.id) setComponents((c) => ({ ...c, unit: data.unit }));
            fetchUnits();
        },
    );

    // A component comes out (repair, wrong scan): the reason stays on the unit's history.
    const unbindComponent = (c) => prompt(
        { title: __('Remove component :id', { id: c.identifier }), label: __('Reason (optional)'), confirmLabel: __('Remove'), required: false },
        async (reason) => {
            const { res, data } = await post(`/operator/unit-labels/components/${c.id}/unbind`, { reason });
            setResult({ severity: res.ok ? 'warning' : 'error', title: data.message || (res.ok ? __('Saved') : __('Error')) });
            if (res.ok) setComponents({ unit: data.unit, items: data.components ?? [] });
        },
    );

    // Numbers ahead of the line: N units registered on the order, their labels in one file.
    const [batchLabel, setBatchLabel] = useState(null); // { count, pdf, zpl }
    // psnOnly: the units start on their process serial alone; the SN comes later.
    const issueBatch = (psnOnly = false) => {
        if (!woId) { setResult({ severity: 'error', title: __('Select a work order first.') }); return; }
        prompt(
            { title: psnOnly ? __('Issue a batch of process serials') : __('Issue a batch of numbers'), label: __('How many units?'), confirmLabel: __('Issue and print'), type: 'number', min: 1, max: 500, defaultValue: 10 },
            async (quantity) => {
                setBusy(true);
                try {
                    const res = await fetch('/operator/unit-labels/issue-batch', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ work_order_id: Number(woId), quantity: Number(quantity), psn_only: psnOnly }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        setResult({ severity: 'error', title: data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || __('Could not issue a number.')) });
                        return;
                    }
                    setResult({ severity: 'success', title: data.message });
                    setBatchLabel({ count: data.count, pdf: data.label_pdf, zpl: data.label_zpl });
                    fetchUnits();
                } catch {
                    setResult({ severity: 'error', title: __('Network error, try again.') });
                } finally {
                    setBusy(false);
                }
            },
        );
    };

    const rebind = () => prompt(
        { title: __('Re-bind process serial'), label: __('Reason'), confirmLabel: __('Re-bind') },
        (reason) => applyLabel({ force: true, reason }),
    );

    const unitColumns = useMemo(() => [
        // The serial is the row's identity: clicking it (or double-clicking the row) shows the label.
        { id: 'serial_no', accessorKey: 'serial_no', header: __('Serial No'), cell: ({ row }) => (
            // A unit still waiting for its SN is named by its PSN until the label goes on.
            row.original.serial_no
                ? <button type="button" className="font-mono font-semibold text-om-accent hover:underline" onClick={() => printLabel(row.original)}>{row.original.serial_no}</button>
                : <button type="button" className="font-mono text-om-muted hover:underline" onClick={() => printLabel(row.original)} title={__('No serial number yet')}>—</button>
        ) },
        { id: 'psn', accessorKey: 'psn', header: __('Process serial (PSN)'), cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.psn || '—'}</span> },
        { id: 'work_order', accessorKey: 'work_order', header: __('Work Order'), cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.work_order || '—'}</span> },
        // The accessor is the translated label, so the filter's options and search read the same as the chips.
        { id: 'status', accessorFn: (r) => serialStatusLabel(r.status), header: __('Status'), cell: ({ row }) => (
            // A held unit says why: the error code, or the failed tests.
            <span className="inline-flex flex-col items-start gap-0.5">
                <StatusBadge size="sm" {...serialStatusBadge(row.original.status)} />
                {row.original.hold && <span className="text-[11px] text-om-blocked">{row.original.hold.source === 'manual' ? `${row.original.hold.reason_code} · ${row.original.hold.reason}` : __('Failed tests')}</span>}
            </span>
        ) },
        { id: 'applied_at', accessorKey: 'applied_at', header: __('Applied At'), cell: ({ row }) => <span className="font-mono text-om-muted text-[11px] whitespace-nowrap">{row.original.applied_at ? formatDateTime(row.original.applied_at) : '—'}</span> },
        {
            id: '_actions',
            header: __('Actions'),
            enableSorting: false,
            meta: { align: 'right', chrome: true },
            cell: ({ row }) => {
                const u = row.original;
                const live = !['scrapped', 'shipped'].includes(u.status);
                return (
                    <div className="flex justify-end gap-1.5">
                        <Button variant="outline" size="sm" leftIcon={<Icon name="printer" size={13} />} onClick={() => printLabel(u)}>{__('Label')}</Button>
                        {live && can('components') && <Button variant="outline" size="sm" leftIcon={<Icon name="puzzle" size={13} />} onClick={() => openComponents(u)}>{__('Components')}</Button>}
                        {live && u.status !== 'blocked' && holdReasons.length > 0 && <Button variant="ghost" size="sm" className="text-om-blocked" leftIcon={<Icon name="ban" size={13} />} onClick={() => setBlockFor({ unit: u, reasonId: '', note: '' })}>{__('Block unit')}</Button>}
                        {u.status === 'blocked' && canUnblock && <Button variant="outline" size="sm" leftIcon={<Icon name="unlock" size={13} />} onClick={() => unblockUnit(u)}>{__('Release unit')}</Button>}
                        {live && <Button variant="ghost" size="sm" className="text-om-blocked" leftIcon={<Icon name="trash-2" size={13} />} onClick={() => scrapUnit(u)}>{__('Scrap unit')}</Button>}
                    </div>
                );
            },
        },
    ], [printLabel, openComponents, scrapUnit, holdReasons, canUnblock, labelActions]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title={__('SN Label Station')} />
            <div className="p-6 space-y-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-om-ink">{__('SN Label Station')}</h1>
                        <p className="text-sm text-om-muted">{__('Apply the pre-printed serial number label and bind it to the process serial number (PSN).')}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {modeOptions.length > 1 && <SegmentedControl
                            label={__('Station mode')}
                            value={mode}
                            onChange={(m) => { setMode(m); setResult(null); }}
                            options={modeOptions}
                        />}
                    <div className="w-72">
                        <Dropdown
                            aria-label={__('Work order')}
                            value={woId}
                            onChange={setWoId}
                            placeholder={__('All work orders')}
                            options={[
                                { value: '', label: __('All work orders') },
                                ...workOrders.map((wo) => ({ value: String(wo.id), label: `${wo.order_no} - ${wo.product || wo.status}` })),
                            ]}
                        />
                    </div>
                    </div>
                </div>

                {mode === 'subassembly' && (
                    <form
                        className="rounded-om border border-om-line bg-om-card p-5"
                        onSubmit={(e) => { e.preventDefault(); if (!busy) registerSubassembly(); }}
                    >
                        <div className="grid max-w-2xl grid-cols-1 gap-4">
                            {produces.length === 0 ? (
                                <InlineAlert severity="info" title={__('This order makes no serial-tracked sub-assembly.')}>
                                    {__('Choose the work order of the sub-assembly station above.')}
                                </InlineAlert>
                            ) : (
                                <>
                                    <div>
                                        <label className="mb-[7px] block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('What was made')}</label>
                                        <Dropdown
                                            aria-label={__('What was made')}
                                            value={subMaterialId}
                                            onChange={setSubMaterialId}
                                            placeholder={__('Choose…')}
                                            options={produces.map((m) => ({ value: String(m.id), label: `${m.code} · ${m.name}` }))}
                                        />
                                    </div>
                                    <TextField
                                        ref={subSerialRef}
                                        mono
                                        autoFocus
                                        label={__('Serial number of the sub-assembly')}
                                        value={subSerial}
                                        onChange={setSubSerial}
                                        placeholder={__('Scan the sub-assembly…')}
                                    />
                                    <div className="flex justify-end">
                                        <Button type="submit" variant="primary" loading={busy} disabled={busy || !subMaterialId || !subSerial.trim()} className="w-full sm:w-auto">
                                            {__('Register sub-assembly')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </div>
                        {result && <InlineAlert severity={result.severity} title={result.title} className="mt-4">{result.body}</InlineAlert>}
                    </form>
                )}

                {mode === 'component' && (
                    <form
                        className="rounded-om border border-om-line bg-om-card p-5"
                        onSubmit={(e) => { e.preventDefault(); if (!busy) bindComponent(); }}
                    >
                        {/* Unit scan, then the component (material beside its identifier), then the button. */}
                        <div className="grid max-w-2xl grid-cols-1 gap-4">
                            <TextField
                                ref={serialRef}
                                mono
                                label={__('Unit number (SN or PSN)')}
                                value={serialNo}
                                onChange={setSerialNo}
                                autoFocus
                                placeholder={__('Scan the unit…')}
                                onBlur={() => loadComponents(serialNo.trim())}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); loadComponents(serialNo.trim()); identifierRef.current?.focus(); } }}
                            />
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-[auto_1fr] sm:items-end">
                            <div className="sm:min-w-56">
                                <label className="mb-[7px] block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Component material')}</label>
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
                            </div>
                            <TextField
                                ref={identifierRef}
                                mono
                                label={__('Component identifier')}
                                value={identifier}
                                onChange={setIdentifier}
                                placeholder={__('Scan the component…')}
                            />
                            </div>
                            <div className="flex justify-end">
                                <Button type="submit" variant="primary" loading={busy} disabled={busy || !serialNo.trim() || !identifier.trim()} className="w-full sm:w-auto">
                                    {__('Bind component')}
                                </Button>
                            </div>
                        </div>

                        {result && <InlineAlert severity={result.severity} title={result.title} className="mt-4">{result.body}</InlineAlert>}

                        {components.unit && (
                            <div className="mt-4 border-t border-om-line2 pt-4">
                                <div className="mb-2 flex flex-wrap items-center gap-2 text-[13px]">
                                    <span className="font-mono font-semibold text-om-ink">{components.unit.serial_no ?? __('No serial number yet')}</span>
                                    {components.unit.psn && <span className="font-mono text-om-muted">{components.unit.psn}</span>}
                                    <StatusBadge size="sm" {...serialStatusBadge(components.unit.status)} />
                                    {components.unit.status !== 'blocked' && !['scrapped', 'shipped'].includes(components.unit.status) && holdReasons.length > 0 && (
                                        <Button type="button" variant="ghost" size="sm" className="text-om-blocked" leftIcon={<Icon name="ban" size={13} />} onClick={() => setBlockFor({ unit: components.unit, reasonId: '', note: '' })}>{__('Block unit')}</Button>
                                    )}
                                    <span className="ml-auto text-om-muted">{__(':count components', { count: components.items.length })}</span>
                                </div>
                                {components.items.length > 0 && (
                                    <ul className="flex flex-wrap gap-1.5">
                                        {components.items.map((c) => (
                                            <li key={c.id} className="flex items-center gap-1">
                                                <Badge variant={c.kind === 'identifier' ? 'neutral' : 'outline'} title={c.material ? `${c.material.code} · ${c.material.name}` : undefined}>
                                                    {c.material?.code ? `${c.material.code}: ` : ''}{c.identifier}
                                                </Badge>
                                                <Button type="button" variant="ghost" size="sm" aria-label={__('Remove component :id', { id: c.identifier })} title={__('Remove')} onClick={() => unbindComponent(c)}>
                                                    <Icon name="x" size={12} />
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                    </form>
                )}

                {mode === 'label' && <form
                    className="rounded-om border border-om-line bg-om-card p-5"
                    onSubmit={(e) => { e.preventDefault(); if (!busy) applyLabel(); }}
                >
                    {/* The two scans stacked, each with its issue button; the apply
                        button closes the form under them. */}
                    {can('label') && <div className={`grid max-w-2xl items-end gap-x-3 gap-y-4 ${can('issue') ? 'grid-cols-[1fr_auto]' : 'grid-cols-1'}`}>
                        <TextField
                            ref={psnRef}
                            mono
                            label={__('Process Serial Number (PSN)')}
                            value={psn}
                            onChange={setPsn}
                            autoFocus
                            placeholder={__('Scan the process serial…')}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); serialRef.current?.focus(); } }}
                        />
                        {can('issue') && <Button type="button" variant="outline" disabled={busy} onClick={() => issue('process_serial')} title={__('Next number from the product\'s process serial sequence')}>
                            {__('Issue number')}
                        </Button>}
                        <TextField
                            ref={serialRef}
                            mono
                            label={__('Serial Number (label)')}
                            value={serialNo}
                            onChange={setSerialNo}
                            placeholder={__('Scan the serial number label…')}
                        />
                        {can('issue') && <Button type="button" variant="outline" disabled={busy} onClick={() => issue('unit_serial')} title={__('Next number from the product\'s unit serial sequence')}>
                            {__('Issue number')}
                        </Button>}
                        <div className={`${can('issue') ? 'col-span-2' : ''} flex justify-end`}>
                            <Button type="submit" variant="primary" loading={busy} disabled={busy || !serialNo.trim()} className="w-full sm:w-auto">
                                {__('Apply label')}
                            </Button>
                        </div>
                    </div>}
                    {(can('start') || can('issue')) && <div className={`${can('label') ? 'mt-3' : ''} flex flex-wrap items-center gap-2 text-[12px] text-om-muted`}>
                        {can('start') && <Button type="button" variant={can('label') ? 'outline' : 'primary'} size={can('label') ? 'sm' : 'md'} disabled={busy} leftIcon={<Icon name="plus" size={13} />} onClick={startUnit} title={__('First station: the unit starts on its process serial (the one in the field, or the next from the sequence) and its PSN label prints; the serial number follows with the product label.')} data-testid="start-unit">
                            {__('Start unit on PSN')}
                        </Button>}
                        {can('issue') && <>
                        <Button type="button" variant="ghost" size="sm" disabled={busy} leftIcon={<Icon name="layers" size={13} />} onClick={() => issueBatch(false)} title={__('Numbers issued ahead of the line: the labels can be printed now and are applied by scanning them.')}>
                            {__('Issue batch…')}
                        </Button>
                        <Button type="button" variant="ghost" size="sm" disabled={busy} leftIcon={<Icon name="layers" size={13} />} onClick={() => issueBatch(true)}>
                            {__('Issue PSN batch…')}
                        </Button>
                        <span>{__('Numbers issued ahead of the line: the labels can be printed now and are applied by scanning them.')}</span>
                        </>}
                        {!can('label') && can('start') && <span>{__('Each unit starts here on its process serial; its PSN label prints for the housing.')}</span>}
                    </div>}

                    {result && (
                        <InlineAlert severity={result.severity} title={result.title} className="mt-4">
                            {result.body}
                            {result.rebindable && (
                                <div className="mt-2">
                                    <Button variant="outline" size="sm" onClick={rebind}>{__('Re-bind process serial')}</Button>
                                </div>
                            )}
                        </InlineAlert>
                    )}
                </form>}

                <div className="overflow-hidden rounded-om border border-om-line bg-om-card">
                    <div className="px-[22px] pt-4 pb-3">
                        <h2 className="text-[15px] font-semibold text-om-ink">{__('Recent units (last 50)')}</h2>
                    </div>
                    <AppDataTable data={units} columns={unitColumns} searchable onRowDoubleClick={(u) => printLabel(u)} />
                </div>
            </div>
            {promptDialog}
            <Modal
                open={blockFor != null}
                onClose={() => setBlockFor(null)}
                title={__('Block unit :sn', { sn: blockFor?.unit?.serial_no ?? blockFor?.unit?.psn ?? '' })}
                subtitle={__('A held unit is not packed and fails its pallet’s quality gate until a supervisor releases it.')}
                closeLabel={__('Close')}
                footer={(
                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" onClick={() => setBlockFor(null)}>{__('Cancel')}</Button>
                        <Button variant="danger" onClick={confirmBlock} data-testid="confirm-block">{__('Block unit')}</Button>
                    </div>
                )}
            >
                <div className="flex flex-col gap-4">
                    <div>
                        <div className="mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Error code')}</div>
                        <Dropdown
                            aria-label={__('Error code')}
                            value={blockFor?.reasonId ?? ''}
                            onChange={(v) => setBlockFor((b) => ({ ...b, reasonId: v, error: null }))}
                            placeholder={__('Choose…')}
                            options={holdReasons.map((r) => ({ value: String(r.id), label: `${r.code} · ${r.name}` }))}
                            className="w-full"
                        />
                    </div>
                    <TextField label={__('Note (optional)')} value={blockFor?.note ?? ''} onChange={(v) => setBlockFor((b) => ({ ...b, note: v }))} placeholder={__('What was found?')} />
                    {blockFor?.error && <InlineAlert severity="error" title={blockFor.error} />}
                </div>
            </Modal>
            <LabelPreviewModal
                open={labelFor != null}
                onClose={() => setLabelFor(null)}
                title={__('Unit label')}
                subtitle={labelFor?.serial_no ?? labelFor?.psn}
                pdfUrl={labelFor ? `/packaging/labels/serial-unit/${labelFor.id}/pdf` : null}
                zplUrl={labelFor ? `/packaging/labels/serial-unit/${labelFor.id}/zpl` : null}
            />
            <LabelPreviewModal
                open={batchLabel != null}
                onClose={() => setBatchLabel(null)}
                title={__('Unit labels (:count)', { count: batchLabel?.count ?? 0 })}
                subtitle={workOrders.find((wo) => String(wo.id) === String(woId))?.order_no}
                pdfUrl={batchLabel?.pdf ?? null}
                zplUrl={batchLabel?.zpl ?? null}
            />
        </>
    );
}

Station.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
