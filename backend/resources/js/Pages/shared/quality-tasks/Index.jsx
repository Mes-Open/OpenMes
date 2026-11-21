import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Button, Modal, TextField, Dropdown, InlineAlert } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

// One editable row per template parameter for each sample (or a single
// pass/fail when the trigger has no template). Only the values are sent: the
// server judges each sample against the template's limits.
function initialRows(triggerInfo) {
    const params = triggerInfo?.parameters ?? [];
    if (!params.length) {
        return [{ sample_number: 1, parameter_name: __('Result'), parameter_type: 'pass_fail', value_numeric: '', passed: true }];
    }
    const perCheck = Math.max(1, Number(triggerInfo?.samples_per_check) || 1);
    return Array.from({ length: perCheck }, (_, i) => i + 1).flatMap((n) =>
        params.map((p) => ({
            sample_number: n,
            parameter_name: p.name,
            parameter_type: p.type === 'measurement' ? 'measurement' : 'pass_fail',
            unit: p.unit,
            min: p.min,
            max: p.max,
            value_numeric: '',
            passed: true,
        }))
    );
}

function limitsLabel(row) {
    const has = (v) => v !== null && v !== undefined && v !== '';
    if (has(row.min) && has(row.max)) return `${row.min}–${row.max}`;
    if (has(row.min)) return `≥ ${row.min}`;
    if (has(row.max)) return `≤ ${row.max}`;
    return '';
}

