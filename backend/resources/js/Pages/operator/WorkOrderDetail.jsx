import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Badge, Button, ProgressBar, StatusPill } from '@openmes/ui';
import OperatorLayout from '../../layouts/OperatorLayout';
import LineSync from '../../components/LineSync';
import LabelPrintMenu from '../../components/LabelPrintMenu';
import CustomFields from '../../components/CustomFields';
import { customFieldInitial, customFieldProps, submitForm } from '../../lib/customFieldForm';
import { formatDate, formatDateTime, formatNumber } from '../../lib/i18n';

// Geist White restyle: light-only v1 — former `dark:` variants removed.

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fmtQty(n, decimals = 2) {
    if (n == null) return '—';
    return formatNumber(Number(n), { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/** Map domain statuses onto the design system's StatusPill states. */
function pillStatus(status) {
    const map = {
        PENDING: 'pending',
        IN_PROGRESS: 'running',
        DONE: 'done',
        BLOCKED: 'blocked',
        CANCELLED: 'done',
    };
    return map[status] ?? 'pending';
}

function issuePillStatus(status) {
    const map = {
        OPEN: 'blocked',
        ACKNOWLEDGED: 'downtime',
        RESOLVED: 'running',
    };
    return map[status] ?? 'pending';
}

function statusLabel(status) {
    if (status === 'PENDING') return 'Not Started';
    return (status ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function bomTypeBadge(type) {
    const map = {
        raw_material:  'text-om-downtime bg-om-downtime-bg',
        semi_finished: 'text-om-accent bg-om-selected',
        packaging:     'text-om-muted bg-om-chip',
    };
    return map[type] ?? 'text-om-muted bg-om-chip';
}

// Shared Geist White idiom classes
const cardCls = 'bg-om-card border border-om-line rounded-om p-6';
const sectionLabelCls = 'font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint';
const fieldLabelCls = 'block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint';
const inputCls =
    'w-full text-[13px] text-om-ink placeholder:text-om-faint bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]';
const errorCls = 'mt-[5px] text-[11.5px] text-om-blocked';

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function ChevronIcon({ open }) {
    return (
        <svg
            className={`w-5 h-5 text-om-faint transition-transform ${open ? 'rotate-180' : ''}`}
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
        </svg>
    );
}

// ---------------------------------------------------------------------------
// Modal shell — §09 idiom (radius 12, deep shadow, header hairline + mono
// subtitle, panel footer supplied by callers inside their <form>).
// ---------------------------------------------------------------------------

function ModalShell({ title, subtitle, onClose, children }) {
    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    className="relative w-full max-w-md overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="flex items-center justify-between border-b border-om-line2 px-[18px] py-4">
                        <div>
                            <div className="text-[15px] font-semibold text-om-ink">{title}</div>
                            {subtitle != null && (
                                <div className="mt-[3px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{subtitle}</div>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="cursor-pointer text-[18px] leading-none text-om-faint hover:text-om-muted"
                        >
                            ×
                        </button>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}

const modalFooterCls = 'flex justify-end gap-[9px] border-t border-om-line2 bg-om-panel px-[18px] py-[14px]';

// ---------------------------------------------------------------------------
// BOM accordion
// ---------------------------------------------------------------------------

function BomSection({ workOrder }) {
    const [open, setOpen] = useState(false);
    const bom = workOrder.process_snapshot?.bom;
    if (!bom || bom.length === 0) return null;

    return (
        <div className={cardCls}>
            <button
                type="button"
                className="flex justify-between items-center w-full text-left cursor-pointer"
                onClick={() => setOpen((v) => !v)}
            >
                <h2 className={sectionLabelCls}>Recipe / Materials</h2>
                <div className="flex items-center gap-2">
                    <Badge variant="neutral">{bom.length} items</Badge>
                    <ChevronIcon open={open} />
                </div>
            </button>

            {open && (
                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint">
                                <th className="pb-2 font-normal">Material</th>
                                <th className="pb-2 font-normal">Type</th>
                                <th className="pb-2 font-normal text-right">Per Unit</th>
                                <th className="pb-2 font-normal text-right">
                                    Total ({Math.round(workOrder.planned_qty)} pcs)
                                </th>
                                <th className="pb-2 font-normal">Step</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-om-line2">
                            {bom.map((item, idx) => {
                                const base = item.quantity_per_unit * workOrder.planned_qty;
                                const scrap = base * (item.scrap_percentage / 100);
                                const total = base + scrap;
                                return (
                                    <tr key={idx}>
                                        <td className="py-2">
                                            <span className="font-medium text-om-ink">{item.material_name}</span>
                                            <span className="text-xs text-om-faint font-mono ml-1">{item.material_code}</span>
                                        </td>
                                        <td className="py-2">
                                            <span className={`px-2 py-0.5 rounded-[20px] font-mono text-[10px] uppercase tracking-[0.06em] ${bomTypeBadge(item.material_type)}`}>
                                                {item.material_type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                                            </span>
                                        </td>
                                        <td className="py-2 text-right font-mono text-om-ink">
                                            {item.quantity_per_unit} {item.unit_of_measure}
                                        </td>
                                        <td className="py-2 text-right font-mono font-medium text-om-ink">
                                            {fmtQty(total)} {item.unit_of_measure}
                                            {item.scrap_percentage > 0 && (
                                                <span className="text-xs text-om-faint ml-1">(+{item.scrap_percentage}% scrap)</span>
                                            )}
                                        </td>
                                        <td className="py-2 font-mono text-[12px] text-om-muted">
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
        <div className={cardCls}>
            <button
                type="button"
                className="flex justify-between items-center w-full text-left cursor-pointer"
                onClick={() => setOpen((v) => !v)}
            >
                <h2 className={sectionLabelCls}>Work Instructions</h2>
                <div className="flex items-center gap-2">
                    <Badge variant="neutral">{photos.length} photos</Badge>
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
                                className="block w-full cursor-pointer"
                                title={photo.caption || ''}
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.caption || 'Work instruction'}
                                    loading="lazy"
                                    className="w-full h-32 object-cover rounded-om-sm border border-om-line bg-om-chip"
                                />
                            </button>
                            {photo.caption && (
                                <figcaption className="mt-1 text-xs text-om-muted truncate">
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
                            className="max-w-full max-h-[80vh] rounded-om shadow-2xl"
                        />
                        {lightbox.caption && (
                            <figcaption className="text-white/90 text-sm mt-3 text-center">{lightbox.caption}</figcaption>
                        )}
                    </figure>
                    <button
                        type="button"
                        onClick={() => setLightbox(null)}
                        className="absolute top-5 right-5 text-white/80 hover:text-white text-3xl leading-none cursor-pointer"
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
        <div className="mt-3 p-4 bg-om-panel border border-om-line2 rounded-om-sm">
            <form onSubmit={submit}>
                <div className="mb-3">
                    <label className={fieldLabelCls}>Production Quantity</label>
                    <input
                        type="number"
                        step="0.01"
                        value={productionQty}
                        onChange={(e) => setProductionQty(e.target.value)}
                        className={`${inputCls} font-mono`}
                        placeholder="Current production qty"
                    />
                </div>

                {[1, 2, 3].map((s) => {
                    const dimIdx = (s - 1) * 2;
                    const fitIdx = (s - 1) * 2 + 1;
                    return (
                        <div key={s} className="mb-2 p-2 bg-om-card border border-om-line2 rounded-om-sm">
                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">Sample #{s}</p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={samples[dimIdx].value_numeric}
                                        onChange={(e) => updateSample(dimIdx, 'value_numeric', e.target.value)}
                                        className={`${inputCls} font-mono`}
                                        placeholder="Dimension"
                                        required
                                    />
                                </div>
                                <div>
                                    <select
                                        value={samples[fitIdx].value_boolean}
                                        onChange={(e) => updateSample(fitIdx, 'value_boolean', e.target.value)}
                                        className={inputCls}
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
                    <Button type="submit" variant="accent" disabled={processing} className="px-5 py-3 text-[14px]">
                        Submit QC
                    </Button>
                    <Button variant="secondary" onClick={onClose} className="px-5 py-3 text-[14px]">
                        Cancel
                    </Button>
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
        <div className="mt-3 p-4 bg-om-panel border border-om-line2 rounded-om-sm">
            <form onSubmit={submit}>
                {checks.map(([field, label]) => (
                    <label key={field} className="flex items-center gap-2 mb-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={form.data[field]}
                            onChange={(e) => form.setData(field, e.target.checked)}
                            className="size-[18px] rounded-[5px] border-om-line accent-om-accent"
                        />
                        <span className="text-[13px] text-om-ink">{label}</span>
                    </label>
                ))}
                <div className="flex gap-2 mt-2">
                    <Button type="submit" variant="accent" disabled={form.processing} className="px-5 py-3 text-[14px]">
                        Submit Checklist
                    </Button>
                    <Button variant="secondary" onClick={onClose} className="px-5 py-3 text-[14px]">
                        Cancel
                    </Button>
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
        <div className="mt-3 p-4 bg-om-panel border border-om-line2 rounded-om-sm">
            <div className="mb-3">
                <label className={fieldLabelCls}>
                    Scrap quantity (optional)
                </label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.data.scrap_qty}
                    onChange={(e) => form.setData('scrap_qty', e.target.value)}
                    className={`${inputCls} w-32 font-mono`}
                    placeholder="0"
                />
            </div>
            <p className="text-sm mb-3 text-om-muted">Release this batch?</p>
            <div className="flex gap-3 flex-wrap">
                <Button
                    variant="secondary"
                    disabled={form.processing}
                    onClick={() => submitWith('for_production')}
                    className="px-5 py-3 text-[14px]"
                >
                    For Production
                </Button>
                <Button
                    variant="accent"
                    disabled={form.processing}
                    onClick={() => submitWith('for_sale')}
                    className="px-5 py-3 text-[14px]"
                >
                    For Sale
                </Button>
                <Button variant="secondary" onClick={onClose} className="px-5 py-3 text-[14px]">
                    Cancel
                </Button>
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
            <Button
                variant="secondary"
                disabled={processing}
                onClick={handleClick}
                className="px-5 py-3 text-[14px]"
            >
                Confirm Parameters
            </Button>
            {lastConfirm && (
                <span className="font-mono text-[11px] text-om-running">
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
        <div className="border-t border-om-line2 pt-4 space-y-3">
            <h4 className={sectionLabelCls}>
                Production Controls
            </h4>

            {/* Confirm Parameters */}
            <ConfirmParametersRow batch={batch} />

            {/* Quality Check */}
            <div>
                <div className="flex items-center gap-3">
                    <Button
                        variant="secondary"
                        onClick={() => setShowQc((v) => !v)}
                        className="px-5 py-3 text-[14px]"
                    >
                        Quality Check ({qcCount})
                    </Button>
                    {qcCount < 3 ? (
                        <span className="font-mono text-[11px] text-om-downtime">{3 - qcCount} more needed</span>
                    ) : (
                        <span className="font-mono text-[11px] text-om-running">Min. requirement met</span>
                    )}
                </div>
                {showQc && <QualityCheckForm batch={batch} onClose={() => setShowQc(false)} />}
            </div>

            {/* Packaging Checklist */}
            {!hasChecklist ? (
                <div>
                    <Button
                        variant="secondary"
                        onClick={() => setShowChecklist((v) => !v)}
                        className="px-5 py-3 text-[14px]"
                    >
                        Packaging Checklist
                    </Button>
                    {showChecklist && (
                        <PackagingChecklistForm batch={batch} onClose={() => setShowChecklist(false)} />
                    )}
                </div>
            ) : (
                <div className="text-sm">
                    <span className={batch.packaging_checklist.all_passed ? 'text-om-running' : 'text-om-blocked'}>
                        Packaging: {batch.packaging_checklist.all_passed ? 'All passed' : 'Some items failed'}
                    </span>
                </div>
            )}

            {/* Release */}
            {batch.status === 'DONE' && !isReleased && (
                <div>
                    <Button
                        variant="accent"
                        onClick={() => setShowRelease((v) => !v)}
                        className="px-6 py-3.5 text-[15px]"
                    >
                        Release Batch
                    </Button>
                    {showRelease && <ReleaseForm batch={batch} onClose={() => setShowRelease(false)} />}
                </div>
            )}

            {/* Series Report after release (admin-only route: admin/batches/{id}/report) */}
            {isReleased && usePage().props.auth?.user?.roles?.includes('Admin') && (
                <a
                    href={`/admin/batches/${batch.id}/report`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center justify-center rounded-om-sm border border-om-line bg-transparent px-5 py-3 text-[14px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
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

function BatchCard({ batch, defaultOpen, labelTemplates = [], stepPhotos = {} }) {
    const [expanded, setExpanded] = useState(defaultOpen);
    const showControls = batch.status === 'IN_PROGRESS' || batch.status === 'DONE';

    const releaseLabel =
        batch.release_type === 'for_sale' ? 'For Sale' : 'For Production';
    const isReleased = !!(batch.released_at || batch.released);

    return (
        <div className="border border-om-line rounded-om p-4 bg-om-card">
            {/* Header row */}
            <div className="flex items-center gap-3">
                <button
                    type="button"
                    className="flex flex-1 justify-between items-center text-left cursor-pointer"
                    onClick={() => setExpanded((v) => !v)}
                >
                    <div className="flex items-center gap-4">
                        <h3 className="text-[16px] font-semibold tracking-[-0.01em] text-om-ink">
                            Batch #{batch.batch_number}
                        </h3>
                        <StatusPill status={pillStatus(batch.status)} label={statusLabel(batch.status)} />
                        <span className="font-mono text-[13px] text-om-muted">
                            {fmtQty(batch.produced_qty)} / {fmtQty(batch.target_qty)}
                        </span>
                    </div>
                    <svg
                        className={`w-6 h-6 text-om-faint transition-transform ${expanded ? 'rotate-180' : ''}`}
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
                    <div className="flex flex-wrap gap-4 text-sm bg-om-panel border border-om-line2 p-3 rounded-om-sm">
                        {batch.lot_number && (
                            <span className="font-medium text-om-ink">
                                LOT: <span className="font-mono text-om-accent">{batch.lot_number}</span>
                            </span>
                        )}
                        {batch.workstation && (
                            <span className="font-medium text-om-ink">Workstation: {batch.workstation.name}</span>
                        )}
                        {isReleased && (
                            <span className="text-om-running font-medium">
                                Released ({releaseLabel})
                            </span>
                        )}
                        {batch.expiry_date && (
                            <span className="font-mono text-[13px] text-om-muted">Expiry: {batch.expiry_date}</span>
                        )}
                    </div>

                    {/* Steps */}
                    <BatchStepList steps={batch.steps ?? []} labelTemplates={labelTemplates} stepPhotos={stepPhotos} />

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

function BatchStepList({ steps, labelTemplates = [], stepPhotos = {} }) {
    const [inflightStepId, setInflightStepId] = useState(null);
    const [photoZoom, setPhotoZoom] = useState(null);

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
            <h4 className={`${sectionLabelCls} mb-2`}>Steps</h4>
            <div className="space-y-2">
                {steps.map((step) => {
                    const isInflight = inflightStepId === step.id;
                    const photo = stepPhotos[step.step_number];
                    return (
                        <div key={step.id} className="flex items-center gap-3 p-3 bg-om-panel border border-om-line2 rounded-om-sm">
                            <span className="w-7 h-7 flex-shrink-0 flex items-center justify-center rounded-full font-mono text-[11px] bg-om-chip text-om-muted">
                                {step.step_number}
                            </span>
                            {photo && (
                                <button
                                    type="button"
                                    onClick={() => setPhotoZoom(photo)}
                                    className="flex-shrink-0 cursor-pointer"
                                    title={photo.caption || 'Step photo'}
                                >
                                    <img
                                        src={photo.url}
                                        alt={photo.caption || 'Step photo'}
                                        loading="lazy"
                                        className="w-12 h-12 object-cover rounded-om-sm border border-om-line bg-om-chip"
                                    />
                                </button>
                            )}
                            <span className="flex-1 text-sm font-medium text-om-ink">
                                {step.name}
                            </span>

                            {/* Status label for terminal states */}
                            {step.status === 'DONE' && (
                                <span className="font-mono text-[11px] text-om-done whitespace-nowrap">
                                    Done{step.completed_by ? ` by ${step.completed_by.name}` : ''}
                                </span>
                            )}
                            {step.status === 'SKIPPED' && (
                                <span className="font-mono text-[11px] text-om-faint whitespace-nowrap">Skipped</span>
                            )}
                            {step.status === 'IN_PROGRESS' && !inflightStepId && (
                                <span className="font-mono text-[11px] text-om-running whitespace-nowrap">
                                    In progress{step.started_by ? ` by ${step.started_by.name}` : ''}
                                </span>
                            )}
                            {/* Fallback for older data without explicit status field */}
                            {!step.status && step.completed_at && (
                                <span className="font-mono text-[11px] text-om-done whitespace-nowrap">
                                    Done{step.completed_by ? ` by ${step.completed_by.name}` : ''}
                                </span>
                            )}
                            {!step.status && !step.completed_at && step.started_at && (
                                <span className="font-mono text-[11px] text-om-running whitespace-nowrap">
                                    In progress{step.started_by ? ` by ${step.started_by.name}` : ''}
                                </span>
                            )}

                            {/* Action buttons */}
                            {step.status === 'PENDING' && (
                                <Button
                                    variant="accent"
                                    disabled={isInflight}
                                    onClick={() => handleStepAction(step, 'start')}
                                    className="px-6 py-3.5 text-[15px] whitespace-nowrap"
                                >
                                    {isInflight ? '…' : 'Start'}
                                </Button>
                            )}
                            {step.status === 'IN_PROGRESS' && (
                                <Button
                                    variant="primary"
                                    disabled={isInflight}
                                    onClick={() => handleStepAction(step, 'complete')}
                                    className="px-6 py-3.5 text-[15px] whitespace-nowrap"
                                >
                                    {isInflight ? '…' : 'Complete'}
                                </Button>
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

            {photoZoom && (
                <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-6" onClick={() => setPhotoZoom(null)}>
                    <figure className="max-w-3xl max-h-full m-0" onClick={(e) => e.stopPropagation()}>
                        <img src={photoZoom.url} alt={photoZoom.caption || 'Step photo'} className="max-w-full max-h-[80vh] rounded-om shadow-2xl" />
                        {photoZoom.caption && (
                            <figcaption className="text-white/90 text-sm mt-3 text-center">{photoZoom.caption}</figcaption>
                        )}
                    </figure>
                </div>
            )}
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
        <ModalShell title="Create New Batch" subtitle={workOrder.order_no} onClose={onClose}>
            <form onSubmit={submit}>
                <div className="px-[18px] py-4">
                    {/* Quantity */}
                    <div className="mb-4">
                        <label className={fieldLabelCls}>
                            Quantity
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            max={remaining}
                            value={form.data.target_qty}
                            onChange={(e) => form.setData('target_qty', e.target.value)}
                            className={`${inputCls} font-mono text-[15px]`}
                            required
                        />
                        <p className="mt-1 font-mono text-[11px] text-om-faint">Remaining: {fmtQty(remaining)}</p>
                        {form.errors.target_qty && (
                            <p className={errorCls}>{form.errors.target_qty}</p>
                        )}
                    </div>

                    {/* Workstation */}
                    {workstations.length > 0 && (
                        <div className="mb-4">
                            <label className={fieldLabelCls}>
                                Workstation
                            </label>
                            {workstations.length === 1 ? (
                                <>
                                    <input type="hidden" value={workstations[0].id} />
                                    <p className="text-sm text-om-muted py-2">
                                        {workstations[0].name}
                                    </p>
                                </>
                            ) : (
                                <select
                                    value={form.data.workstation_id}
                                    onChange={(e) => form.setData('workstation_id', e.target.value)}
                                    className={inputCls}
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
                                <p className={errorCls}>{form.errors.workstation_id}</p>
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
                                className="size-[18px] rounded-[5px] border-om-line accent-om-accent"
                            />
                            <span className="ml-2 text-[13px] text-om-ink">
                                Auto-generate LOT number
                            </span>
                        </label>
                    </div>

                    {/* Manual LOT */}
                    {!form.data.auto_lot && (
                        <div className="mb-4">
                            <label className={fieldLabelCls}>
                                LOT Number (manual)
                            </label>
                            <input
                                type="text"
                                value={form.data.lot_number}
                                onChange={(e) => form.setData('lot_number', e.target.value)}
                                className={`${inputCls} font-mono`}
                                placeholder="Leave empty for no LOT"
                            />
                            {form.errors.lot_number && (
                                <p className={errorCls}>{form.errors.lot_number}</p>
                            )}
                        </div>
                    )}
                </div>

                <div className={modalFooterCls}>
                    <Button
                        variant="secondary"
                        onClick={onClose}
                        className="px-6 py-4 text-[15px] font-semibold"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="accent"
                        disabled={form.processing}
                        className="px-6 py-4 text-[15px] font-semibold"
                    >
                        Create Batch
                    </Button>
                </div>
            </form>
        </ModalShell>
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
        <ModalShell title="Report Issue" subtitle={workOrder.order_no} onClose={onClose}>
            <form onSubmit={submit}>
                <div className="px-[18px] py-4 space-y-4">
                    <div>
                        <label className={fieldLabelCls}>
                            Issue Type <span className="text-om-blocked">*</span>
                        </label>
                        <select
                            value={form.data.issue_type_id}
                            onChange={(e) => form.setData('issue_type_id', e.target.value)}
                            className={inputCls}
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
                            <p className={errorCls}>{form.errors.issue_type_id}</p>
                        )}
                    </div>

                    <div>
                        <label className={fieldLabelCls}>
                            Title <span className="text-om-blocked">*</span>
                        </label>
                        <input
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            className={inputCls}
                            placeholder="Brief summary of the issue"
                            required
                            maxLength={255}
                        />
                        {form.errors.title && (
                            <p className={errorCls}>{form.errors.title}</p>
                        )}
                    </div>

                    <div>
                        <label className={fieldLabelCls}>
                            Description
                        </label>
                        <textarea
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            rows={3}
                            className={`${inputCls} resize-none`}
                            placeholder="Additional details…"
                            maxLength={2000}
                        />
                        {form.errors.description && (
                            <p className={errorCls}>{form.errors.description}</p>
                        )}
                    </div>

                    {customFields.length > 0 && <CustomFields {...customFieldProps(form, customFields)} />}
                </div>

                <div className={modalFooterCls}>
                    <Button variant="secondary" onClick={onClose} className="px-6 py-4 text-[15px] font-semibold">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="danger"
                        disabled={form.processing}
                        className="px-6 py-4 text-[15px] font-semibold"
                    >
                        Report Issue
                    </Button>
                </div>
            </form>
        </ModalShell>
    );
}

// ---------------------------------------------------------------------------
// Report Scrap Modal
// ---------------------------------------------------------------------------

function ReportScrapModal({ workOrder, scrapReasons, onClose }) {
    const form = useForm({
        work_order_id: workOrder.id,
        scrap_reason_id: '',
        quantity: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/operator/scrap', { onSuccess: onClose });
    };

    return (
        <ModalShell title="Report Scrap" subtitle={workOrder.order_no} onClose={onClose}>
            <form onSubmit={submit}>
                <div className="px-[18px] py-4 space-y-4">
                    <div>
                        <label className={fieldLabelCls}>
                            Reason <span className="text-om-blocked">*</span>
                        </label>
                        <select
                            value={form.data.scrap_reason_id}
                            onChange={(e) => form.setData('scrap_reason_id', e.target.value)}
                            className={inputCls}
                            required
                        >
                            <option value="">— Select reason —</option>
                            {scrapReasons.map((reason) => (
                                <option key={reason.id} value={reason.id}>
                                    {reason.code} — {reason.name}
                                </option>
                            ))}
                        </select>
                        {form.errors.scrap_reason_id && (
                            <p className={errorCls}>{form.errors.scrap_reason_id}</p>
                        )}
                    </div>

                    <div>
                        <label className={fieldLabelCls}>
                            Quantity <span className="text-om-blocked">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={form.data.quantity}
                            onChange={(e) => form.setData('quantity', e.target.value)}
                            className={`${inputCls} font-mono text-[15px]`}
                            placeholder="0"
                            required
                        />
                        {form.errors.quantity && (
                            <p className={errorCls}>{form.errors.quantity}</p>
                        )}
                    </div>

                    <div>
                        <label className={fieldLabelCls}>
                            Notes
                        </label>
                        <textarea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={3}
                            className={`${inputCls} resize-none`}
                            placeholder="Additional details…"
                            maxLength={2000}
                        />
                        {form.errors.notes && (
                            <p className={errorCls}>{form.errors.notes}</p>
                        )}
                    </div>
                </div>

                <div className={modalFooterCls}>
                    <Button variant="secondary" onClick={onClose} className="px-6 py-4 text-[15px] font-semibold">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="danger"
                        disabled={form.processing}
                        className="px-6 py-4 text-[15px] font-semibold"
                    >
                        Report Scrap
                    </Button>
                </div>
            </form>
        </ModalShell>
    );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export default function WorkOrderDetail() {
    const { workOrder, issueTypes = [], scrapReasons = [], workstations = [], issueCustomFields = [], defaultWorkstationId, line, labelTemplates = [], processPhotos = [], stepPhotos = {} } = usePage().props;

    const [createBatchOpen, setCreateBatchOpen] = useState(false);
    const [reportIssueOpen, setReportIssueOpen] = useState(false);
    const [reportScrapOpen, setReportScrapOpen] = useState(false);

    const plannedQty = workOrder.planned_qty ?? 0;
    const producedQty = workOrder.produced_qty ?? 0;
    const remaining = Math.max(plannedQty - producedQty, 0);
    const pct = plannedQty > 0 ? Math.min((producedQty / plannedQty) * 100, 100) : 0;

    const canCreateBatch = !['DONE', 'CANCELLED', 'BLOCKED'].includes(workOrder.status);
    const canReportIssue = !['DONE', 'CANCELLED'].includes(workOrder.status);
    const canReportScrap = scrapReasons.length > 0 && !['DONE', 'CANCELLED'].includes(workOrder.status);

    const scrapEntries = workOrder.scrap_entries ?? [];
    const totalScrap = scrapEntries.reduce((sum, e) => sum + Number(e.quantity ?? 0), 0);
    const qualityPct = producedQty > 0 ? (Math.max(0, producedQty - totalScrap) / producedQty) * 100 : null;

    const dueDateStr = workOrder.due_date;
    const dueDatePast = dueDateStr && new Date(dueDateStr) < new Date() && workOrder.status !== 'DONE';

    return (
        <>
            <Head title={`Work Order ${workOrder.order_no}`} />

            {/* Live-refresh when the work order changes on the line */}
            {line && <LineSync lineId={line.id} reloadOnly={['workOrder']} />}

            <div className="max-w-7xl mx-auto">
                {/* Header — active-WO hero idiom: mono order code + status pill */}
                <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="font-mono text-[28px] font-semibold tracking-[-0.02em] text-om-ink">
                                {workOrder.order_no}
                            </h1>
                            <StatusPill status={pillStatus(workOrder.status)} label={statusLabel(workOrder.status)} />
                        </div>
                        {workOrder.product_type && (
                            <p className="text-om-muted mt-2 text-[15px]">{workOrder.product_type.name}</p>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        <LabelPrintMenu
                            kind="work-order"
                            id={workOrder.id}
                            templates={labelTemplates}
                            label="Print WO Label"
                        />
                        <Link
                            href="/operator/queue"
                            className="inline-flex items-center justify-center rounded-om-sm border border-om-line bg-om-card px-5 py-3 text-sm font-semibold text-om-ink hover:bg-om-chip transition-colors"
                        >
                            ← Back to Queue
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Work Order Details card */}
                        <div className={cardCls}>
                            <h2 className={`${sectionLabelCls} mb-4`}>Work Order Details</h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p className={fieldLabelCls}>Order Number</p>
                                    <p className="font-mono text-[15px] font-medium text-om-ink">{workOrder.order_no}</p>
                                </div>

                                {workOrder.product_type && (
                                    <div>
                                        <p className={fieldLabelCls}>Product Type</p>
                                        <p className="font-medium text-om-ink">{workOrder.product_type.name}</p>
                                    </div>
                                )}

                                {workOrder.line && (
                                    <div>
                                        <p className={fieldLabelCls}>Line</p>
                                        <p className="font-medium text-om-ink">{workOrder.line.name}</p>
                                    </div>
                                )}

                                <div>
                                    <p className={fieldLabelCls}>Priority</p>
                                    <p className="font-mono text-[15px] font-medium text-om-ink">{workOrder.priority}</p>
                                </div>

                                <div>
                                    <p className={fieldLabelCls}>Planned Quantity</p>
                                    <p className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink">{fmtQty(plannedQty)}</p>
                                </div>

                                <div>
                                    <p className={fieldLabelCls}>Produced Quantity</p>
                                    <p className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink">
                                        {fmtQty(producedQty)}
                                        {plannedQty > 0 && (
                                            <span className="font-mono text-[13px] text-om-faint ml-1">
                                                ({fmtQty((producedQty / plannedQty) * 100, 1)}%)
                                            </span>
                                        )}
                                    </p>
                                </div>

                                {dueDateStr && (
                                    <div>
                                        <p className={fieldLabelCls}>Due Date</p>
                                        <p className={`font-mono text-[15px] font-medium ${dueDatePast ? 'text-om-blocked' : 'text-om-ink'}`}>
                                            {formatDate(new Date(dueDateStr), { day: '2-digit', month: 'short', year: 'numeric' })}
                                        </p>
                                    </div>
                                )}

                                {workOrder.description && (
                                    <div className="md:col-span-2">
                                        <p className={fieldLabelCls}>Description</p>
                                        <p className="font-medium text-om-ink">{workOrder.description}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Recipe / BOM */}
                        <BomSection workOrder={workOrder} />

                        {/* Process reference photos (work instructions) */}
                        <ProcessPhotosSection photos={processPhotos} />

                        {/* Batches */}
                        <div className={cardCls}>
                            <div className="flex justify-between items-center mb-4">
                                <h2 className={sectionLabelCls}>Batches</h2>
                                {canCreateBatch && (
                                    <Button
                                        variant="accent"
                                        onClick={() => setCreateBatchOpen(true)}
                                        className="px-5 py-3 text-[14px]"
                                    >
                                        + Create Batch
                                    </Button>
                                )}
                            </div>

                            {(!workOrder.batches || workOrder.batches.length === 0) ? (
                                <div className="text-center py-8 bg-om-panel border border-om-line2 rounded-om-sm">
                                    <p className="text-sm text-om-faint">No batches created yet.</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {workOrder.batches.map((batch, idx) => (
                                        <BatchCard
                                            key={batch.id}
                                            batch={batch}
                                            defaultOpen={idx === 0}
                                            labelTemplates={labelTemplates}
                                            stepPhotos={stepPhotos}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Progress */}
                        <div className={cardCls}>
                            <h3 className={`${sectionLabelCls} mb-4`}>Progress</h3>
                            <div className="mb-4">
                                <div className="flex justify-between items-baseline mb-2">
                                    <span className="font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint">Completion</span>
                                    <span className="font-mono text-[13px] text-om-ink">{fmtQty(pct, 1)}%</span>
                                </div>
                                <ProgressBar value={pct} color={pct >= 100 ? 'var(--color-om-running)' : undefined} />
                            </div>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between items-baseline">
                                    <span className="font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint">Planned:</span>
                                    <span className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink">{fmtQty(plannedQty)}</span>
                                </div>
                                <div className="flex justify-between items-baseline">
                                    <span className="font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint">Produced:</span>
                                    <span className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink">{fmtQty(producedQty)}</span>
                                </div>
                                <div className="flex justify-between items-baseline">
                                    <span className="font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint">Remaining:</span>
                                    <span className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-accent">{fmtQty(remaining)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Issues */}
                        <div className={cardCls}>
                            <div className="flex justify-between items-center mb-4">
                                <h3 className={sectionLabelCls}>Issues</h3>
                                {canReportIssue && (
                                    <Button
                                        variant="danger"
                                        onClick={() => setReportIssueOpen(true)}
                                        className="px-4 py-2.5 text-[13px]"
                                    >
                                        + Report
                                    </Button>
                                )}
                            </div>

                            {(!workOrder.issues || workOrder.issues.length === 0) ? (
                                <p className="text-sm text-om-faint text-center py-4">No issues reported.</p>
                            ) : (
                                <div className="space-y-3">
                                    {workOrder.issues.slice(0, 5).map((issue) => (
                                        <div
                                            key={issue.id}
                                            className={`p-3 rounded-om-sm border ${issue.issue_type?.is_blocking ? 'bg-om-blocked-bg/60 border-om-blocked/20' : 'bg-om-panel border-om-line2'}`}
                                        >
                                            <div className="flex items-center justify-between mb-1">
                                                <span className="text-xs font-semibold text-om-ink">
                                                    {issue.issue_type?.name}
                                                </span>
                                                <StatusPill status={issuePillStatus(issue.status)} label={issue.status} />
                                            </div>
                                            <p className="text-sm font-medium text-om-ink">{issue.title}</p>
                                            {issue.description && (
                                                <p className="text-xs text-om-muted mt-1">
                                                    {issue.description.length > 80
                                                        ? `${issue.description.slice(0, 80)}…`
                                                        : issue.description}
                                                </p>
                                            )}
                                            <p className="font-mono text-[10px] text-om-faint mt-1">
                                                {issue.reported_at
                                                    ? formatDateTime(new Date(issue.reported_at))
                                                    : ''}
                                                {issue.reported_by ? ` by ${issue.reported_by.name}` : ''}
                                            </p>
                                        </div>
                                    ))}
                                    {workOrder.issues.length > 5 && (
                                        <p className="font-mono text-[10px] text-om-faint text-center">
                                            +{workOrder.issues.length - 5} more issues
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Scrap */}
                        <div className={cardCls}>
                            <div className="flex justify-between items-center mb-4">
                                <h3 className={sectionLabelCls}>Scrap</h3>
                                {canReportScrap && (
                                    <button
                                        type="button"
                                        onClick={() => setReportScrapOpen(true)}
                                        className="inline-flex items-center justify-center rounded-om-sm bg-om-downtime-bg px-4 py-2.5 text-[13px] font-semibold text-om-downtime hover:bg-[#f5e7c8] transition-colors cursor-pointer"
                                    >
                                        + Report
                                    </button>
                                )}
                            </div>

                            <div className="flex justify-between items-baseline text-sm mb-2">
                                <span className="font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint">Total scrap:</span>
                                <span className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink">{fmtQty(totalScrap)}</span>
                            </div>
                            {qualityPct !== null && (
                                <div className="flex justify-between items-baseline text-sm mb-3">
                                    <span className="font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint">Quality:</span>
                                    <span className={`font-mono text-[15px] font-medium ${qualityPct < 100 ? 'text-om-downtime' : 'text-om-running'}`}>
                                        {qualityPct.toFixed(1)}%
                                    </span>
                                </div>
                            )}

                            {scrapEntries.length === 0 ? (
                                <p className="text-sm text-om-faint text-center py-4">No scrap reported.</p>
                            ) : (
                                <div className="space-y-2">
                                    {scrapEntries.slice(0, 5).map((entry) => (
                                        <div key={entry.id} className="p-3 rounded-om-sm bg-om-panel border border-om-line2">
                                            <div className="flex items-center justify-between mb-1">
                                                <span className="text-xs font-semibold text-om-ink">
                                                    {entry.scrap_reason?.name ?? 'Unknown'}
                                                </span>
                                                <span className="font-mono text-[13px] font-medium text-om-ink">{fmtQty(entry.quantity)}</span>
                                            </div>
                                            {entry.notes && (
                                                <p className="text-xs text-om-muted">
                                                    {entry.notes.length > 80 ? `${entry.notes.slice(0, 80)}…` : entry.notes}
                                                </p>
                                            )}
                                            <p className="font-mono text-[10px] text-om-faint mt-1">
                                                {entry.reported_at ? new Date(entry.reported_at).toLocaleString() : ''}
                                                {entry.reported_by ? ` by ${entry.reported_by.name}` : ''}
                                            </p>
                                        </div>
                                    ))}
                                    {scrapEntries.length > 5 && (
                                        <p className="font-mono text-[10px] text-om-faint text-center">+{scrapEntries.length - 5} more</p>
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

            {reportScrapOpen && (
                <ReportScrapModal
                    workOrder={workOrder}
                    scrapReasons={scrapReasons}
                    onClose={() => setReportScrapOpen(false)}
                />
            )}
        </>
    );
}

WorkOrderDetail.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
