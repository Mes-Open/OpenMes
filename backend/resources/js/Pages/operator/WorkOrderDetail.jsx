import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import OperatorLayout from '../../layouts/OperatorLayout';
import LineSync from '../../components/LineSync';
import LabelPrintMenu from '../../components/LabelPrintMenu';
import CustomFields from '../../components/CustomFields';
import { customFieldInitial, customFieldProps, submitForm } from '../../lib/customFieldForm';
import { formatDate, formatDateTime, formatNumber } from '../../lib/i18n';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fmtQty(n, decimals = 2) {
    if (n == null) return '—';
    return formatNumber(Number(n), { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function statusBadge(status) {
    const map = {
        PENDING:     'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        IN_PROGRESS: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        DONE:        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        BLOCKED:     'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return map[status] ?? 'bg-gray-100 text-gray-500';
}

function statusLabel(status) {
    if (status === 'PENDING') return 'Not Started';
    return (status ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function batchStatusBadge(status) {
    const map = {
        PENDING:     'bg-gray-100 text-gray-700',
        IN_PROGRESS: 'bg-blue-100 text-blue-700',
        DONE:        'bg-green-100 text-green-700',
    };
    return map[status] ?? 'bg-gray-100 text-gray-400';
}

function issueBadge(status) {
    const map = {
        OPEN:         'bg-red-100 text-red-700',
        ACKNOWLEDGED: 'bg-yellow-100 text-yellow-700',
        RESOLVED:     'bg-green-100 text-green-700',
    };
    return map[status] ?? 'bg-gray-100 text-gray-400';
}

function bomTypeBadge(type) {
    const map = {
        raw_material:  'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        semi_finished: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        packaging:     'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    };
    return map[type] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function ChevronIcon({ open }) {
    return (
        <svg
            className={`w-5 h-5 text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`}
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
        </svg>
    );
}

// ---------------------------------------------------------------------------
// BOM accordion
// ---------------------------------------------------------------------------

function BomSection({ workOrder }) {
    const [open, setOpen] = useState(false);
    const bom = workOrder.process_snapshot?.bom;
    if (!bom || bom.length === 0) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
            <button
                type="button"
                className="flex justify-between items-center w-full text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100">Recipe / Materials</h2>
                <div className="flex items-center gap-2">
                    <span className="text-sm text-gray-500">{bom.length} items</span>
                    <ChevronIcon open={open} />
                </div>
            </button>

            {open && (
                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-gray-500 uppercase">
                                <th className="pb-2">Material</th>
                                <th className="pb-2">Type</th>
                                <th className="pb-2 text-right">Per Unit</th>
                                <th className="pb-2 text-right">
                                    Total ({Math.round(workOrder.planned_qty)} pcs)
                                </th>
                                <th className="pb-2">Step</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                            {bom.map((item, idx) => {
                                const base = item.quantity_per_unit * workOrder.planned_qty;
                                const scrap = base * (item.scrap_percentage / 100);
                                const total = base + scrap;
                                return (
                                    <tr key={idx}>
                                        <td className="py-2">
                                            <span className="font-medium">{item.material_name}</span>
                                            <span className="text-xs text-gray-500 font-mono ml-1">{item.material_code}</span>
                                        </td>
                                        <td className="py-2">
                                            <span className={`px-2 py-0.5 rounded-full text-xs ${bomTypeBadge(item.material_type)}`}>
                                                {item.material_type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                                            </span>
                                        </td>
                                        <td className="py-2 text-right font-mono">
                                            {item.quantity_per_unit} {item.unit_of_measure}
                                        </td>
                                        <td className="py-2 text-right font-mono font-medium">
                                            {fmtQty(total)} {item.unit_of_measure}
                                            {item.scrap_percentage > 0 && (
                                                <span className="text-xs text-gray-500 ml-1">(+{item.scrap_percentage}% scrap)</span>
                                            )}
                                        </td>
                                        <td className="py-2 text-gray-500">
                                            {item.step_number ? `#${item.step_number}` : 'General'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Process reference photos (work instructions) — read-only for operators.
// Images stream from an authenticated endpoint; tap to enlarge.
// ---------------------------------------------------------------------------

function ProcessPhotosSection({ photos = [] }) {
    const [open, setOpen] = useState(true);
    const [lightbox, setLightbox] = useState(null);
    if (!photos || photos.length === 0) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
            <button
                type="button"
                className="flex justify-between items-center w-full text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100">Work Instructions</h2>
                <div className="flex items-center gap-2">
                    <span className="text-sm text-gray-500">{photos.length} photos</span>
                    <ChevronIcon open={open} />
                </div>
            </button>

            {open && (
                <div className="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    {photos.map((photo) => (
                        <figure key={photo.id} className="m-0">
                            <button
                                type="button"
                                onClick={() => setLightbox(photo)}
                                className="block w-full"
                                title={photo.caption || ''}
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.caption || 'Work instruction'}
                                    loading="lazy"
                                    className="w-full h-32 object-cover rounded-lg bg-gray-100 dark:bg-slate-700"
                                />
                            </button>
                            {photo.caption && (
                                <figcaption className="mt-1 text-xs text-gray-600 dark:text-gray-300 truncate">
                                    {photo.caption}
                                </figcaption>
                            )}
                        </figure>
                    ))}
                </div>
            )}

            {lightbox && (
                <div
                    className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-6"
                    onClick={() => setLightbox(null)}
                >
                    <figure className="max-w-4xl max-h-full m-0" onClick={(e) => e.stopPropagation()}>
                        <img
                            src={lightbox.url}
                            alt={lightbox.caption || 'Work instruction'}
                            className="max-w-full max-h-[80vh] rounded-lg shadow-2xl"
                        />
                        {lightbox.caption && (
                            <figcaption className="text-white/90 text-sm mt-3 text-center">{lightbox.caption}</figcaption>
                        )}
                    </figure>
                    <button
                        type="button"
                        onClick={() => setLightbox(null)}
                        className="absolute top-5 right-5 text-white/80 hover:text-white text-3xl leading-none"
                        title="Close"
                    >
                        ×
                    </button>
                </div>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Quality Check form (3 fixed samples per Blade)
// ---------------------------------------------------------------------------

function QualityCheckForm({ batch, onClose }) {
    const [productionQty, setProductionQty] = useState('');
    // 3 samples × 2 parameters (Dimension measurement + Fit check pass/fail)
    const [samples, setSamples] = useState(() =>
        [1, 2, 3].flatMap((s) => [
            { sample_number: s, parameter_name: 'Dimension', parameter_type: 'measurement', value_numeric: '', is_passed: '1' },
            { sample_number: s, parameter_name: 'Fit check', parameter_type: 'pass_fail', value_boolean: '1', is_passed: '1' },
        ])
    );
    const [processing, setProcessing] = useState(false);

    const updateSample = (idx, key, val) => {
        setSamples((prev) => prev.map((s, i) => (i === idx ? { ...s, [key]: val } : s)));
    };

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        const payload = {
            production_quantity: productionQty || undefined,
            samples: samples.map((s) => ({
                sample_number: s.sample_number,
                parameter_name: s.parameter_name,
                parameter_type: s.parameter_type,
                ...(s.parameter_type === 'measurement'
                    ? { value_numeric: parseFloat(s.value_numeric), is_passed: 1 }
                    : { value_boolean: parseInt(s.value_boolean, 10), is_passed: 1 }),
            })),
        };
        router.post(`/operator/batch/${batch.id}/quality-check`, payload, {
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="mt-3 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <form onSubmit={submit}>
                <div className="mb-3">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Production Quantity</label>
                    <input
                        type="number"
                        step="0.01"
                        value={productionQty}
                        onChange={(e) => setProductionQty(e.target.value)}
                        className="form-input w-full"
                        placeholder="Current production qty"
                    />
                </div>

                {[1, 2, 3].map((s) => {
                    const dimIdx = (s - 1) * 2;
                    const fitIdx = (s - 1) * 2 + 1;
                    return (
                        <div key={s} className="mb-2 p-2 bg-white dark:bg-slate-700 rounded">
                            <p className="text-xs font-bold text-gray-500 mb-1">Sample #{s}</p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={samples[dimIdx].value_numeric}
                                        onChange={(e) => updateSample(dimIdx, 'value_numeric', e.target.value)}
                                        className="form-input w-full text-sm"
                                        placeholder="Dimension"
                                        required
                                    />
                                </div>
                                <div>
                                    <select
                                        value={samples[fitIdx].value_boolean}
                                        onChange={(e) => updateSample(fitIdx, 'value_boolean', e.target.value)}
                                        className="form-input w-full text-sm"
                                        required
                                    >
                                        <option value="1">Pass</option>
                                        <option value="0">Fail</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    );
                })}

                <div className="flex gap-2 mt-2">
                    <button type="submit" disabled={processing} className="btn-touch btn-primary text-sm disabled:opacity-50">
                        Submit QC
                    </button>
                    <button type="button" onClick={onClose} className="btn-touch btn-secondary text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Packaging Checklist form
// ---------------------------------------------------------------------------

function PackagingChecklistForm({ batch, onClose }) {
    const form = useForm({
        udi_readable: false,
        packaging_condition: false,
        labels_readable: false,
        label_matches_product: false,
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/operator/batch/${batch.id}/packaging-checklist`);
    };

    const checks = [
        ['udi_readable', 'UDI code readable'],
        ['packaging_condition', 'Packaging in good condition'],
        ['labels_readable', 'Labels readable'],
        ['label_matches_product', 'Label matches product'],
    ];

    return (
        <div className="mt-3 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
            <form onSubmit={submit}>
                {checks.map(([field, label]) => (
                    <label key={field} className="flex items-center gap-2 mb-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={form.data[field]}
                            onChange={(e) => form.setData(field, e.target.checked)}
                            className="rounded border-gray-300 text-blue-600"
                        />
                        <span className="text-sm">{label}</span>
                    </label>
                ))}
                <div className="flex gap-2 mt-2">
                    <button type="submit" disabled={form.processing} className="btn-touch btn-primary text-sm disabled:opacity-50">
                        Submit Checklist
                    </button>
                    <button type="button" onClick={onClose} className="btn-touch btn-secondary text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Release form
// ---------------------------------------------------------------------------

function ReleaseForm({ batch, onClose }) {
    const form = useForm({ scrap_qty: '', release_type: '' });

    const submitWith = (releaseType) => {
        form.setData('release_type', releaseType);
        form.post(`/operator/batch/${batch.id}/release`, {
            data: { ...form.data, release_type: releaseType },
        });
    };

    return (
        <div className="mt-3 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <div className="mb-3">
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Scrap quantity (optional)
                </label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.data.scrap_qty}
                    onChange={(e) => form.setData('scrap_qty', e.target.value)}
                    className="form-input w-32 text-sm"
                    placeholder="0"
                />
            </div>
            <p className="text-sm mb-3 text-gray-700 dark:text-gray-300">Release this batch?</p>
            <div className="flex gap-3 flex-wrap">
                <button
                    type="button"
                    disabled={form.processing}
                    onClick={() => submitWith('for_production')}
                    className="btn-touch btn-secondary text-sm disabled:opacity-50"
                >
                    For Production
                </button>
                <button
                    type="button"
                    disabled={form.processing}
                    onClick={() => submitWith('for_sale')}
                    className="btn-touch btn-primary text-sm disabled:opacity-50"
                >
                    For Sale
                </button>
                <button type="button" onClick={onClose} className="btn-touch btn-secondary text-sm">
                    Cancel
                </button>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Confirm Parameters button
// ---------------------------------------------------------------------------

function ConfirmParametersRow({ batch }) {
    const [processing, setProcessing] = useState(false);

    const lastConfirm = (batch.process_confirmations ?? [])
        .filter((c) => c.confirmation_type === 'parameters' && c.confirmed_at)
        .sort((a, b) => new Date(b.confirmed_at) - new Date(a.confirmed_at))[0];

    const handleClick = () => {
        setProcessing(true);
        router.post(
            `/operator/batch/${batch.id}/confirm`,
            { confirmation_type: 'parameters' },
            { onFinish: () => setProcessing(false) }
        );
    };

    return (
        <div className="flex items-center gap-3">
            <button
                type="button"
                disabled={processing}
                onClick={handleClick}
                className="btn-touch btn-secondary text-sm disabled:opacity-50"
            >
                Confirm Parameters
            </button>
            {lastConfirm && (
                <span className="text-xs text-green-600">
                    Last: {formatDateTime(new Date(lastConfirm.confirmed_at), { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                </span>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Production Controls panel per batch
// ---------------------------------------------------------------------------

function ProductionControls({ batch }) {
    const [showQc, setShowQc] = useState(false);
    const [showChecklist, setShowChecklist] = useState(false);
    const [showRelease, setShowRelease] = useState(false);

    const qcCount = (batch.quality_checks ?? []).length;
    const hasChecklist = !!batch.packaging_checklist;
    const isReleased = !!(batch.released_at);

    return (
        <div className="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
            <h4 className="text-sm font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                Production Controls
            </h4>

            {/* Confirm Parameters */}
            <ConfirmParametersRow batch={batch} />

            {/* Quality Check */}
            <div>
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => setShowQc((v) => !v)}
                        className="btn-touch btn-secondary text-sm"
                    >
                        Quality Check ({qcCount})
                    </button>
                    {qcCount < 3 ? (
                        <span className="text-xs text-orange-600">{3 - qcCount} more needed</span>
                    ) : (
                        <span className="text-xs text-green-600">Min. requirement met</span>
                    )}
                </div>
                {showQc && <QualityCheckForm batch={batch} onClose={() => setShowQc(false)} />}
            </div>

            {/* Packaging Checklist */}
            {!hasChecklist ? (
                <div>
                    <button
                        type="button"
                        onClick={() => setShowChecklist((v) => !v)}
                        className="btn-touch btn-secondary text-sm"
                    >
                        Packaging Checklist
                    </button>
                    {showChecklist && (
                        <PackagingChecklistForm batch={batch} onClose={() => setShowChecklist(false)} />
                    )}
                </div>
            ) : (
                <div className="text-sm">
                    <span className={batch.packaging_checklist.all_passed ? 'text-green-600' : 'text-red-600'}>
                        Packaging: {batch.packaging_checklist.all_passed ? 'All passed' : 'Some items failed'}
                    </span>
                </div>
            )}

            {/* Release */}
            {batch.status === 'DONE' && !isReleased && (
                <div>
                    <button
                        type="button"
                        onClick={() => setShowRelease((v) => !v)}
                        className="btn-touch bg-green-600 text-white hover:bg-green-700 text-sm"
                    >
                        Release Batch
                    </button>
                    {showRelease && <ReleaseForm batch={batch} onClose={() => setShowRelease(false)} />}
                </div>
            )}

            {/* Series Report after release (admin-only route: admin/batches/{id}/report) */}
            {isReleased && usePage().props.auth?.user?.roles?.includes('Admin') && (
                <a
                    href={`/admin/batches/${batch.id}/report`}
                    target="_blank"
                    rel="noreferrer"
                    className="btn-touch btn-secondary text-sm inline-block"
                >
                    Series Report
                </a>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Single Batch card
// ---------------------------------------------------------------------------

function BatchCard({ batch, defaultOpen, labelTemplates = [] }) {
    const [expanded, setExpanded] = useState(defaultOpen);
    const showControls = batch.status === 'IN_PROGRESS' || batch.status === 'DONE';

    const releaseLabel =
        batch.release_type === 'for_sale' ? 'For Sale' : 'For Production';
    const isReleased = !!(batch.released_at || batch.released);

    return (
        <div className="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            {/* Header row */}
            <div className="flex items-center gap-3">
                <button
                    type="button"
                    className="flex flex-1 justify-between items-center text-left"
                    onClick={() => setExpanded((v) => !v)}
                >
                    <div className="flex items-center gap-4">
                        <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100">
                            Batch #{batch.batch_number}
                        </h3>
                        <span className={`px-3 py-1 rounded-full text-sm font-medium ${batchStatusBadge(batch.status)}`}>
                            {statusLabel(batch.status)}
                        </span>
                        <span className="text-sm text-gray-500">
                            {fmtQty(batch.produced_qty)} / {fmtQty(batch.target_qty)}
                        </span>
                    </div>
                    <svg
                        className={`w-6 h-6 text-gray-400 transition-transform ${expanded ? 'rotate-180' : ''}`}
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {/* FG Label button — only shown for released batches */}
                {isReleased && (
                    <div onClick={(e) => e.stopPropagation()}>
                        <LabelPrintMenu
                            kind="finished-goods"
                            id={batch.id}
                            templates={labelTemplates}
                            label="FG Label"
                        />
                    </div>
                )}
            </div>

            {expanded && (
                <div className="mt-4 space-y-4">
                    {/* Info bar */}
                    <div className="flex flex-wrap gap-4 text-sm bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg">
                        {batch.lot_number && (
                            <span className="font-medium">
                                LOT: <span className="font-mono text-blue-600">{batch.lot_number}</span>
                            </span>
                        )}
                        {batch.workstation && (
                            <span className="font-medium">Workstation: {batch.workstation.name}</span>
                        )}
                        {isReleased && (
                            <span className="text-green-600 font-medium">
                                Released ({releaseLabel})
                            </span>
                        )}
                        {batch.expiry_date && (
                            <span>Expiry: {batch.expiry_date}</span>
                        )}
                    </div>

                    {/* Steps */}
                    <BatchStepList steps={batch.steps ?? []} labelTemplates={labelTemplates} />

                    {/* Production controls */}
                    {showControls && <ProductionControls batch={batch} />}
                </div>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Batch Steps list (replaces the Livewire component)
// ---------------------------------------------------------------------------

function BatchStepList({ steps, labelTemplates = [] }) {
    const [inflightStepId, setInflightStepId] = useState(null);

    if (!steps || steps.length === 0) return null;

    const handleStepAction = (step, action) => {
        setInflightStepId(step.id);
        router.post(
            `/operator/batch-step/${step.id}/${action}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setInflightStepId(null),
            }
        );
    };

    return (
        <div>
            <h4 className="text-sm font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Steps</h4>
            <div className="space-y-2">
                {steps.map((step) => {
                    const isInflight = inflightStepId === step.id;
                    return (
                        <div key={step.id} className="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <span className="w-7 h-7 flex-shrink-0 flex items-center justify-center rounded-full text-xs font-bold bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                {step.step_number}
                            </span>
                            <span className="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {step.name}
                            </span>

                            {/* Status label for terminal states */}
                            {step.status === 'DONE' && (
                                <span className="text-xs text-green-600 whitespace-nowrap">
                                    Done{step.completed_by ? ` by ${step.completed_by.name}` : ''}
                                </span>
                            )}
                            {step.status === 'SKIPPED' && (
                                <span className="text-xs text-gray-400 whitespace-nowrap">Skipped</span>
                            )}
                            {step.status === 'IN_PROGRESS' && !inflightStepId && (
                                <span className="text-xs text-blue-600 whitespace-nowrap">
                                    In progress{step.started_by ? ` by ${step.started_by.name}` : ''}
                                </span>
                            )}
                            {/* Fallback for older data without explicit status field */}
                            {!step.status && step.completed_at && (
                                <span className="text-xs text-green-600 whitespace-nowrap">
                                    Done{step.completed_by ? ` by ${step.completed_by.name}` : ''}
                                </span>
                            )}
                            {!step.status && !step.completed_at && step.started_at && (
                                <span className="text-xs text-blue-600 whitespace-nowrap">
                                    In progress{step.started_by ? ` by ${step.started_by.name}` : ''}
                                </span>
                            )}

                            {/* Action buttons */}
                            {step.status === 'PENDING' && (
                                <button
                                    type="button"
                                    disabled={isInflight}
                                    onClick={() => handleStepAction(step, 'start')}
                                    className="btn-touch bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg font-medium disabled:opacity-50 whitespace-nowrap"
                                >
                                    {isInflight ? '…' : 'Start'}
                                </button>
                            )}
                            {step.status === 'IN_PROGRESS' && (
                                <button
                                    type="button"
                                    disabled={isInflight}
                                    onClick={() => handleStepAction(step, 'complete')}
                                    className="btn-touch bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-lg font-medium disabled:opacity-50 whitespace-nowrap"
                                >
                                    {isInflight ? '…' : 'Complete'}
                                </button>
                            )}

                            {/* Per-step label print (compact) */}
                            <LabelPrintMenu
                                kind="workstation-step"
                                id={step.id}
                                templates={labelTemplates}
                                label="Label"
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Create Batch Modal
// ---------------------------------------------------------------------------

function CreateBatchModal({ workOrder, workstations, defaultWorkstationId, onClose }) {
    const remaining = Math.max((workOrder.planned_qty ?? 0) - (workOrder.produced_qty ?? 0), 0);

    const form = useForm({
        work_order_id: workOrder.id,
        target_qty: String(remaining),
        workstation_id: defaultWorkstationId ? String(defaultWorkstationId) : (workstations.length === 1 ? String(workstations[0].id) : ''),
        lot_number: '',
        auto_lot: false,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/operator/batch', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-black bg-opacity-50" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    className="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6"
                    onClick={(e) => e.stopPropagation()}
                >
                    <h3 className="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4">Create New Batch</h3>

                    <form onSubmit={submit}>
                        {/* Quantity */}
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Quantity
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                max={remaining}
                                value={form.data.target_qty}
                                onChange={(e) => form.setData('target_qty', e.target.value)}
                                className="form-input w-full"
                                required
                            />
                            <p className="mt-1 text-sm text-gray-500">Remaining: {fmtQty(remaining)}</p>
                            {form.errors.target_qty && (
                                <p className="text-red-600 text-sm mt-1">{form.errors.target_qty}</p>
                            )}
                        </div>

                        {/* Workstation */}
                        {workstations.length > 0 && (
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Workstation
                                </label>
                                {workstations.length === 1 ? (
                                    <>
                                        <input type="hidden" value={workstations[0].id} />
                                        <p className="text-sm text-gray-600 dark:text-gray-400 py-2">
                                            {workstations[0].name}
                                        </p>
                                    </>
                                ) : (
                                    <select
                                        value={form.data.workstation_id}
                                        onChange={(e) => form.setData('workstation_id', e.target.value)}
                                        className="form-input w-full"
                                    >
                                        <option value="">— Select workstation —</option>
                                        {workstations.map((ws) => (
                                            <option key={ws.id} value={ws.id}>
                                                {ws.name}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {form.errors.workstation_id && (
                                    <p className="text-red-600 text-sm mt-1">{form.errors.workstation_id}</p>
                                )}
                            </div>
                        )}

                        {/* Auto-LOT */}
                        <div className="mb-4">
                            <label className="inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.data.auto_lot}
                                    onChange={(e) => form.setData('auto_lot', e.target.checked)}
                                    className="rounded border-gray-300 text-blue-600"
                                />
                                <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    Auto-generate LOT number
                                </span>
                            </label>
                        </div>

                        {/* Manual LOT */}
                        {!form.data.auto_lot && (
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    LOT Number (manual)
                                </label>
                                <input
                                    type="text"
                                    value={form.data.lot_number}
                                    onChange={(e) => form.setData('lot_number', e.target.value)}
                                    className="form-input w-full"
                                    placeholder="Leave empty for no LOT"
                                />
                                {form.errors.lot_number && (
                                    <p className="text-red-600 text-sm mt-1">{form.errors.lot_number}</p>
                                )}
                            </div>
                        )}

                        <div className="flex gap-3 justify-end">
                            <button
                                type="button"
                                onClick={onClose}
                                className="btn-touch btn-secondary"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="btn-touch btn-primary disabled:opacity-50"
                            >
                                Create Batch
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Report Issue Modal
// ---------------------------------------------------------------------------

function ReportIssueModal({ workOrder, issueTypes, customFields = [], onClose }) {
    const form = useForm({
        work_order_id: workOrder.id,
        issue_type_id: '',
        title: '',
        description: '',
        ...customFieldInitial(),
    });

    const submit = (e) => {
        e.preventDefault();
        submitForm(form, 'post', '/operator/issue', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-black bg-opacity-50" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    className="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6"
                    onClick={(e) => e.stopPropagation()}
                >
                    <h3 className="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4">Report Issue</h3>

                    <form onSubmit={submit}>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Issue Type <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.data.issue_type_id}
                                    onChange={(e) => form.setData('issue_type_id', e.target.value)}
                                    className="form-input w-full"
                                    required
                                >
                                    <option value="">— Select type —</option>
                                    {issueTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.name}
                                            {type.is_blocking ? ' ⚠ Blocking' : ''}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.issue_type_id && (
                                    <p className="text-red-600 text-sm mt-1">{form.errors.issue_type_id}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Title <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                    className="form-input w-full"
                                    placeholder="Brief summary of the issue"
                                    required
                                    maxLength={255}
                                />
                                {form.errors.title && (
                                    <p className="text-red-600 text-sm mt-1">{form.errors.title}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Description
                                </label>
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    rows={3}
                                    className="form-input w-full"
                                    placeholder="Additional details…"
                                    maxLength={2000}
                                />
                                {form.errors.description && (
                                    <p className="text-red-600 text-sm mt-1">{form.errors.description}</p>
                                )}
                            </div>

                            {customFields.length > 0 && <CustomFields {...customFieldProps(form, customFields)} />}
                        </div>

                        <div className="flex gap-3 justify-end mt-6">
                            <button type="button" onClick={onClose} className="btn-touch btn-secondary">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="btn-touch btn-primary bg-red-600 hover:bg-red-700 disabled:opacity-50"
                            >
                                Report Issue
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export default function WorkOrderDetail() {
    const { workOrder, issueTypes = [], workstations = [], defaultWorkstationId, line, labelTemplates = [], processPhotos = [], issueCustomFields = [] } = usePage().props;

    const [createBatchOpen, setCreateBatchOpen] = useState(false);
    const [reportIssueOpen, setReportIssueOpen] = useState(false);

    const plannedQty = workOrder.planned_qty ?? 0;
    const producedQty = workOrder.produced_qty ?? 0;
    const remaining = Math.max(plannedQty - producedQty, 0);
    const pct = plannedQty > 0 ? Math.min((producedQty / plannedQty) * 100, 100) : 0;

    const canCreateBatch = !['DONE', 'CANCELLED', 'BLOCKED'].includes(workOrder.status);
    const canReportIssue = !['DONE', 'CANCELLED'].includes(workOrder.status);

    const dueDateStr = workOrder.due_date;
    const dueDatePast = dueDateStr && new Date(dueDateStr) < new Date() && workOrder.status !== 'DONE';

    return (
        <>
            <Head title={`Work Order ${workOrder.order_no}`} />

            {/* Live-refresh when the work order changes on the line */}
            {line && <LineSync lineId={line.id} reloadOnly={['workOrder']} />}

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">
                                {workOrder.order_no}
                            </h1>
                            <span className={`px-4 py-2 rounded-full text-sm font-medium ${statusBadge(workOrder.status)}`}>
                                {statusLabel(workOrder.status)}
                            </span>
                        </div>
                        {workOrder.product_type && (
                            <p className="text-gray-600 dark:text-gray-400 mt-2">{workOrder.product_type.name}</p>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        <LabelPrintMenu
                            kind="work-order"
                            id={workOrder.id}
                            templates={labelTemplates}
                            label="Print WO Label"
                        />
                        <Link href="/operator/queue" className="btn-touch btn-secondary">
                            ← Back to Queue
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Work Order Details card */}
                        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
                            <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4">Work Order Details</h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-gray-500">Order Number</p>
                                    <p className="font-medium text-gray-800 dark:text-gray-100 font-mono">{workOrder.order_no}</p>
                                </div>

                                {workOrder.product_type && (
                                    <div>
                                        <p className="text-sm text-gray-500">Product Type</p>
                                        <p className="font-medium text-gray-800 dark:text-gray-100">{workOrder.product_type.name}</p>
                                    </div>
                                )}

                                {workOrder.line && (
                                    <div>
                                        <p className="text-sm text-gray-500">Line</p>
                                        <p className="font-medium text-gray-800 dark:text-gray-100">{workOrder.line.name}</p>
                                    </div>
                                )}

                                <div>
                                    <p className="text-sm text-gray-500">Priority</p>
                                    <p className="font-medium text-gray-800 dark:text-gray-100">{workOrder.priority}</p>
                                </div>

                                <div>
                                    <p className="text-sm text-gray-500">Planned Quantity</p>
                                    <p className="font-medium text-gray-800 dark:text-gray-100">{fmtQty(plannedQty)}</p>
                                </div>

                                <div>
                                    <p className="text-sm text-gray-500">Produced Quantity</p>
                                    <p className="font-medium text-gray-800 dark:text-gray-100">
                                        {fmtQty(producedQty)}
                                        {plannedQty > 0 && (
                                            <span className="text-sm text-gray-500 ml-1">
                                                ({fmtQty((producedQty / plannedQty) * 100, 1)}%)
                                            </span>
                                        )}
                                    </p>
                                </div>

                                {dueDateStr && (
                                    <div>
                                        <p className="text-sm text-gray-500">Due Date</p>
                                        <p className={`font-medium ${dueDatePast ? 'text-red-600' : 'text-gray-800 dark:text-gray-100'}`}>
                                            {formatDate(new Date(dueDateStr), { day: '2-digit', month: 'short', year: 'numeric' })}
                                        </p>
                                    </div>
                                )}

                                {workOrder.description && (
                                    <div className="md:col-span-2">
                                        <p className="text-sm text-gray-500">Description</p>
                                        <p className="font-medium text-gray-800 dark:text-gray-100">{workOrder.description}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Recipe / BOM */}
                        <BomSection workOrder={workOrder} />

                        {/* Process reference photos (work instructions) */}
                        <ProcessPhotosSection photos={processPhotos} />

                        {/* Batches */}
                        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100">Batches</h2>
                                {canCreateBatch && (
                                    <button
                                        type="button"
                                        onClick={() => setCreateBatchOpen(true)}
                                        className="btn-touch btn-primary text-sm"
                                    >
                                        + Create Batch
                                    </button>
                                )}
                            </div>

                            {(!workOrder.batches || workOrder.batches.length === 0) ? (
                                <div className="text-center py-8 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                    <p className="text-gray-500">No batches created yet.</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {workOrder.batches.map((batch, idx) => (
                                        <BatchCard
                                            key={batch.id}
                                            batch={batch}
                                            defaultOpen={idx === 0}
                                            labelTemplates={labelTemplates}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Progress */}
                        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
                            <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Progress</h3>
                            <div className="mb-4">
                                <div className="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <span>Completion</span>
                                    <span>{fmtQty(pct, 1)}%</span>
                                </div>
                                <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                    <div
                                        className={`${pct >= 100 ? 'bg-green-500' : 'bg-blue-600'} h-4 rounded-full transition-all`}
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Planned:</span>
                                    <span className="font-medium">{fmtQty(plannedQty)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Produced:</span>
                                    <span className="font-medium">{fmtQty(producedQty)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Remaining:</span>
                                    <span className="font-medium">{fmtQty(remaining)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Issues */}
                        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100">Issues</h3>
                                {canReportIssue && (
                                    <button
                                        type="button"
                                        onClick={() => setReportIssueOpen(true)}
                                        className="text-sm text-red-600 hover:text-red-800 font-medium border border-red-200 hover:border-red-400 px-3 py-1 rounded-lg transition-colors"
                                    >
                                        + Report
                                    </button>
                                )}
                            </div>

                            {(!workOrder.issues || workOrder.issues.length === 0) ? (
                                <p className="text-sm text-gray-400 text-center py-4">No issues reported.</p>
                            ) : (
                                <div className="space-y-3">
                                    {workOrder.issues.slice(0, 5).map((issue) => (
                                        <div
                                            key={issue.id}
                                            className={`p-3 rounded-lg ${issue.issue_type?.is_blocking ? 'bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800' : 'bg-gray-50 dark:bg-gray-800/50'}`}
                                        >
                                            <div className="flex items-center justify-between mb-1">
                                                <span className="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                                    {issue.issue_type?.name}
                                                </span>
                                                <span className={`px-2 py-0.5 rounded text-xs font-medium ${issueBadge(issue.status)}`}>
                                                    {issue.status}
                                                </span>
                                            </div>
                                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{issue.title}</p>
                                            {issue.description && (
                                                <p className="text-xs text-gray-500 mt-1">
                                                    {issue.description.length > 80
                                                        ? `${issue.description.slice(0, 80)}…`
                                                        : issue.description}
                                                </p>
                                            )}
                                            <p className="text-xs text-gray-400 mt-1">
                                                {issue.reported_at
                                                    ? formatDateTime(new Date(issue.reported_at))
                                                    : ''}
                                                {issue.reported_by ? ` by ${issue.reported_by.name}` : ''}
                                            </p>
                                        </div>
                                    ))}
                                    {workOrder.issues.length > 5 && (
                                        <p className="text-xs text-gray-400 text-center">
                                            +{workOrder.issues.length - 5} more issues
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Modals */}
            {createBatchOpen && (
                <CreateBatchModal
                    workOrder={workOrder}
                    workstations={workstations}
                    defaultWorkstationId={defaultWorkstationId}
                    onClose={() => setCreateBatchOpen(false)}
                />
            )}

            {reportIssueOpen && (
                <ReportIssueModal
                    workOrder={workOrder}
                    issueTypes={issueTypes}
                    customFields={issueCustomFields}
                    onClose={() => setReportIssueOpen(false)}
                />
            )}
        </>
    );
}

WorkOrderDetail.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