export default function QualityTasksIndex() {
    const {
        triggers = {},
        workOrderNos = {},
        lineNames = {},
        batchNumbers = {},
        roamingTriggers = [],
        activeBatches = [],
        pallets = [],
        errors = {},
    } = usePage().props;
    const sampleErrors = Object.entries(errors).filter(([k]) => k === 'samples' || k.startsWith('samples.'));

    const base =
        typeof window !== 'undefined' && window.location.pathname.startsWith('/admin')
            ? '/admin'
            : '/supervisor';

    const [performFor, setPerformFor] = useState(null);
    const [rows, setRows] = useState([]);
    const [notes, setNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [palletId, setPalletId] = useState('');
    const [roamingOpen, setRoamingOpen] = useState(false);
    const [roamingTrigger, setRoamingTrigger] = useState('');
    const [roamingBatch, setRoamingBatch] = useState('');

    const openPerform = (task) => {
        setRows(initialRows(triggers[task.quality_control_trigger_id]));
        setNotes('');
        setPalletId('');
        setPerformFor(task);
    };

    // Pallets belonging to the task's work order — the control can be linked to one (#106).
    const palletOptions = performFor
        ? pallets.filter((p) => !performFor.work_order_id || p.work_order_id === performFor.work_order_id)
        : [];

    const setRow = (i, patch) => setRows((rs) => rs.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));

    const submitPerform = () => {
        const samples = rows.map((r) => ({
            sample_number: r.sample_number,
            parameter_name: r.parameter_name,
            parameter_type: r.parameter_type,
            ...(r.parameter_type === 'measurement'
                ? { value_numeric: r.value_numeric === '' ? null : Number(r.value_numeric) }
                : { value_boolean: !!r.passed }),
        }));
        setSubmitting(true);
        router.post(`${base}/quality-tasks/${performFor.id}/perform`, { samples, notes, pallet_id: palletId || null }, {
            preserveScroll: true,
            onSuccess: () => setPerformFor(null),
            onFinish: () => setSubmitting(false),
        });
    };

    const skip = (task) => {
        router.post(`${base}/quality-tasks/${task.id}/skip`, {}, { preserveScroll: true });
    };

    const submitRoaming = () => {
        if (!roamingTrigger || !roamingBatch) return;
        router.post(`${base}/quality-tasks`, { quality_control_trigger_id: roamingTrigger, batch_id: roamingBatch }, {
            preserveScroll: true,
            onSuccess: () => { setRoamingOpen(false); setRoamingTrigger(''); setRoamingBatch(''); },
        });
    };

    const columns = [
        {
            key: 'quality_control_trigger_id',
            label: __('Control'),
            className: 'font-medium text-om-ink',
            value: (r) => triggers[r.quality_control_trigger_id]?.name ?? `#${r.quality_control_trigger_id}`,
           
            render: (r) => triggers[r.quality_control_trigger_id]?.name ?? `#${r.quality_control_trigger_id}`,
        },
        { key: 'due_reason', label: __('Reason'), className: 'text-om-muted' },
        {
            key: 'work_order_id',
            label: __('Work order'),
            className: 'text-om-muted',
            value: (r) => (r.work_order_id ? (workOrderNos[r.work_order_id] ?? `#${r.work_order_id}`) : '—'),
            render: (r) => (r.work_order_id ? (workOrderNos[r.work_order_id] ?? `#${r.work_order_id}`) : '—'),
        },
        {
            key: 'batch_id',
            label: __('Batch'),
            className: 'text-om-muted',
            value: (r) => (r.batch_id ? (batchNumbers[r.batch_id] ?? `#${r.batch_id}`) : '—'),
            render: (r) => (r.batch_id ? (batchNumbers[r.batch_id] ?? `#${r.batch_id}`) : '—'),
        },
        {
            key: 'line_id',
            label: __('Line'),
            className: 'text-om-muted',
            value: (r) => (r.line_id ? (lineNames[r.line_id] ?? `#${r.line_id}`) : '—'),
           
            render: (r) => (r.line_id ? (lineNames[r.line_id] ?? `#${r.line_id}`) : '—'),
        },
        {
            key: 'fired_at', filter: 'date',
            label: __('Due since'),
            className: 'text-om-faint',
            render: (r) => (r.fired_at ? String(r.fired_at).slice(0, 16).replace('T', ' ') : '—'),
        },
    ];

    const actions = (r) => {
        const list = [];
        if (r.batch_id) {
            list.push({ label: __('Perform'), variant: 'primary', onClick: () => openPerform(r) });
        }
        list.push({
            label: __('Skip'),
            confirm: {
                title: __('Skip this quality control?'),
                confirmLabel: __('Skip'),
            },
            onClick: () => skip(r),
        });
        return list;
    };

    return (
        <>
            <Head title={__('Quality Controls')} />
            <ResourceTable
                shape="quality_control_tasks_due"
                title={__('Quality Controls')}
                columns={columns}
                orderBy="fired_at"
                actions={actions}
                emptyText={__('No outstanding quality controls.')}
                subtitle={
                    roamingTriggers.length ? (
                        <Button variant="secondary" onClick={() => setRoamingOpen(true)}>
                            {__('Raise roaming check')}
                        </Button>
                    ) : null
                }
            />

            {/* Perform modal — record the control's samples. */}
            <Modal
                open={performFor != null}
                onClose={() => setPerformFor(null)}
                title={__('Perform quality control')}
                subtitle={performFor ? triggers[performFor.quality_control_trigger_id]?.name : ''}
                closeLabel={__('Close')}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setPerformFor(null)}>{__('Cancel')}</Button>
                        <Button variant="primary" onClick={submitPerform} disabled={submitting}>{__('Record result')}</Button>
                    </>
                }
            >
                <div className="space-y-[14px]">
                    {rows.map((row, i) => (
                        <div key={i} className="flex items-end gap-3">
                            <div className="flex-1">
                                <div className="text-[12.5px] font-medium text-om-ink mb-1">
                                    {rows.length > 1 ? `${__('Sample #')}${row.sample_number} · ` : ''}
                                    {row.parameter_name}
                                    {row.unit ? <span className="text-om-faint"> ({row.unit})</span> : null}
                                    {limitsLabel(row) ? <span className="text-om-faint"> {limitsLabel(row)}</span> : null}
                                </div>
                                {row.parameter_type === 'measurement' && (
                                    <TextField
                                        label={__('Measured value')}
                                        inputMode="decimal"
                                        value={row.value_numeric}
                                        onChange={(v) => setRow(i, { value_numeric: v })}
                                    />
                                )}
                            </div>
                            {row.parameter_type !== 'measurement' && (
                                <Dropdown
                                    label={__('Result')}
                                    value={row.passed ? 'pass' : 'fail'}
                                    onChange={(v) => setRow(i, { passed: v === 'pass' })}
                                    options={[
                                        { value: 'pass', label: __('Pass') },
                                        { value: 'fail', label: __('Fail') },
                                    ]}
                                />
                            )}
                        </div>
                    ))}
                    {palletOptions.length > 0 && (
                        <Dropdown
                            label={__('Link to pallet (optional)')}
                            value={palletId}
                            onChange={setPalletId}
                            options={[
                                { value: '', label: __('— None —') },
                                ...palletOptions.map((p) => ({ value: String(p.id), label: p.pallet_no })),
                            ]}
                        />
                    )}
                    <TextField
                        label={__('Notes')}
                        multiline
                        value={notes}
                        onChange={setNotes}
                        placeholder={__('Optional')}
                    />
                    {sampleErrors.length > 0 && (
                        <InlineAlert severity="error">
                            {sampleErrors.map(([k, msg]) => <div key={k}>{msg}</div>)}
                        </InlineAlert>
                    )}
                    <InlineAlert severity="info">
                        {__('A failing result raises a non-conformance and, for blocking controls, halts the work order.')}
                    </InlineAlert>
                </div>
            </Modal>

            {/* Roaming check — raise an ad-hoc control. */}
            <Modal
                open={roamingOpen}
                onClose={() => setRoamingOpen(false)}
                title={__('Raise roaming check')}
                closeLabel={__('Close')}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setRoamingOpen(false)}>{__('Cancel')}</Button>
                        <Button variant="primary" onClick={submitRoaming}>{__('Raise')}</Button>
                    </>
                }
            >
                <div className="space-y-[14px]">
                    <Dropdown
                        label={__('Roaming trigger')}
                        value={roamingTrigger}
                        onChange={setRoamingTrigger}
                        options={[
                            { value: '', label: __('Select…') },
                            ...roamingTriggers.map((t) => ({ value: String(t.id), label: t.name })),
                        ]}
                    />
                    <Dropdown
                        label={__('Batch')}
                        value={roamingBatch}
                        onChange={setRoamingBatch}
                        options={[
                            { value: '', label: __('Select…') },
                            ...activeBatches.map((b) => ({ value: String(b.id), label: b.label })),
                        ]}
                    />
                </div>
            </Modal>
        </>
    );
}

QualityTasksIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
