import { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Badge, Button, Checkbox, Dropdown, Icon, StatusBadge, useToast } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import OperatorLayout from '../../layouts/OperatorLayout';
import AppDataTable from '../../components/AppDataTable';
import LineSync from '../../components/LineSync';
import LabelPrintMenu from '../../components/LabelPrintMenu';
import CustomFields from '../../components/CustomFields';
import Tooltip from '../../components/Tooltip';
import RoutingGraph from '../../components/flow/RoutingGraph';
import QuantityField from '../../components/QuantityField';
import { Hook } from '../../lib/hooks';
import EngineeringViewerModal from '../../components/EngineeringViewerModal';
import { packageMeta, isInteractive, formatBytes } from '../../components/engineeringDocuments';
import { apiGet, apiCall } from '../../lib/http';
import { customFieldInitial, customFieldProps, submitForm } from '../../lib/customFieldForm';
import { __, formatDate, formatDateTime, formatNumber } from '../../lib/i18n';
import { readParam, writeParams } from '../../lib/urlState';
import { serialStatusBadge, serialStatusLabel } from '../../lib/serialStatus';
import { ConfirmTimesModal, LotPickModal, ModalShell } from '../../components/operator/StepModals';
import { pickCurrentStep } from '../../components/operator/ExecutionPanel';
import { historyTitle } from '../admin/traceability/results';
import { fmtQty as qtyS, monoLabelCls, pillCls, priorityTag, statusMeta } from '../../components/operator/operatorUi';

// Geist White restyle: light-only v1 — former `dark:` variants removed.

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fmtQty(n, decimals = 2) {
    if (n == null) return '—';
    return formatNumber(Number(n), { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
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
const sectionLabelCls = 'font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint';

/**
 * The order's serialised units: PSN, serial number, status and the last thing
 * that happened to each (started at the first bench, label applied, tested, packed ...).
 * The step counters above are quantities the benches log; this is the pieces.
 */
function SerialUnitsSection({ data }) {
    const units = data?.units ?? [];
    const columns = useMemo(() => [
        { id: 'serial_no', accessorKey: 'serial_no', header: __('Serial number'), cell: ({ row }) => <span className="whitespace-nowrap font-mono text-[12.5px] font-semibold text-om-ink">{row.original.serial_no ?? '—'}</span> },
        { id: 'psn', accessorKey: 'psn', header: __('PSN'), cell: ({ row }) => <span className="whitespace-nowrap font-mono text-[12px] text-om-muted">{row.original.psn ?? '—'}</span> },
        { id: 'status', accessorFn: (r) => serialStatusLabel(r.status), header: __('Status'), cell: ({ row }) => <StatusBadge size="sm" {...serialStatusBadge(row.original.status)} /> },
        { id: 'last', accessorFn: (r) => (r.last ? historyTitle(r.last) : ''), header: __('Last event'), cell: ({ row }) => {
            const l = row.original.last;
            return l ? (
                <span className="flex flex-col">
                    <span className="text-[12.5px] text-om-ink">{historyTitle(l)}</span>
                    <span className="font-mono text-[11px] text-om-faint">{formatDateTime(l.at)}</span>
                </span>
            ) : '—';
        } },
        { id: 'packed', accessorFn: (r) => [r.carton, r.pallet].filter(Boolean).join(' · '), header: __('Carton / pallet'), cell: ({ row }) => <span className="whitespace-nowrap font-mono text-[11.5px] text-om-muted">{[row.original.carton, row.original.pallet].filter(Boolean).join(' · ') || '—'}</span> },
    ], []);
    if (!data?.total) {
        return (
            <div className="border border-om-line2 rounded-[10px] overflow-hidden">
                <div className="grid grid-cols-[150px_150px_1fr_140px] gap-3 px-3.5 py-[9px] bg-om-panel border-b border-om-line2">
                    {[__('PSN'), __('Serial no.'), __('Last seen'), __('Status')].map((h) => <span key={h} className={sectionLabelCls}>{h}</span>)}
                </div>
                <div className="text-center p-[30px] text-[13px] text-om-faint">{__('No units started yet.')}</div>
            </div>
        );
    }
    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h2 className="m-0 text-[14px] font-semibold text-om-ink">{__('Units in this order')}</h2>
                <Badge variant="neutral">{data.total > units.length ? __(':shown of :total', { shown: units.length, total: data.total }) : data.total}</Badge>
            </div>
            <AppDataTable data={units} columns={columns} searchable columnToggle={false} paginated />
        </div>
    );
}
const fieldLabelCls = 'block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint';
const inputCls =
    'w-full text-[13px] text-om-ink placeholder:text-om-faint bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]';
const errorCls = 'mt-[5px] text-[11.5px] text-om-blocked';

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function ScrapReasonForm({ entry, reasons }) {
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const submit = async (event) => {
        event.preventDefault();
        if (!reason || busy) return;
        setBusy(true);
        setError('');
        try {
            const response = await apiCall(`/api/v1/scrap-entries/${entry.id}`, 'PATCH', { scrap_reason_id: Number(reason) });
            if (!response.ok) {
                const data = await response.json();
                setError(data.errors?.scrap_reason_id?.[0] || data.message);
                return;
            }
            router.reload({ only: ['workOrder'], preserveScroll: true });
        } catch {
            setError(__('Failed to save correction. Please try again.'));
        } finally {
            setBusy(false);
        }
    };
    return (
        <form onSubmit={submit} className="mt-2 space-y-2" data-testid={`scrap-reason-${entry.id}`}>
            <div className="text-sm text-om-muted">
                {__('Scrap reason')}
                <Dropdown
                    options={reasons.map((item) => ({ value: String(item.id), label: item.name }))}
                    value={reason}
                    onChange={setReason}
                    placeholder={__('Select a reason')}
                    aria-label={__('Scrap reason')}
                    className="mt-1 w-full"
                />
            </div>
            <Button type="submit" disabled={!reason || busy}>{__('Save')}</Button>
            {error && <p className={errorCls}>{error}</p>}
        </form>
    );
}

function ChevronIcon({ open }) {
    return (
        <Icon name="chevron-down" size={20} className={`text-om-faint transition-transform ${open ? 'rotate-180' : ''}`} />
    );
}

const modalFooterCls = 'flex justify-end gap-[9px] border-t border-om-line2 bg-om-panel px-[18px] py-[14px]';

// ---------------------------------------------------------------------------
// BOM accordion
// ---------------------------------------------------------------------------

function BomSection({ workOrder }) {
    const [open, setOpen] = useState(false);
    const bom = workOrder.process_snapshot?.bom;

    const columns = useMemo(() => [
        {
            id: 'material',
            accessorFn: (r) => r.material_name,
            header: __('Material'),
            meta: { align: 'left', flex: true },
            cell: ({ row }) => (
                <>
                    <span className="font-medium text-om-ink">{row.original.material_name}</span>
                    <span className="text-xs text-om-faint font-mono ml-1">{row.original.material_code}</span>
                </>
            ),
        },
        {
            id: 'type',
            accessorFn: (r) => r.material_type,
            header: __('Type'),
            meta: { align: 'left' },
            cell: ({ row }) => (
                <span className={`px-2 py-0.5 rounded-[20px] font-mono text-[10px] uppercase tracking-[0.06em] ${bomTypeBadge(row.original.material_type)}`}>
                    {row.original.material_type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                </span>
            ),
        },
        {
            id: 'per_unit',
            accessorFn: (r) => r.quantity_per_unit,
            header: __('Per Unit'),
            meta: { align: 'right' },
            cell: ({ row }) => (
                <span className="font-mono text-om-ink">
                    {row.original.quantity_per_unit} {row.original.unit_of_measure}
                </span>
            ),
        },
        {
            id: 'total',
            accessorFn: (r) => {
                const base = r.quantity_per_unit * workOrder.planned_qty;
                return base + base * (r.scrap_percentage / 100);
            },
            header: `${__('Total')} (${Math.round(workOrder.planned_qty)} ${__('pcs')})`,
            meta: { align: 'right' },
            cell: ({ row }) => {
                const item = row.original;
                const base = item.quantity_per_unit * workOrder.planned_qty;
                const scrap = base * (item.scrap_percentage / 100);
                const total = base + scrap;
                return (
                    <span className="font-mono font-medium text-om-ink">
                        {fmtQty(total)} {item.unit_of_measure}
                        {item.scrap_percentage > 0 && (
                            <span className="text-xs text-om-faint ml-1">(+{item.scrap_percentage}% {__('scrap')})</span>
                        )}
                    </span>
                );
            },
        },
        {
            id: 'step',
            accessorFn: (r) => r.step_number,
            header: __('Step'),
            meta: { align: 'left' },
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-om-muted">
                    {row.original.step_number ? `#${row.original.step_number}` : __('General')}
                </span>
            ),
        },
    ], [workOrder.planned_qty]);

    if (!bom || bom.length === 0) return null;

    return (
        <div className="border border-om-line2 rounded-[10px] px-4 py-3">
            <button
                type="button"
                className="flex justify-between items-center w-full text-left cursor-pointer"
                onClick={() => setOpen((v) => !v)}
            >
                <h2 className={`${sectionLabelCls} m-0`}>{__('Recipe / Materials')}</h2>
                <div className="flex items-center gap-2">
                    <Badge variant="neutral">{bom.length} {__('items')}</Badge>
                    <ChevronIcon open={open} />
                </div>
            </button>

            {open && (
                <div className="mt-4">
                    <DataTable
                        data={bom}
                        columns={columns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                    />
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
    const [lightbox, setLightbox] = useState(null);
    if (!photos || photos.length === 0) return null;

    return (
        <div>
            <h2 className="m-0 mb-2.5 text-[14px] font-semibold text-om-ink">{__('Reference photos')}</h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
                {photos.map((photo) => (
                    <figure key={photo.id} className="m-0 border border-om-line2 rounded-[10px] p-[7px]">
                        <Tooltip label={photo.caption || ''}>
                            <button
                                type="button"
                                onClick={() => setLightbox(photo)}
                                className="block w-full cursor-pointer"
                                aria-label={photo.caption || 'Work instruction'}
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.caption || 'Work instruction'}
                                    loading="lazy"
                                    className="w-full h-[110px] object-cover rounded-[7px] bg-om-chip"
                                />
                            </button>
                        </Tooltip>
                        {photo.caption && (
                            <figcaption className="mt-[7px] text-xs text-om-muted truncate">
                                {photo.caption}
                            </figcaption>
                        )}
                    </figure>
                ))}
            </div>

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
                    <Tooltip label="Close">
                        <button
                            type="button"
                            onClick={() => setLightbox(null)}
                            className="absolute top-5 right-5 text-white/80 hover:text-white cursor-pointer"
                            aria-label="Close"
                        >
                            <Icon name="x" size={28} />
                        </button>
                    </Tooltip>
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
                    <div className={fieldLabelCls}>{__('Production Quantity')}</div>
                    <QuantityField
                        aria-label={__('Production Quantity')}
                        step="0.01"
                        value={productionQty}
                        onChange={setProductionQty}
                        className={`${inputCls} font-mono`}
                        placeholder={__('Current production qty')}
                    />
                </div>

                {[1, 2, 3].map((s) => {
                    const dimIdx = (s - 1) * 2;
                    const fitIdx = (s - 1) * 2 + 1;
                    return (
                        <div key={s} className="mb-2 p-2 bg-om-card border border-om-line2 rounded-om-sm">
                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">{__('Sample #')}{s}</p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={samples[dimIdx].value_numeric}
                                        onChange={(e) => updateSample(dimIdx, 'value_numeric', e.target.value)}
                                        className={`${inputCls} font-mono`}
                                        placeholder={__('Dimension')}
                                        required
                                    />
                                </div>
                                <div>
                                    <Dropdown
                                        options={[
                                            { value: '1', label: __('Pass') },
                                            { value: '0', label: __('Fail') },
                                        ]}
                                        value={samples[fitIdx].value_boolean == null ? '' : String(samples[fitIdx].value_boolean)}
                                        onChange={(v) => updateSample(fitIdx, 'value_boolean', v)}
                                        className="w-full"
                                    />
                                </div>
                            </div>
                        </div>
                    );
                })}

                <div className="flex gap-2 mt-2">
                    <Button type="submit" variant="accent" disabled={processing} className="px-5 py-3 text-[14px]">
                        {__('Submit QC')}
                    </Button>
                    <Button variant="secondary" onClick={onClose} className="px-5 py-3 text-[14px]">
                        {__('Cancel')}
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
        ['udi_readable', __('UDI code readable')],
        ['packaging_condition', __('Packaging in good condition')],
        ['labels_readable', __('Labels readable')],
        ['label_matches_product', __('Label matches product')],
    ];

    return (
        <div className="mt-3 p-4 bg-om-panel border border-om-line2 rounded-om-sm">
            <form onSubmit={submit}>
                {checks.map(([field, label]) => (
                    <div key={field} className="mb-2">
                        <Checkbox
                            checked={form.data[field]}
                            onChange={(next) => form.setData(field, next)}
                            label={label}
                        />
                    </div>
                ))}
                <div className="flex gap-2 mt-2">
                    <Button type="submit" variant="accent" disabled={form.processing} className="px-5 py-3 text-[14px]">
                        {__('Submit Checklist')}
                    </Button>
                    <Button variant="secondary" onClick={onClose} className="px-5 py-3 text-[14px]">
                        {__('Cancel')}
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
                <div className={fieldLabelCls}>
                    {__('Scrap quantity (optional)')}
                </div>
                <QuantityField
                    aria-label={__('Scrap quantity (optional)')}
                    step="0.01"
                    min="0"
                    value={form.data.scrap_qty}
                    onChange={(v) => form.setData('scrap_qty', v)}
                    className={`${inputCls} w-32 font-mono`}
                    placeholder="0"
                />
            </div>
            <p className="text-sm mb-3 text-om-muted">{__('Release this batch?')}</p>
            <div className="flex gap-3 flex-wrap">
                <Button
                    variant="secondary"
                    disabled={form.processing}
                    onClick={() => submitWith('for_production')}
                    className="px-5 py-3 text-[14px]"
                >
                    {__('For Production')}
                </Button>
                <Button
                    variant="accent"
                    disabled={form.processing}
                    onClick={() => submitWith('for_sale')}
                    className="px-5 py-3 text-[14px]"
                >
                    {__('For Sale')}
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
                {__('Confirm Parameters')}
            </Button>
            {lastConfirm && (
                <span className="font-mono text-[11px] text-om-running">
                    {__('Last:')} {formatDateTime(new Date(lastConfirm.confirmed_at), { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
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
                {__('Production Controls')}
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
                        {__('Quality Check (:count)', { count: qcCount })}
                    </Button>
                    {qcCount < 3 ? (
                        <span className="font-mono text-[11px] text-om-downtime">{__(':count more needed', { count: 3 - qcCount })}</span>
                    ) : (
                        <span className="font-mono text-[11px] text-om-running">{__('Min. requirement met')}</span>
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
                        {__('Packaging Checklist')}
                    </Button>
                    {showChecklist && (
                        <PackagingChecklistForm batch={batch} onClose={() => setShowChecklist(false)} />
                    )}
                </div>
            ) : (
                <div className="text-sm">
                    <span className={batch.packaging_checklist.all_passed ? 'text-om-running' : 'text-om-blocked'}>
                        {__('Packaging')}: {batch.packaging_checklist.all_passed ? __('All passed') : __('Some items failed')}
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
                        {__('Release Batch')}
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

function BatchCard({ batch, defaultOpen, labelTemplates = [], stepPhotos = {}, stepMedia = {}, stepChecklists = {}, stepOutputs = {}, flowMode = 'whole_batch', selectedWorkstation = null }) {
    const [expanded, setExpanded] = useState(defaultOpen);
    const showControls = batch.status === 'IN_PROGRESS' || batch.status === 'DONE';

    const releaseLabel =
        batch.release_type === 'for_sale' ? 'For Sale' : 'For Production';
    const isReleased = !!(batch.released_at || batch.released);
    const status = statusMeta(batch.status);

    const steps = batch.steps ?? [];
    const finished = steps.length > 0 && steps.every((st) => ['DONE', 'SKIPPED'].includes(st.status));
    const current = finished ? null : pickCurrentStep(steps, selectedWorkstation?.id);
    const stepLabel = steps.length === 0 ? null : `${finished ? steps.length : (current?.step_number ?? 1)}/${steps.length}`;
    const facts = [
        batch.workstation && `${__('Workstation')}: ${batch.workstation.name}`,
        batch.expiry_date && `${__('Expiry')}: ${batch.expiry_date}`,
    ].filter(Boolean);

    return (
        <div className="border border-om-line rounded-xl overflow-hidden bg-om-card" data-testid={`batch-${batch.batch_number}`}>
            {/* Header row */}
            <div className="flex items-center gap-3 px-4 py-[13px] bg-om-panel">
                <button
                    type="button"
                    aria-expanded={expanded}
                    className="flex flex-1 min-w-0 flex-wrap items-center gap-x-3.5 gap-y-1 text-left cursor-pointer"
                    onClick={() => setExpanded((v) => !v)}
                >
                    <span className="text-[14px] font-semibold text-om-ink">{__('Batch')} #{batch.batch_number}</span>
                    {batch.lot_number && <span className="font-mono text-[11.5px] text-om-muted">{__('LOT')} {batch.lot_number}</span>}
                    <span className={`font-mono text-[9px] uppercase tracking-[0.05em] rounded-full px-2 py-[3px] ${status.pill}`}>{status.label}</span>
                    {isReleased && <span className="font-mono text-[9px] uppercase tracking-[0.05em] rounded-full px-2 py-[3px] text-om-running bg-om-running-bg">{__('Released')} ({__(releaseLabel)})</span>}
                    <span className="flex-1" />
                    <span className="font-mono text-[12px] text-om-muted">
                        {qtyS(batch.produced_qty)} / {qtyS(batch.target_qty)} {__('pcs')}{stepLabel && ` · ${__('step')} ${stepLabel}`}
                    </span>
                    {batch.created_at && <span className="font-mono text-[11px] text-om-faint">{formatDateTime(new Date(batch.created_at), { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}</span>}
                    <Icon name={expanded ? 'chevron-up' : 'chevron-down'} size={16} className="text-om-faint shrink-0" aria-hidden="true" />
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
                <div className="px-[18px] pt-5 pb-[18px] space-y-4">
                    {facts.length > 0 && <div className="font-mono text-[11.5px] text-om-muted -mt-1">{facts.join(' · ')}</div>}

                    {/* Steps */}
                    <BatchStepList steps={steps} current={current} labelTemplates={labelTemplates} stepPhotos={stepPhotos} stepMedia={stepMedia} stepChecklists={stepChecklists} stepOutputs={stepOutputs} flowMode={flowMode} selectedWorkstation={selectedWorkstation} />

                    {/* Production controls */}
                    {showControls && <ProductionControls batch={batch} />}
                </div>
            )}
        </div>
    );
}

/** The step's short name on the tracker: its station's floor code, else its number. */
function stepCode(step) {
    return stationParts(step.workstation)?.code || `#${step.step_number}`;
}

/** Segment of a step on the stepper: its station's type when set, else the code's prefix (FA-2 → FA). */
function stepSegment(step) {
    if (step.workstation?.workstation_type) return step.workstation.workstation_type;
    const code = stationParts(step.workstation)?.code;
    return code ? code.split('-')[0] : null;
}

const isClosed = (st) => st.status === 'DONE' || st.status === 'SKIPPED';

/**
 * The batch's route (design ref: OpenMES Work Order v2.dc.html): a bar of
 * segments (runs of stations sharing a prefix or type) over one dot per step —
 * done in green, the current step in accent, the rest open — with what each
 * started step passed out of what it received. A dot opens its step below. The
 * scroller clips vertically too, so its padding leaves room for the halo.
 */
function StepStepper({ steps, current, viewId, transfer, onView }) {
    const runs = [];
    for (const st of steps) {
        const key = stepSegment(st);
        const last = runs[runs.length - 1];
        if (last && last.key === key) last.steps.push(st);
        else runs.push({ key, steps: [st] });
    }
    const showRuns = runs.length > 1 && runs.every((r) => r.key);

    return (
        <div className="overflow-x-auto -mx-1 px-1 pt-1.5 pb-1 mb-[18px]" data-testid="step-tracker">
            <div style={{ minWidth: `${steps.length * 60}px` }}>
                {showRuns && (
                    <div className="flex gap-1.5 mb-2.5">
                        {runs.map((r, i) => {
                            const done = r.steps.filter(isClosed).length;
                            const active = !!current && r.steps.some((st) => st.id === current.id);
                            const all = done === r.steps.length;
                            return (
                                <div key={i} style={{ flex: r.steps.length }} className={`min-w-0 flex items-center justify-between gap-2 px-[9px] py-[5px] rounded-[7px] ${active ? 'bg-om-accent-bg' : all ? 'bg-om-running-bg' : 'bg-om-chip'}`}>
                                    <span className={`text-[11px] font-semibold truncate ${active ? 'text-om-accent' : all ? 'text-om-running' : 'text-om-muted'}`}>{r.key}</span>
                                    <span className="font-mono text-[9.5px] text-om-faint">{done}/{r.steps.length}</span>
                                </div>
                            );
                        })}
                    </div>
                )}
                <ol className="flex m-0 p-0 list-none">
                    {steps.map((st, i) => {
                        const done = isClosed(st);
                        const active = current?.id === st.id;
                        const viewing = viewId === st.id;
                        const dot = done
                            ? 'bg-om-running text-white'
                            : active
                                ? 'bg-om-accent text-white'
                                : 'bg-om-card text-om-faint border-[1.5px] border-om-line';
                        const halo = viewing ? (active ? 'shadow-[0_0_0_4px_var(--om-accent-bg)]' : 'shadow-[0_0_0_4px_var(--om-chip)]') : '';
                        const started = done || active || st.status === 'IN_PROGRESS';
                        const count = transfer && started && st.status !== 'SKIPPED' && st.incoming_qty != null
                            ? `${qtyS(st.passed_qty ?? 0)}/${qtyS(st.incoming_qty)}`
                            : '';
                        return (
                            <li key={st.id} className="flex-1 min-w-0 relative flex flex-col items-center">
                                {i < steps.length - 1 && <span className={`absolute top-[13px] left-1/2 w-full h-[3px] ${done ? 'bg-om-running' : 'bg-om-line'}`} />}
                                <button
                                    type="button"
                                    onClick={() => onView(st.id)}
                                    title={`${st.step_number}. ${st.name}`}
                                    aria-label={`${st.step_number}. ${st.name}`}
                                    aria-current={active ? 'step' : undefined}
                                    aria-pressed={viewing}
                                    className={`relative z-[1] w-7 h-7 rounded-full flex items-center justify-center font-mono text-[10.5px] font-semibold cursor-pointer ${dot} ${halo}`}
                                >
                                    {st.status === 'SKIPPED' ? <Icon name="minus" size={14} stroke={3} /> : done ? <Icon name="check" size={14} stroke={3} /> : st.step_number}
                                </button>
                                <span className={`mt-[7px] font-mono text-[10px] whitespace-nowrap max-w-full truncate px-0.5 ${active ? 'text-om-accent' : done ? 'text-om-ink' : 'text-om-faint'} ${active || viewing ? 'font-semibold' : ''}`}>
                                    {stepCode(st)}
                                </span>
                                <span className={`mt-0.5 h-[13px] font-mono text-[9.5px] ${active ? 'text-om-accent' : 'text-om-faint'}`}>{count}</span>
                            </li>
                        );
                    })}
                </ol>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Batch Steps list (replaces the Livewire component)
// ---------------------------------------------------------------------------

// Consecutive steps bound to the same workstation form one "station operation":
// the unit of work an operator at that station sees and confirms.
function groupStepsByStation(steps) {
    const groups = [];
    for (const step of steps) {
        const workstationId = step.workstation_id ?? null;
        const last = groups[groups.length - 1];
        if (last && last.workstationId === workstationId) {
            last.steps.push(step);
        } else {
            groups.push({ workstationId, workstation: step.workstation ?? null, steps: [step] });
        }
    }
    return groups;
}

// Transfer-flow figures of one step: what arrived, what is still waiting here,
// what left as good and what was lost. `compact`: the read-only card's row.
function FlowTiles({ step, compact = false }) {
    const waiting = Number(step.available_qty ?? 0);
    const passed = Number(step.passed_qty ?? 0);
    const scrap = Number(step.scrap_qty ?? 0);
    const tiles = [
        [__('Incoming'), Number(step.incoming_qty ?? 0), 'text-om-ink'],
        [__('Waiting'), waiting, waiting ? 'text-om-accent' : 'text-om-faint'],
        [__('Passed'), passed, passed ? 'text-om-running' : 'text-om-faint'],
        [__('Scrap'), scrap, scrap ? 'text-om-blocked' : 'text-om-faint'],
    ];
    return (
        <div className={`flex ${compact ? 'gap-2' : 'gap-1.5'}`} data-testid={`ledger-${step.step_number}`}>
            {tiles.map(([label, value, cls], i) => (
                <div key={label} className={`min-w-0 border rounded-[10px] px-3 py-[11px] ${compact ? 'w-24 bg-om-bg' : 'flex-1 bg-om-card'} ${!compact && i === 1 && waiting ? 'border-om-accent' : 'border-om-line2'}`}>
                    <div className="font-mono text-[8.5px] uppercase tracking-[0.08em] text-om-faint mb-[5px]">{label}</div>
                    <div className={`font-mono font-medium leading-none ${compact ? 'text-[20px]' : 'text-[24px]'} ${cls}`}>{qtyS(value)}</div>
                </div>
            ))}
        </div>
    );
}

function QuantityCorrection({ step }) {
    const form = useForm({ good_qty: String(step.passed_qty), expected_good_qty: String(step.passed_qty), reason: '' });
    return <details className="px-3 pb-3">
        <summary className="cursor-pointer text-sm text-om-accent">{__('Correct good quantity')}</summary>
        <form className="space-y-2 pt-2" onSubmit={e => { e.preventDefault(); form.post(`/operator/batch-step/${step.id}/quantity-correction`, { preserveScroll: true }); }}>
            <p className="text-sm text-om-muted">{__('Enter the corrected total, not an increment. The original and corrected values are retained in the audit history.')}</p>
            <label className="block">{__('Corrected good total')}<QuantityField aria-label={__('Corrected good total')} className="w-full border border-om-line bg-om-bg text-om-ink rounded-om-sm px-2 py-2" min="0" step="0.01" required value={form.data.good_qty} onChange={v => form.setData('good_qty', v)} /></label>
            <label className="block">{__('Correction reason')}<input aria-label={__('Correction reason')} className="w-full border border-om-line bg-om-bg text-om-ink rounded-om-sm px-2 py-2" required maxLength={1000} value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} /></label>
            {Object.entries(form.errors).map(([key, value]) => <p key={key} role="alert" className="text-sm text-om-blocked">{value}</p>)}
            <Button type="submit" variant="outline" disabled={form.processing}>{__('Save correction')}</Button>
        </form>
    </details>;
}

// One quantity with − / + either side of the field, sized for a gloved thumb.
function QtyStepper({ label, value, onChange, onStep, max, danger = false }) {
    const sideCls = 'shrink-0 w-11 h-[52px] flex items-center justify-center bg-om-panel cursor-pointer hover:text-om-ink';
    return (
        <div className="min-w-0">
            <div className="text-[12px] text-om-muted mb-1.5">{label}</div>
            <div className="flex items-center border border-om-line rounded-[10px] overflow-hidden focus-within:border-om-accent">
                <button type="button" onClick={() => onStep(-1)} aria-label={__('Decrease :field', { field: label })} className={`${sideCls} text-om-muted`}>
                    <Icon name="minus" size={18} />
                </button>
                <QuantityField
                    min="0"
                    step="0.01"
                    max={max}
                    value={value}
                    onChange={onChange}
                    placeholder="0"
                    aria-label={label}
                    className={`flex-1 min-w-0 w-0 h-[52px] border-0 p-0 text-center bg-om-card font-mono text-[22px] outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none ${danger ? 'text-om-blocked' : 'text-om-ink'}`}
                />
                <button type="button" onClick={() => onStep(1)} aria-label={__('Increase :field', { field: label })} className={`${sideCls} text-om-ink`}>
                    <Icon name="plus" size={18} />
                </button>
            </div>
        </div>
    );
}

// Quantity log for a running step: good pieces move on to the next station,
// scrapped ones are recorded as a bare number (reason added later). With
// `throughSteps` (the operator's station runs several steps) a tick sends the
// good pieces through all of them in one go. `piece` (the template step's
// quantity entry is "+1 per piece"): one large +1 for a good piece and one for
// a scrapped one instead of the fields.
function RecordQuantities({ step, throughSteps = 0, piece = false, inflight, error, onSubmit }) {
    const [good, setGood] = useState('');
    const [scrap, setScrap] = useState('');
    const [through, setThrough] = useState(false);
    const available = Number(step.available_qty ?? 0);
    const g = Number(good) || 0;
    const x = Number(scrap) || 0;
    const total = g + x;
    const over = total > available + 0.001;
    const fraction = !!step.whole_pieces && (!Number.isInteger(g) || !Number.isInteger(x));
    const valid = total > 0 && !over && !fraction;
    const bump = (set, cur, delta) => set(cur + delta <= 0 ? '' : String(Math.min(available, cur + delta)));
    const fillAll = () => {
        const rest = available - x;
        if (rest > 0) setGood(String(rest));
        else { setGood(String(available)); setScrap(''); }
    };
    const submit = (e) => {
        e.preventDefault();
        if (!valid || inflight) return;
        onSubmit({ good_qty: g, scrap_qty: x, through_station: through }, () => { setGood(''); setScrap(''); });
    };
    const throughTick = throughSteps > 1 && (
        <label className="flex items-center gap-2 mb-3 text-[12.5px] text-om-ink cursor-pointer" data-testid={`log-station-${step.step_number}`}>
            <input type="checkbox" checked={through} onChange={(e) => setThrough(e.target.checked)} className="w-4 h-4 accent-[var(--om-accent)]" />
            {__('Log through station')}
            <span className="font-mono text-[11px] text-om-faint">· {__(':count steps', { count: throughSteps })}</span>
        </label>
    );
    const one = (payload) => onSubmit({ good_qty: 0, scrap_qty: 0, ...payload, through_station: through }, () => {});

    if (piece) {
        const bigCls = 'flex flex-col items-center justify-center gap-0.5 rounded-xl h-[76px] cursor-pointer hover:brightness-95 disabled:opacity-40 disabled:cursor-not-allowed';
        return (
            <div className="bg-om-card border border-om-line rounded-xl px-4 py-3.5" data-testid={`log-step-${step.step_number}`}>
                <div className="flex items-center justify-between gap-2 mb-3">
                    <span className="text-[14px] font-semibold text-om-ink">{__('Log pieces')}</span>
                    <span className="font-mono text-[11px] text-om-faint">{__('Waiting')}: {qtyS(available)}</span>
                </div>
                {throughTick}
                <div className="grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-3">
                    <button type="button" disabled={available < 1 || inflight} onClick={() => one({ good_qty: 1 })} aria-label={__('Add one good piece')} className={`${bigCls} bg-om-accent text-white`}>
                        <span className="font-mono text-[26px] font-semibold leading-none">{inflight ? '…' : '+1'}</span>
                        <span className="text-[12px] font-medium opacity-90">{__('Good')}</span>
                    </button>
                    <button type="button" disabled={available < 1 || inflight} onClick={() => one({ scrap_qty: 1 })} aria-label={__('Add one scrapped piece')} className={`${bigCls} bg-om-blocked-bg text-om-blocked`}>
                        <span className="font-mono text-[26px] font-semibold leading-none">+1</span>
                        <span className="text-[12px] font-medium">{__('Scrap')}</span>
                    </button>
                </div>
                {error && <p role="alert" className="mt-2 mb-0 text-[12px] text-om-blocked">{error}</p>}
            </div>
        );
    }

    const label = inflight
        ? '…'
        : valid
            ? (x ? __('Record :good good · :scrap scrap', { good: qtyS(g), scrap: qtyS(x) }) : __('Record :good good', { good: qtyS(g) }))
            : over ? __('More than waiting (:count)', { count: qtyS(available) }) : fraction ? __('Whole pieces only') : __('Enter a quantity');

    return (
        <form onSubmit={submit} className="bg-om-card border border-om-line rounded-xl px-4 py-3.5" data-testid={`log-step-${step.step_number}`}>
            <div className="flex items-center justify-between gap-2 mb-3">
                <span className="text-[14px] font-semibold text-om-ink">{__('Record quantities')}</span>
                <div className="flex items-center gap-1.5">
                    {/* One good piece logged at once, without touching the fields */}
                    <button type="button" disabled={available < 1 || inflight} onClick={() => one({ good_qty: 1 })} aria-label={__('Add one good piece')} className="font-mono text-[12px] font-semibold rounded-full px-3 py-1.5 cursor-pointer text-white bg-om-accent hover:brightness-95 disabled:opacity-40 disabled:cursor-not-allowed">
                        +1
                    </button>
                    <button type="button" onClick={fillAll} className="text-[12px] font-semibold rounded-full px-3 py-1.5 cursor-pointer text-om-accent bg-om-accent-bg hover:brightness-95">
                        {__('All :count good', { count: qtyS(available) })}
                    </button>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3 mb-3">
                <QtyStepper label={__('Good')} value={good} onChange={setGood} onStep={(d) => bump(setGood, g, d)} max={available} />
                <QtyStepper label={__('Scrap')} value={scrap} onChange={setScrap} onStep={(d) => bump(setScrap, x, d)} max={available} danger />
            </div>
            {throughTick}
            <button
                type="submit"
                disabled={!valid || inflight}
                className={`block w-full text-center text-[14.5px] font-semibold rounded-[10px] py-3.5 cursor-pointer disabled:cursor-not-allowed ${valid ? 'bg-om-accent text-white hover:brightness-95' : over || fraction ? 'bg-om-chip text-om-blocked' : 'bg-om-chip text-om-faint'}`}
            >
                {label}
            </button>
            {error && <p role="alert" className="mt-2 mb-0 text-[12px] text-om-blocked">{error}</p>}
        </form>
    );
}

// Where the operator's station hands its good pieces on to.
function NextStation({ next }) {
    return (
        <p className="m-0 text-[12px] text-om-muted">
            {next
                ? __('Next: :station · Line: :line', { station: next.workstation?.name ?? next.name, line: next.workstation?.line?.name ?? '—' })
                : __('Final step — finished output')}
        </p>
    );
}

// What the step needs before it can be completed — read confirmation, the
// checklist, documents to validate — as one list of tick rows. A row without
// an action (a finished step) shows who did it.
function GateList({ gates }) {
    return (
        <ul className="m-0 p-0 list-none flex flex-col gap-[7px]">
            {gates.map((g) => (
                <li key={g.key} className={`flex items-center gap-[11px] border rounded-[10px] px-[13px] py-3 ${g.done ? 'border-om-running/35 bg-om-running-bg' : 'border-om-line2 bg-om-card'}`}>
                    <button
                        type="button"
                        role="checkbox"
                        aria-checked={g.done}
                        disabled={!g.onClick || g.busy}
                        onClick={g.onClick ?? undefined}
                        className="flex flex-1 min-w-0 items-center gap-[11px] text-left cursor-pointer disabled:cursor-default"
                    >
                        <span aria-hidden="true" className={`w-[22px] h-[22px] rounded-md shrink-0 flex items-center justify-center text-white ${g.done ? 'bg-om-running' : 'border-2 border-om-faintest'} ${g.busy ? 'opacity-50' : ''}`}>
                            {g.done && <Icon name="check" size={14} stroke={3} />}
                        </span>
                        <span className="flex-1 min-w-0 text-[13.5px] text-om-ink break-words">{g.label}</span>
                    </button>
                    {g.href && (
                        <a href={g.href} target="_blank" rel="noopener noreferrer" className="shrink-0 text-[12px] font-semibold text-om-accent hover:text-om-ink">{__('View')}</a>
                    )}
                    {g.meta
                        ? <span className="shrink-0 max-w-[45%] truncate font-mono text-[11px] text-om-running">{g.meta}</span>
                        : g.required && !g.done && <span className="shrink-0 font-mono text-[9px] uppercase tracking-[0.08em] text-om-downtime">{__('Required')}</span>}
                </li>
            ))}
        </ul>
    );
}

function BatchStepList({ steps, current = null, labelTemplates = [], stepPhotos = {}, stepMedia = {}, stepChecklists = {}, stepOutputs = {}, flowMode = 'whole_batch', selectedWorkstation = null }) {
    const [inflightStepId, setInflightStepId] = useState(null);
    const transfer = flowMode === 'transfer';
    const list = steps ?? [];

    // One step on show at a time — the current one unless a dot on the stepper
    // (or the routing graph) opened another. That pick travels in the address
    // (`?step=`) so a link from the workstation table, or a reload, lands on it.
    const [viewId, setViewId] = useState(() => {
        const fromUrl = Number(readParam('step'));
        return fromUrl && list.some((st) => st.id === fromUrl) ? fromUrl : null;
    });
    const viewed = (viewId != null ? list.find((st) => st.id === viewId) : null) ?? current;
    // How much of the route is laid out as cards: the one step on show, the
    // operator's station's run of steps, or every step grouped by station.
    const stationScoped = !!selectedWorkstation;
    const [mode, setMode] = useState('step');
    const viewOn = (id) => {
        const next = id == null || id === current?.id ? null : id;
        setViewId(next);
        writeParams({ step: next });
        if (mode !== 'step' && id != null) {
            window.setTimeout(() => document.getElementById(`batch-step-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50);
        }
    };
    const [graphOpen, setGraphOpen] = useState(false);
    // A step's media shows inline, as before; its button folds it away.
    const [mediaHidden, setMediaHidden] = useState({});

    // The operator's station (picked in the queue, or their workstation
    // account): its run of steps can log pieces through all of them at once,
    // and says where the pieces go next.
    const groups = groupStepsByStation(list);
    const groupOf = (step) => groups.find((g) => g.steps.some((st) => st.id === step.id));
    const isMine = (group) => stationScoped && group?.workstationId === selectedWorkstation.id;
    const nextAfter = (group) => list.find((s) => s.step_number > group.steps[group.steps.length - 1].step_number && s.status !== 'SKIPPED');
    // Lifting the station goes through the address, as the queue's picker does,
    // so the whole session (queue included) stops scoping to it.
    const clearStation = () => router.get(window.location.pathname, { ...Object.fromEntries(new URLSearchParams(window.location.search)), workstation: 'all' }, { preserveScroll: true });

    // Quantity log (transfer flow): posts to the ledger route; a rule violation
    // comes back as a `good_qty` error shown under the form that sent it.
    const [logTarget, setLogTarget] = useState(null);
    const [logErrors, setLogErrors] = useState({});
    const [startErrors, setStartErrors] = useState({});
    const handleLogQuantity = (step, payload, onDone) => {
        setLogErrors({});
        setLogTarget(`${step.id}:${payload.through_station ? 'station' : 'step'}`);
        setInflightStepId(step.id);
        router.post(`/operator/batch-step/${step.id}/quantity`, payload, {
            preserveScroll: true,
            onSuccess: onDone,
            onError: setLogErrors,
            onFinish: () => setInflightStepId(null),
        });
    };
    const [photoZoom, setPhotoZoom] = useState(null);
    const [pickModal, setPickModal] = useState(null); // { step, materials } | null
    const [completeModal, setCompleteModal] = useState(null); // { step } — actual-times confirmation (#52)

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

    // Validate a mandatory document so its step's completion gate clears.
    const [inflightDocId, setInflightDocId] = useState(null);
    const handleValidateDocument = (doc) => {
        setInflightDocId(doc.id);
        router.post(
            `/operator/batch-step-document/${doc.id}/validate`,
            {},
            { preserveScroll: true, onFinish: () => setInflightDocId(null) }
        );
    };

    // Acknowledge reading a critical step's instructions so its completion gate clears.
    const [inflightConfirmId, setInflightConfirmId] = useState(null);
    const handleConfirmInstructions = (step) => {
        setInflightConfirmId(step.id);
        router.post(
            `/operator/batch-step/${step.id}/confirm-instructions`,
            {},
            { preserveScroll: true, onFinish: () => setInflightConfirmId(null) }
        );
    };

    // Tick / un-tick a work-instruction checklist item on a step.
    const [inflightCheckId, setInflightCheckId] = useState(null);
    const handleToggleChecklist = (step, item) => {
        setInflightCheckId(`${step.id}:${item.id}`);
        router.post(
            `/operator/batch-step/${step.id}/checklist/${item.id}/toggle`,
            {},
            { preserveScroll: true, onFinish: () => setInflightCheckId(null) }
        );
    };

    // Record a typed output value (scalar or a picture file). forceFormData carries
    // the File for a picture; scalars post a plain value.
    const handleRecordOutput = (step, output, value) => {
        setInflightCheckId(`out:${step.id}:${output.id}`);
        router.post(
            `/operator/batch-step/${step.id}/outputs/${output.id}`,
            { value },
            { preserveScroll: true, forceFormData: value instanceof File, onFinish: () => setInflightCheckId(null) }
        );
    };

    // Starting a step: first ask the server which material lots (if any) need
    // picking. With lots to pick, open the WO-time picking modal seeded with the
    // system's proposal; otherwise start the step directly (unchanged behavior).
    const handleStart = async (step) => {
        setStartErrors({});
        setInflightStepId(step.id);
        try {
            const res = await fetch(`/operator/batch-step/${step.id}/pick-preview`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.ok) {
                const data = await res.json();
                if (data.materials && data.materials.length > 0) {
                    setInflightStepId(null);
                    setPickModal({ step, materials: data.materials });
                    return;
                }
            }
        } catch {
            // Preview failed → fall through and start directly; the server
            // still auto-picks lots and surfaces any real error.
        }
        router.post(
            `/operator/batch-step/${step.id}/start`,
            {},
            {
                preserveScroll: true,
                onError: errors => setStartErrors({ [step.id]: Object.values(errors).join(' ') }),
                onSuccess: page => { if (page.props.flash?.error) setStartErrors({ [step.id]: page.props.flash.error }); },
                onFinish: () => setInflightStepId(null),
            }
        );
    };

    const when = (d) => formatDateTime(new Date(d), { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

    const renderCard = (step) => {
        const isCur = current?.id === step.id;
        const isInflight = inflightStepId === step.id;
        const photo = stepPhotos[step.step_number];
        const media = stepMedia[step.step_number] || [];
        const stepDocs = step.documents || [];
        const checkDocs = stepDocs.filter((d) => d.requires_validation);
        const refDocs = stepDocs.filter((d) => !d.requires_validation);
        const checklist = stepChecklists[step.step_number] || [];
        const byItem = Object.fromEntries((step.checklist_completions || []).map((c) => [c.checklist_item_id, c]));
        const outputs = stepOutputs[step.step_number] || [];
        const outputValues = step.output_values || [];
        const recorded = new Set(outputValues.map((v) => v.output_id));
        const canCheck = step.status === 'IN_PROGRESS' || step.status === 'READY' || step.status === 'PENDING';
        const startable = step.status === 'PENDING' || step.status === 'READY';
        // Transfer flow: the step can't be finished while pieces can still
        // arrive or are waiting (the server tells us why).
        const productionBlocker = transfer ? step.production_blocker : null;
        const ledgerBlocker = productionBlocker || (transfer ? step.completion_blocker : null);
        // A step that can be worked from here gets the two-column work view; a
        // finished one, or one still waiting on the step before it, reads only.
        const workable = isCur || step.status === 'IN_PROGRESS' || (startable && step.prerequisites_met !== false);
        const group = groupOf(step);
        const mine = isMine(group);
        const stationLogStep = transfer && mine && group.steps.length > 1 ? group.steps.find((st) => !isClosed(st)) : null;
        const mediaOpen = !mediaHidden[step.id];

        const gates = [
            ...(step.requires_confirmation ? [{
                key: 'confirm',
                label: __('I have read the instructions'),
                done: !!step.confirmed_at,
                required: true,
                meta: step.confirmed_at ? [step.confirmed_by?.name, when(step.confirmed_at)].filter(Boolean).join(' · ') : null,
                onClick: !step.confirmed_at && canCheck ? () => handleConfirmInstructions(step) : null,
                busy: inflightConfirmId === step.id,
            }] : []),
            ...checklist.map((item) => ({
                key: `check-${item.id}`,
                label: item.label,
                done: !!byItem[item.id],
                required: !!item.is_required,
                meta: byItem[item.id]?.checked_by?.name ?? null,
                onClick: canCheck ? () => handleToggleChecklist(step, item) : null,
                busy: inflightCheckId === `${step.id}:${item.id}`,
            })),
            ...checkDocs.map((doc) => ({
                key: `doc-${doc.id}`,
                label: __('Validate :document', { document: doc.reference ? `${doc.name} · ${doc.reference}` : doc.name }),
                done: !!doc.validated_at,
                required: !!doc.is_mandatory,
                meta: doc.validated_at ? (doc.validated_by?.name ?? __('Validated')) : null,
                href: doc.file_path ? `/operator/batch-step-document/${doc.id}/file` : null,
                onClick: !doc.validated_at && canCheck ? () => handleValidateDocument(doc) : null,
                busy: inflightDocId === doc.id,
            })),
        ];
        const gateTotal = gates.length + outputs.length;
        const gateDone = gates.filter((g) => g.done).length + outputs.filter((o) => recorded.has(o.id)).length;
        // What the server refuses a completion for, counted here so the button
        // says so before the operator presses it.
        const requiredLeft = gates.filter((g) => g.required && !g.done).length
            + outputs.filter((o) => o.is_required && !recorded.has(o.id)).length;
        const reasons = [
            ledgerBlocker,
            requiredLeft === 1 ? __('1 required check left') : requiredLeft > 1 ? __(':count required checks left', { count: requiredLeft }) : null,
        ].filter(Boolean);
        const canComplete = step.status === 'IN_PROGRESS' && !isInflight && reasons.length === 0;

        const tag = isCur
            ? __('Current step · :n/:total', { n: step.step_number, total: list.length })
            : step.status === 'DONE'
                ? __('Completed · step :n', { n: step.step_number })
                : step.status === 'SKIPPED'
                    ? __('Skipped · step :n', { n: step.step_number })
                    : step.status === 'IN_PROGRESS'
                        ? __('In progress · step :n', { n: step.step_number })
                        : __('Upcoming · step :n', { n: step.step_number });
        const who = step.status === 'IN_PROGRESS' ? step.started_by?.name : null;

        const instruction = step.instruction
            ? <p className={`m-0 whitespace-pre-line ${workable ? 'text-[14px] leading-[1.6] text-om-ink' : 'text-[13.5px] leading-[1.6] text-om-muted'}`}>{step.instruction}</p>
            : <p className="m-0 text-[13px] text-om-faint">{__('No instructions for this step.')}</p>;
        const tools = (
            <div className="flex flex-wrap items-center gap-2 empty:hidden">
                {photo && (
                    <Tooltip label={photo.caption || 'Step photo'}>
                        <button type="button" onClick={() => setPhotoZoom(photo)} className="shrink-0 cursor-pointer" aria-label={photo.caption || 'Step photo'}>
                            <img src={photo.url} alt={photo.caption || 'Step photo'} loading="lazy" className="w-11 h-11 object-cover rounded-[9px] border border-om-line bg-om-chip" />
                        </button>
                    </Tooltip>
                )}
                <LabelPrintMenu kind="workstation-step" id={step.id} templates={labelTemplates} label={__('Print label')} />
                {media.length > 0 && (
                    <button type="button" onClick={() => setMediaHidden((h) => ({ ...h, [step.id]: mediaOpen }))} aria-expanded={mediaOpen} className={`inline-flex items-center gap-1.5 text-[13px] font-medium rounded-[9px] px-3.5 py-2.5 border cursor-pointer ${mediaOpen ? 'bg-om-ink text-om-on-ink border-om-ink' : 'text-om-ink border-om-line hover:bg-om-bg'}`}>
                        <Icon name="images" size={15} />{__('Step media · :count', { count: media.length })}
                    </button>
                )}
                {refDocs.map((doc) => (doc.file_path ? (
                    <a key={doc.id} href={`/operator/batch-step-document/${doc.id}/file`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1.5 text-[13px] font-medium text-om-ink rounded-[9px] px-3.5 py-2.5 border border-om-line hover:bg-om-bg">
                        <Icon name="file-text" size={15} />{doc.name}
                        {doc.reference && <span className="font-mono text-[11px] text-om-faint">{doc.reference}</span>}
                    </a>
                ) : (
                    <span key={doc.id} className="inline-flex items-center gap-1.5 text-[13px] text-om-muted rounded-[9px] px-3.5 py-2.5 border border-om-line2">
                        <Icon name="file-text" size={15} />{doc.name}
                        {doc.reference && <span className="font-mono text-[11px] text-om-faint">{doc.reference}</span>}
                    </span>
                )))}
            </div>
        );
        const outputsEl = outputs.length > 0 && (
            <StepOutputs step={step} outputs={outputs} values={outputValues} canRecord={canCheck} inflightCheckId={inflightCheckId} onRecord={handleRecordOutput} />
        );

        return (
            <div id={`batch-step-${step.id}`} className={`border-[1.5px] rounded-[14px] overflow-hidden bg-om-card ${isCur ? 'border-om-accent' : 'border-om-line'}`} data-testid="step-card">
                <div className="flex flex-wrap items-start gap-3.5 px-5 py-4 border-b border-om-line2">
                    <div className="flex-1 min-w-0">
                        <div className={`font-mono text-[9.5px] uppercase tracking-[0.08em] mb-[5px] ${isCur ? 'text-om-accent' : 'text-om-faint'}`}>{tag}</div>
                        <div className="text-[18px] font-semibold tracking-[-0.01em] text-om-ink break-words">
                            {step.workstation && <><span className="font-mono">{stepCode(step)}</span><span className="text-om-faintest"> · </span></>}{step.name}
                        </div>
                    </div>
                    {who && (
                        <span title={__('In progress by :name', { name: who })} className="shrink-0 inline-flex items-center gap-[7px] text-[12px] text-om-running bg-om-running-bg rounded-full px-[11px] py-1.5">
                            <span className="w-1.5 h-1.5 rounded-full bg-om-running" />{who}
                        </span>
                    )}
                    {step.status === 'DONE' && (
                        <span className="shrink-0 font-mono text-[11px] text-om-done">
                            {step.completed_by ? __('Done by :name', { name: step.completed_by.name }) : __('Done')}
                        </span>
                    )}
                    {!isCur && mode === 'step' && (
                        <button type="button" onClick={() => viewOn(null)} className="shrink-0 inline-flex items-center gap-1 text-[12.5px] font-semibold text-om-accent hover:text-om-ink cursor-pointer">
                            {current ? __('Back to current step') : __('Close')}<Icon name="arrow-right" size={14} />
                        </button>
                    )}
                </div>

                {workable ? (
                    <div className="grid lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
                        {/* left: instruction and what it needs before completing */}
                        <div className="px-5 pt-4 pb-5 lg:border-r border-om-line2 flex flex-col gap-4 min-w-0">
                            {instruction}
                            {tools}
                            {mediaOpen && media.length > 0 && <StepInstructions media={media} onZoom={setPhotoZoom} />}
                            {gateTotal > 0 && (
                                <div>
                                    <div className="flex items-baseline justify-between mb-[9px]">
                                        <span className="font-mono text-[9.5px] uppercase tracking-[0.1em] text-om-faint">{__('Before completing')}</span>
                                        <span className={`font-mono text-[11px] ${gateDone === gateTotal ? 'text-om-running' : 'text-om-muted'}`}>{gateDone} / {gateTotal}</span>
                                    </div>
                                    {gates.length > 0 && <GateList gates={gates} />}
                                    {outputsEl}
                                </div>
                            )}
                        </div>

                        {/* right: the flow, the quantity log and the step's action */}
                        <div className="px-5 pt-4 pb-5 bg-om-bg flex flex-col gap-4 min-w-0">
                            {transfer && <FlowTiles step={step} />}
                            {transfer && !productionBlocker && step.status === 'IN_PROGRESS' && Number(step.available_qty ?? 0) > 0 && (
                                <RecordQuantities
                                    key={step.id}
                                    step={step}
                                    throughSteps={stationLogStep?.id === step.id ? group.steps.filter((st) => !isClosed(st)).length : 0}
                                    piece={step.quantity_entry === 'piece'}
                                    inflight={isInflight}
                                    error={logTarget?.startsWith(`${step.id}:`) ? (logErrors.good_qty || logErrors.scrap_qty) : null}
                                    onSubmit={(payload, onDone) => handleLogQuantity(step, payload, onDone)}
                                />
                            )}
                            {transfer && !productionBlocker && step.status === 'IN_PROGRESS' && step.manual_correction_allowed && (
                                <QuantityCorrection key={`${step.id}:${step.passed_qty}`} step={step} />
                            )}
                            <div className="mt-auto">
                                {startable ? (
                                    <button
                                        type="button"
                                        disabled={isInflight || !!productionBlocker || step.prerequisites_met === false}
                                        onClick={() => handleStart(step)}
                                        className="block w-full text-center text-[15px] font-semibold rounded-[11px] py-4 cursor-pointer bg-om-accent text-white hover:brightness-95 disabled:cursor-not-allowed disabled:bg-om-card disabled:text-om-faint disabled:border-[1.5px] disabled:border-dashed disabled:border-om-faintest"
                                    >
                                        {isInflight ? '…' : step.prerequisites_met === false ? __('Waiting for the previous step') : __('Start')}
                                    </button>
                                ) : step.status === 'IN_PROGRESS' && (
                                    <button
                                        type="button"
                                        disabled={!canComplete}
                                        onClick={() => (
                                            // Opt-in: only steps with ISA-95 standard times (#52) prompt for
                                            // operator-confirmed actuals; the rest complete directly as before.
                                            step.setup_time_minutes != null || step.run_time_per_unit_minutes != null
                                                ? setCompleteModal({ step })
                                                : handleStepAction(step, 'complete')
                                        )}
                                        className="block w-full text-center text-[15px] font-semibold rounded-[11px] py-4 cursor-pointer bg-om-ink text-om-on-ink hover:brightness-110 disabled:cursor-not-allowed disabled:bg-om-card disabled:text-om-faint disabled:border-[1.5px] disabled:border-dashed disabled:border-om-faintest"
                                    >
                                        {isInflight ? '…' : __('Complete step')}
                                    </button>
                                )}
                                {startable && productionBlocker && <p role="status" className="mt-2 mb-0 text-center text-[12px] text-om-muted">{productionBlocker}</p>}
                                {step.status === 'IN_PROGRESS' && (
                                    <p className={`mt-2 mb-0 text-center text-[12px] ${canComplete ? 'text-om-running' : 'text-om-muted'}`}>
                                        {reasons.length > 0 ? reasons.join(' · ') : __('Ready to complete')}
                                    </p>
                                )}
                                {startErrors[step.id] && <p role="alert" className="mt-2 mb-0 text-center text-[12px] text-om-blocked">{startErrors[step.id]}</p>}
                            </div>
                            {mine && mode === 'step' && <NextStation next={nextAfter(group)} />}
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="flex flex-col lg:flex-row gap-6 lg:items-start px-5 py-4">
                            <div className="flex-1 min-w-0 flex flex-col gap-3">
                                {instruction}
                                {tools}
                            </div>
                            {transfer && step.status !== 'SKIPPED' && step.status !== 'PENDING' && <FlowTiles step={step} compact />}
                            {startable && (
                                <div className="lg:w-[220px] shrink-0">
                                    <button type="button" disabled className="block w-full text-center text-[13.5px] font-semibold rounded-[10px] py-3 bg-om-card text-om-faint border-[1.5px] border-dashed border-om-faintest cursor-not-allowed">
                                        {step.prerequisites_met === false ? __('Waiting for the previous step') : __('Start')}
                                    </button>
                                    {productionBlocker && <p role="status" className="mt-2 mb-0 text-center text-[12px] text-om-muted">{productionBlocker}</p>}
                                </div>
                            )}
                        </div>
                        {mediaOpen && media.length > 0 && <div className="px-5 pb-4"><StepInstructions media={media} onZoom={setPhotoZoom} /></div>}
                        {gateTotal > 0 && (
                            <div className="px-5 pb-5">
                                {gates.length > 0 && <GateList gates={gates} />}
                                {outputsEl}
                            </div>
                        )}
                    </>
                )}
            </div>
        );
    };

    // An order with no steps used to render as nothing at all, which reads as a
    // broken screen rather than as the consequence it is: a work order keeps the
    // configuration it was created with, so steps added to the template later
    // never reach an order that predates them. Say that, and say what to do
    // about it — the operator cannot fix it from here, but whoever they ask can.
    if (list.length === 0) {
        return (
            <div className="rounded-om border border-om-line2 bg-om-panel px-4 py-3">
                <p className="font-medium text-om-ink text-[13px]">{__('No production steps on this order')}</p>
                <p className="mt-1 text-sm text-om-muted">
                    {__('A work order keeps the process configuration it was created with, so steps added to the template afterwards do not appear here. Ask a supervisor to apply a change request to this order, or to raise a new one.')}
                </p>
            </div>
        );
    }

    const routingLabel = `${current ? current.step_number : list.length}/${list.length}`;

    return (
        <div>
            {/* toolbar */}
            <div className="flex flex-wrap items-center gap-2.5 mb-3.5">
                <span className="font-mono text-[9.5px] uppercase tracking-[0.1em] text-om-faint">{__('Routing')} · {routingLabel}</span>
                <span className="flex-1" />
                {selectedWorkstation && (
                    <span className="inline-flex items-center gap-[7px] text-[12px] font-medium text-om-ink bg-om-chip rounded-full py-1.5 pl-3 pr-2">
                        {__('Workstation')} {selectedWorkstation.name}
                        <button type="button" onClick={clearStation} aria-label={__('Show all workstations')} title={__('Show all workstations')} className="w-[18px] h-[18px] rounded-full bg-om-card flex items-center justify-center text-om-muted hover:text-om-ink cursor-pointer">
                            <Icon name="x" size={11} />
                        </button>
                    </span>
                )}
                {list.length > 1 && (
                    <div className="flex gap-[3px] bg-om-bg border border-om-line2 rounded-[9px] p-[3px]" role="group" aria-label={__('Steps view')}>
                        {[['step', __('Current step')], ...(stationScoped ? [['station', __('Your station')]] : []), ['all', __('All steps')]].map(([key, label]) => (
                            <button key={key} type="button" aria-pressed={mode === key} onClick={() => setMode(key)} className={`whitespace-nowrap text-[12px] font-medium rounded-[7px] px-[11px] py-[5px] cursor-pointer ${mode === key ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'}`}>
                                {label}
                            </button>
                        ))}
                    </div>
                )}
                {list.length > 1 && (
                    <button type="button" onClick={() => setGraphOpen((v) => !v)} aria-expanded={graphOpen} className={`text-[12px] font-medium rounded-lg px-3 py-[7px] border cursor-pointer ${graphOpen ? 'bg-om-ink text-om-on-ink border-om-ink' : 'text-om-ink border-om-line hover:bg-om-bg'}`}>
                        {__('Routing graph')}
                    </button>
                )}
            </div>
            {list.length > 1 && graphOpen && (
                <div className="rounded-om border border-om-line2 overflow-hidden mb-4" data-testid="routing-graph">
                    {/* focusSelected: a long routing fitted whole shrinks every node past
                        readability, so the view follows the step on show and its neighbours. */}
                    <RoutingGraph compact focusSelected height={260} steps={list} selectedId={viewed?.id ?? current?.id ?? null} onSelectStep={viewOn} />
                    <p className="px-3 py-1.5 text-[11px] text-om-muted border-t border-om-line2 m-0">{__('Click a step to open it below.')}</p>
                </div>
            )}
            {list.length > 1 && <StepStepper steps={list} current={current} viewId={viewed?.id ?? null} transfer={transfer} onView={viewOn} />}

            {mode === 'step' && !current && !viewed && (
                <div className="text-center p-4 rounded-[10px] bg-om-running-bg text-[13.5px] font-semibold text-om-running">{__('All steps complete')}</div>
            )}
            {mode === 'step' && viewed && renderCard(viewed)}
            {mode !== 'step' && (
                <div className="space-y-5">
                    {groups.filter((g) => mode === 'all' || isMine(g)).map((group, gi) => {
                        const mineGroup = isMine(group);
                        const done = group.steps.filter(isClosed).length;
                        const running = group.steps.some((st) => st.status === 'IN_PROGRESS');
                        return (
                            <div key={gi} data-testid={`station-group-${gi}`}>
                                {(stationScoped || group.steps.length > 1) && (
                                    <div className="flex flex-wrap items-center gap-2 mb-2">
                                        <span className="text-[13.5px] font-semibold text-om-ink">{group.workstation?.name ?? __('No station')}</span>
                                        {mineGroup && <span className="font-mono text-[9.5px] uppercase tracking-[0.06em] px-2 py-0.5 rounded-full bg-om-accent text-white">{__('Your station')}</span>}
                                        <span className="font-mono text-[11px] text-om-muted">{group.steps.map((st) => `${st.step_number}/${list.length}`).join(', ')}</span>
                                        <span className={`font-mono text-[11px] ${running ? 'text-om-running' : 'text-om-muted'}`}>
                                            {running ? __('In progress') : __(':done of :total done', { done, total: group.steps.length })}
                                        </span>
                                    </div>
                                )}
                                <div className="space-y-3">{group.steps.map(renderCard)}</div>
                                {mineGroup && <div className="mt-2"><NextStation next={nextAfter(group)} /></div>}
                            </div>
                        );
                    })}
                </div>
            )}

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

            {pickModal && (
                <LotPickModal
                    step={pickModal.step}
                    materials={pickModal.materials}
                    onClose={() => setPickModal(null)}
                />
            )}

            {completeModal && (
                <ConfirmTimesModal
                    step={completeModal.step}
                    onClose={() => setCompleteModal(null)}
                />
            )}
        </div>
    );
}

// Document control panel shown under a step: lists attached documents and lets
// the operator validate mandatory ones. A blocked banner explains why the step
// can't be completed until they are validated.
function StepDocuments({ docs = [], blocked, canValidate, inflightDocId, onValidate }) {
    return (
        <div className="border-t border-om-line2 px-3 py-2 space-y-2">
            {blocked && (
                <p className="text-[12px] text-om-blocked bg-om-blocked-bg rounded px-2 py-1.5">
                    {__('This step is blocked: a mandatory document must be validated before you can complete it.')}
                </p>
            )}
            <ul className="space-y-1">
                {docs.map((doc) => {
                    const validated = !!doc.validated_at;
                    const mandatory = doc.is_mandatory && doc.requires_validation;
                    return (
                        <li key={doc.id} className="flex items-center gap-2 text-sm">
                            <span className="text-om-faint" aria-hidden="true">📄</span>
                            <span className="text-om-ink">{doc.name}</span>
                            {doc.reference && <span className="font-mono text-[11px] text-om-faint">{doc.reference}</span>}
                            {mandatory && (
                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-om-chip text-om-muted uppercase tracking-wide">
                                    {__('Mandatory')}
                                </span>
                            )}
                            {doc.file_path && (
                                <a href={`/operator/batch-step-document/${doc.id}/file`} target="_blank" rel="noopener noreferrer" className="text-[12px] text-om-accent hover:underline">
                                    {__('View')}
                                </a>
                            )}
                            <span className="flex-1" />
                            {validated ? (
                                <span className="font-mono text-[11px] text-om-done break-words max-w-full">
                                    {__('Validated')}{doc.validated_by ? ` · ${doc.validated_by.name}` : ''}
                                </span>
                            ) : doc.requires_validation ? (
                                canValidate ? (
                                    <Button
                                        variant="accent"
                                        disabled={inflightDocId === doc.id}
                                        onClick={() => onValidate(doc)}
                                        className="px-3 py-1.5 text-[13px] whitespace-nowrap"
                                    >
                                        {inflightDocId === doc.id ? '…' : __('Validate')}
                                    </Button>
                                ) : (
                                    <span className="font-mono text-[11px] text-om-downtime whitespace-nowrap">{__('Not validated')}</span>
                                )
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

// Rich work instructions shown under a step: images (tap to zoom), inline PDFs
// and videos. Tablet-friendly - large media, no tiny controls.
function StepInstructions({ media = [], onZoom }) {
    return (
        <div className="border-t border-om-line2 px-3 py-2 space-y-3">
            {media.map((m) => (
                <div key={m.id}>
                    {m.title && <p className="text-[12px] font-medium text-om-muted mb-1">{m.title}</p>}
                    {m.media_type === 'image' && (
                        <button type="button" onClick={() => onZoom({ url: m.url, caption: m.title })} className="block cursor-pointer">
                            <img src={m.url} alt={m.title || ''} loading="lazy" className="max-h-56 rounded-om-sm border border-om-line bg-om-chip object-contain" />
                        </button>
                    )}
                    {m.media_type === 'video' && (
                        <video src={m.url} controls preload="metadata" className="w-full max-h-72 rounded-om-sm border border-om-line bg-black" />
                    )}
                    {m.media_type === 'pdf' && (
                        <div>
                            <embed src={m.url} type="application/pdf" className="w-full h-72 rounded-om-sm border border-om-line bg-om-chip" />
                            <a href={m.url} target="_blank" rel="noopener noreferrer" className="inline-block mt-1 text-[12px] text-om-accent hover:underline">
                                {__('Open PDF')}
                            </a>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

// Typed operator outputs on a step: one input per definition; a picture output
// takes a file upload and shows the recorded photo. Records who filled each value.
function StepOutputs({ step, outputs = [], values = [], canRecord, inflightCheckId, onRecord }) {
    const byOutput = Object.fromEntries(values.map((v) => [v.output_id, v]));

    const displayValue = (o, v) => {
        if (!v) return null;
        switch (o.value_type) {
            case 'number': return v.value_number;
            case 'boolean': return v.value_boolean ? __('Yes') : __('No');
            case 'date': return v.value_date;
            case 'picture': return null;
            default: return v.value_text;
        }
    };

    return (
        <div className="border-t border-om-line2 px-3 py-2">
            <p className="text-[12px] font-semibold text-om-muted mb-1">{__('Operator outputs')}</p>
            <ul className="space-y-2">
                {outputs.map((o) => {
                    const v = byOutput[o.id];
                    const busy = inflightCheckId === `out:${step.id}:${o.id}`;
                    const recorded = !!v;
                    return (
                        <li key={o.id} className="text-sm">
                            <div className="flex items-center gap-1.5 mb-1">
                                <span className="text-om-ink">{o.label}</span>
                                {o.unit && <span className="text-[11px] text-om-muted">({o.unit})</span>}
                                {o.is_required && <span className="text-[10px] uppercase tracking-wide text-om-downtime">{__('Required')}</span>}
                                {recorded && v.recorded_by && <span className="ml-auto font-mono text-[11px] text-om-done">{v.recorded_by.name}</span>}
                            </div>
                            {o.value_type === 'picture' ? (
                                <div className="flex items-center gap-2">
                                    {v?.file_url && (
                                        <a href={v.file_url} target="_blank" rel="noopener noreferrer">
                                            <img src={v.file_url} alt={o.label} className="h-14 w-14 object-cover rounded border border-om-line2" />
                                        </a>
                                    )}
                                    <label className={`text-sm text-om-accent hover:underline cursor-pointer ${(!canRecord || busy) ? 'opacity-50 pointer-events-none' : ''}`}>
                                        {busy ? __('Uploading…') : recorded ? __('Replace photo') : __('Take / upload photo')}
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            capture="environment"
                                            className="hidden"
                                            disabled={!canRecord || busy}
                                            onChange={(e) => { const f = e.target.files?.[0]; if (f) onRecord(step, o, f); e.target.value = ''; }}
                                        />
                                    </label>
                                </div>
                            ) : o.value_type === 'boolean' ? (
                                <label className="flex items-center gap-2">
                                    <input type="checkbox" checked={!!v?.value_boolean} disabled={!canRecord || busy} onChange={(e) => onRecord(step, o, e.target.checked ? '1' : '0')} className="w-5 h-5 rounded border-om-line2 accent-om-accent" />
                                    <span className="text-om-muted">{v?.value_boolean ? __('Yes') : __('No')}</span>
                                </label>
                            ) : o.value_type === 'select' ? (
                                <Dropdown
                                    options={(o.options ?? []).map((opt) => ({ value: opt, label: opt }))}
                                    value={v?.value_text ?? ''}
                                    disabled={!canRecord || busy}
                                    onChange={(val) => val && onRecord(step, o, val)}
                                    placeholder={__('Select…')}
                                    aria-label={o.label}
                                    className="w-full"
                                />
                            ) : (
                                <OutputScalarInput output={o} initial={displayValue(o, v)} disabled={!canRecord || busy} onSubmit={(val) => onRecord(step, o, val)} />
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

// Text/number/date output: a controlled field committed on blur/Enter, so the
// operator types the value then it saves without a separate button per row.
function OutputScalarInput({ output, initial, disabled, onSubmit }) {
    const [val, setVal] = useState(initial ?? '');
    const type = output.value_type === 'number' ? 'number' : output.value_type === 'date' ? 'date' : 'text';
    const commit = () => { if (String(val).trim() !== '' && String(val) !== String(initial ?? '')) onSubmit(val); };
    return (
        <input
            type={type}
            value={val}
            disabled={disabled}
            onChange={(e) => setVal(e.target.value)}
            onBlur={commit}
            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); commit(); } }}
            placeholder={__('Enter value…')}
            className="form-input w-full text-sm"
        />
    );
}

// ---------------------------------------------------------------------------
// Create Batch Modal
// ---------------------------------------------------------------------------

function CreateBatchModal({ workOrder, workstations, defaultWorkstationId, onClose }) {
    // Server-computed: planned minus what existing batches already cover (a
    // short finished batch frees the rest, an open one holds its full target).
    const remaining = workOrder.batch_capacity ?? Math.max((workOrder.planned_qty ?? 0) - (workOrder.produced_qty ?? 0), 0);

    const form = useForm({
        work_order_id: workOrder.id,
        target_qty: String(remaining),
        workstation_id: defaultWorkstationId ? String(defaultWorkstationId) : (workstations.length === 1 ? String(workstations[0].id) : ''),
        lot_number: '',
        auto_lot: false,
    });

    // Said under the field instead of left to the browser's max bubble or to
    // the server, whose refusal closes the modal (assertCanCreateBatch).
    const qty = parseFloat(form.data.target_qty);
    const refusal = !(qty > 0) ? null
        : workOrder.whole_pieces && !Number.isInteger(qty) ? __('Whole pieces only')
        : !workOrder.overproduction_allowed && qty > remaining
            ? __('Batch quantity :qty exceeds what the order still needs (:remaining). Allow overproduction in Settings → Production to plan more.', { qty: fmtQty(qty), remaining: fmtQty(remaining) })
            : null;

    const submit = (e) => {
        e.preventDefault();
        if (refusal) return;
        form.post('/operator/batch', { onSuccess: onClose });
    };

    return (
        <ModalShell title={__("Create New Batch")} subtitle={workOrder.order_no} onClose={onClose}>
            <form onSubmit={submit}>
                <div className="px-[18px] py-4">
                    {/* Quantity */}
                    <div className="mb-4">
                        <div className={fieldLabelCls}>
                            {__('Quantity')}
                        </div>
                        <QuantityField
                            aria-label={__('Quantity')}
                            step={workOrder.whole_pieces ? '1' : '0.01'}
                            min={workOrder.whole_pieces ? '1' : '0.01'}
                            max={workOrder.overproduction_allowed ? undefined : remaining}
                            value={form.data.target_qty}
                            onChange={(v) => form.setData('target_qty', v)}
                            className={`${inputCls} font-mono text-[15px]`}
                            required
                        />
                        <p className="mt-1 font-mono text-[11px] text-om-faint">{__('Remaining')}: {fmtQty(remaining)}</p>
                        {refusal && <p role="alert" className={errorCls}>{refusal}</p>}
                        {form.errors.target_qty && (
                            <p className={errorCls}>{form.errors.target_qty}</p>
                        )}
                    </div>

                    {/* Workstation */}
                    {workstations.length > 0 && (
                        <div className="mb-4">
                            <div className={fieldLabelCls}>
                                {__('Workstation')}
                            </div>
                            {workstations.length === 1 ? (
                                <>
                                    <input type="hidden" value={workstations[0].id} />
                                    <p className="text-sm text-om-muted py-2">
                                        {workstations[0].name}
                                    </p>
                                </>
                            ) : (
                                <Dropdown
                                    aria-label={__('Workstation')}
                                    options={workstations.map((ws) => ({ value: String(ws.id), label: ws.name }))}
                                    value={form.data.workstation_id == null ? '' : String(form.data.workstation_id)}
                                    onChange={(v) => form.setData('workstation_id', v)}
                                    placeholder={__('— Select workstation —')}
                                    className="w-full"
                                />
                            )}
                            {form.errors.workstation_id && (
                                <p className={errorCls}>{form.errors.workstation_id}</p>
                            )}
                        </div>
                    )}

                    {/* Auto-LOT */}
                    <div className="mb-4">
                        <Checkbox
                            checked={form.data.auto_lot}
                            onChange={(next) => form.setData('auto_lot', next)}
                            label={__('Auto-generate LOT number')}
                        />
                    </div>

                    {/* Manual LOT */}
                    {!form.data.auto_lot && (
                        <div className="mb-4">
                            <div className={fieldLabelCls}>
                                {__('LOT Number (manual)')}
                            </div>
                            <input
                                aria-label={__('LOT Number (manual)')}
                                type="text"
                                value={form.data.lot_number}
                                onChange={(e) => form.setData('lot_number', e.target.value)}
                                className={`${inputCls} font-mono`}
                                placeholder={__('Leave empty for no LOT')}
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
                        {__('Cancel')}
                    </Button>
                    <Button
                        type="submit"
                        variant="accent"
                        disabled={form.processing || !!refusal}
                        className="px-6 py-4 text-[15px] font-semibold"
                    >
                        {__('Create Batch')}
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
        <ModalShell title={__("Report Issue")} subtitle={workOrder.order_no} onClose={onClose}>
            <form onSubmit={submit}>
                <div className="px-[18px] py-4 space-y-4">
                    <div>
                        <div className={fieldLabelCls}>
                            {__('Issue Type')} <span className="text-om-blocked">*</span>
                        </div>
                        <Dropdown
                            aria-label={__('Issue Type')}
                            options={issueTypes.map((type) => ({
                                value: String(type.id),
                                label: `${type.name}${type.is_blocking ? ` ${__('⚠ Blocking')}` : ''}`,
                            }))}
                            value={form.data.issue_type_id == null ? '' : String(form.data.issue_type_id)}
                            onChange={(v) => form.setData('issue_type_id', v)}
                            placeholder={__('— Select type —')}
                            className="w-full"
                        />
                        {form.errors.issue_type_id && (
                            <p className={errorCls}>{form.errors.issue_type_id}</p>
                        )}
                    </div>

                    <div>
                        <div className={fieldLabelCls}>
                            {__('Title')} <span className="text-om-blocked">*</span>
                        </div>
                        <input
                            aria-label={__('Title')}
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            className={inputCls}
                            placeholder={__('Brief summary of the issue')}
                            required
                            maxLength={255}
                        />
                        {form.errors.title && (
                            <p className={errorCls}>{form.errors.title}</p>
                        )}
                    </div>

                    <div>
                        <div className={fieldLabelCls}>
                            {__('Description')}
                        </div>
                        <textarea
                            aria-label={__('Description')}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            rows={3}
                            className={`${inputCls} resize-none`}
                            placeholder={__('Additional details…')}
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
                        {__('Report Issue')}
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
        <ModalShell title={__("Report Scrap")} subtitle={workOrder.order_no} onClose={onClose}>
            <form onSubmit={submit}>
                <div className="px-[18px] py-4 space-y-4">
                    <div>
                        <div className={fieldLabelCls}>
                            {__('Reason')} <span className="text-om-blocked">*</span>
                        </div>
                        <Dropdown
                            aria-label={__('Reason')}
                            options={scrapReasons.map((reason) => ({
                                value: String(reason.id),
                                label: `${reason.code} — ${reason.name}`,
                            }))}
                            value={form.data.scrap_reason_id == null ? '' : String(form.data.scrap_reason_id)}
                            onChange={(v) => form.setData('scrap_reason_id', v)}
                            placeholder={__('— Select reason —')}
                            className="w-full"
                        />
                        {form.errors.scrap_reason_id && (
                            <p className={errorCls}>{form.errors.scrap_reason_id}</p>
                        )}
                    </div>

                    <div>
                        <div className={fieldLabelCls}>
                            {__('Quantity')} <span className="text-om-blocked">*</span>
                        </div>
                        <QuantityField
                            aria-label={__('Quantity')}
                            step="0.01"
                            min="0.01"
                            value={form.data.quantity}
                            onChange={(v) => form.setData('quantity', v)}
                            className={`${inputCls} font-mono text-[15px]`}
                            placeholder="0"
                            required
                        />
                        {form.errors.quantity && (
                            <p className={errorCls}>{form.errors.quantity}</p>
                        )}
                    </div>

                    <div>
                        <div className={fieldLabelCls}>
                            {__('Notes')}
                        </div>
                        <textarea
                            aria-label={__('Notes')}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={3}
                            className={`${inputCls} resize-none`}
                            placeholder={__('Additional details…')}
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
                        {__('Report Scrap')}
                    </Button>
                </div>
            </form>
        </ModalShell>
    );
}

// ---------------------------------------------------------------------------
// Engineering documents frozen onto this order (#179) — read-only for operators:
// download the native file, or open an interactive package in the sandboxed viewer.
// ---------------------------------------------------------------------------

function EngineeringDocsSection({ docs = [], onView }) {
    if (!docs || docs.length === 0) return null;

    const BASE = '/api/v1/engineering-documents';
    const linkCls = 'text-[12.5px] font-semibold text-om-accent hover:underline cursor-pointer';

    return (
        <div>
            <h2 className="m-0 mb-2.5 text-[14px] font-semibold text-om-ink">{__('Engineering documents')}</h2>
            <ul className="m-0 p-0 list-none grid grid-cols-1 md:grid-cols-2 gap-2">
                {docs.map((doc) => {
                    const pkg = packageMeta(doc.package_type);
                    const ext = (doc.original_filename ?? '').split('.').pop();
                    const tag = (ext && ext !== doc.original_filename ? ext : __(pkg.label)).toUpperCase().slice(0, 5);
                    return (
                        <li key={doc.document_id} className="flex items-center gap-2.5 border border-om-line2 rounded-[9px] px-3 py-2.5">
                            <span className="font-mono text-[9px] font-semibold text-om-blocked border border-om-blocked/30 rounded px-[5px] py-px shrink-0">{tag}</span>
                            <span className="flex-1 min-w-0">
                                <span className="block text-[13px] text-om-ink truncate" title={doc.original_filename ?? ''}>{doc.original_filename ?? __('Document')}</span>
                                <span className="block text-[11px] text-om-faint">
                                    {__(pkg.label)}{doc.file_size ? ` · ${formatBytes(doc.file_size)}` : ''}
                                </span>
                            </span>
                            <span className="font-mono text-[10px] text-om-faint shrink-0">{__('REV')} {doc.revision || '—'}</span>
                            {isInteractive(doc.package_type) && doc.entry_point && (
                                <button type="button" className={linkCls} onClick={() => onView(doc)}>{__('View')}</button>
                            )}
                            <a
                                className={linkCls}
                                href={`${BASE}/${doc.document_id}/download`}
                                target={pkg.inline ? '_blank' : undefined}
                                rel="noopener noreferrer"
                            >
                                {__('Download')}
                            </a>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

const TABS = ['batches', 'materials', 'instructions', 'units'];
const sideSectionCls = 'px-5 py-[18px] border-b border-om-line2';
const sideBtnCls = 'inline-flex items-center gap-1 text-[13px] font-semibold rounded-lg px-[13px] py-[9px] cursor-pointer hover:brightness-95';
const heroBtnCls = 'inline-flex items-center gap-1.5 whitespace-nowrap text-[13.5px] font-semibold rounded-[10px] px-[15px] py-2.5 cursor-pointer hover:brightness-95';

/** Due date as the hero states it: the date, and how far off (or overdue) it is. */
function dueLine(due, status) {
    if (!due) return null;
    const d = new Date(due);
    if (Number.isNaN(d.getTime())) return null;
    const day = (x) => Math.floor(new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime() / 86400000);
    const diff = day(d) - day(new Date());
    const open = !['DONE', 'CANCELLED', 'REJECTED'].includes(status);
    let note = null;
    if (open && diff < 0) note = diff === -1 ? __('1 day overdue') : __(':count days overdue', { count: -diff });
    else if (open && diff === 0) note = __('Today');
    else if (open && diff === 1) note = __('Tomorrow');
    else if (open && diff > 1) note = __('in :count days', { count: diff });
    // The year only when it isn't this one.
    const sameYear = d.getFullYear() === new Date().getFullYear();
    return { date: formatDate(d, { day: '2-digit', month: 'short', ...(sameYear ? {} : { year: 'numeric' }) }), overdue: open && diff < 0, note };
}

/** "FA-2 · Rolki" — the floor's code first, as the station picker reads it. */
function stationParts(ws) {
    if (!ws) return null;
    const [head, ...rest] = String(ws.name ?? '').split(' · ');
    return rest.length ? { code: head, name: rest.join(' · ') } : { code: ws.code ?? '', name: ws.name ?? '' };
}

function DetailRow({ label, mono = false, last = false, children }) {
    return (
        <div className={`flex justify-between gap-3 text-[13px] py-1.5 ${last ? '' : 'border-b border-om-line2'}`}>
            <span className="text-om-muted shrink-0">{label}</span>
            <span className={`text-om-ink text-right min-w-0 break-words ${mono ? 'font-mono' : ''}`}>{children}</span>
        </div>
    );
}

function issueStatusCls(status) {
    if (status === 'RESOLVED' || status === 'CLOSED') return 'text-om-running bg-om-running-bg';
    if (status === 'ACKNOWLEDGED') return 'text-om-downtime bg-om-downtime-bg';
    return 'text-om-blocked bg-om-blocked-bg';
}

/**
 * The order's materials against stock (design ref: OpenMES Work Order.dc.html):
 * what it needs, what it has (reserved for it plus usable stock), what is
 * missing, and the store's on-hand and reserved-by-other-batches figures — the
 * last explains a full store that still reads as short.
 */
function MaterialsTable({ lines = [], filter, onFilter }) {
    const [query, setQuery] = useState('');
    const shortCount = lines.filter((m) => m.short).length;
    const effective = shortCount === 0 ? 'all' : filter;
    const q = query.trim().toLowerCase();
    const rows = lines.filter((m) => (effective === 'all' || m.short)
        && (!q || `${m.material_code ?? ''} ${m.material_name ?? ''}`.toLowerCase().includes(q)));
    const grid = 'grid grid-cols-[minmax(200px,1fr)_80px_80px_90px_90px_110px] gap-3';
    const th = `${monoLabelCls} text-right`;
    const filters = [['short', __('Short :count', { count: shortCount })], ['all', __('All :count', { count: lines.length })]];

    if (lines.length === 0) return null;

    return (
        <div data-testid="materials-table">
            <div className="flex flex-wrap items-center gap-2.5 mb-3">
                <div className="flex gap-[3px] bg-om-bg border border-om-line2 rounded-[9px] p-[3px]" role="group" aria-label={__('Material filter')}>
                    {filters.map(([key, label]) => (
                        <button key={key} type="button" aria-pressed={effective === key} disabled={key === 'short' && shortCount === 0} onClick={() => onFilter(key)} className={`${pillCls(effective === key)} disabled:opacity-40 disabled:cursor-not-allowed`}>
                            {label}
                        </button>
                    ))}
                </div>
                <label className="flex items-center gap-2 w-[260px] max-w-full bg-om-bg border border-om-line2 rounded-[9px] px-[11px] py-[7px] focus-within:border-om-accent">
                    <Icon name="search" size={14} className="text-om-faint shrink-0" />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={__('Search material code…')}
                        aria-label={__('Search material code…')}
                        className="flex-1 min-w-0 border-0 bg-transparent p-0 text-[13px] text-om-ink placeholder:text-om-faint outline-none focus:ring-0"
                    />
                </label>
            </div>
            <div className="border border-om-line2 rounded-[10px] overflow-x-auto">
                <div className="min-w-[720px]">
                    <div className={`${grid} px-3.5 py-[9px] bg-om-panel border-b border-om-line2`}>
                        <span className={monoLabelCls}>{__('Material')}</span>
                        <span className={th}>{__('Need')}</span>
                        <span className={th}>{__('Have')}</span>
                        <span className={th}>{__('Missing')}</span>
                        <span className={th}>{__('On hand')}</span>
                        <span className={th}>{__('Reserved · other')}</span>
                    </div>
                    <div className="max-h-[440px] overflow-auto">
                        {rows.map((m) => (
                            <div key={m.material_code ?? m.material_name} className={`${grid} items-center px-3.5 py-[9px] border-b border-om-line2 last:border-b-0 font-mono text-[12.5px] text-right`}>
                                <span className="flex items-center gap-[9px] min-w-0 text-left" title={m.material_name || undefined}>
                                    <span className={`w-[7px] h-[7px] rounded-full shrink-0 ${m.short ? 'bg-om-blocked' : 'bg-om-running'}`} />
                                    <span className="shrink-0 text-om-ink">{m.material_code || m.material_name}</span>
                                    {m.material_code && m.material_name && <span className="min-w-0 truncate font-sans text-[11.5px] text-om-faint">{m.material_name}</span>}
                                    {!m.material_exists && <span className="shrink-0 font-sans text-[11px] text-om-blocked">{__('not in stock list')}</span>}
                                </span>
                                <span className="text-om-ink">{qtyS(m.need)}</span>
                                <span className={m.short ? 'text-om-blocked' : 'text-om-ink'}>{qtyS(m.have)}</span>
                                <span className={`font-semibold ${m.short ? 'text-om-blocked' : 'text-om-faintest'}`}>{m.missing > 0 ? qtyS(m.missing) : '—'}</span>
                                <span className={m.on_hand < 0 ? 'text-om-blocked' : 'text-om-muted'}>{qtyS(m.on_hand)}</span>
                                <span className="text-om-muted">{m.reserved > 0 ? qtyS(m.reserved) : '—'}</span>
                            </div>
                        ))}
                        {rows.length === 0 && <div className="text-center p-7 font-sans text-[13px] text-om-faint">{__('No material matches.')}</div>}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function WorkOrderDetail() {
    const { workOrder, issueTypes = [], scrapReasons = [], workstations = [], issueCustomFields = [], defaultWorkstationId, line, labelTemplates = [], processPhotos = [], stepPhotos = {}, stepMedia = {}, stepChecklists = {}, stepOutputs = {}, engineeringDocuments = [], materialShortages = [], materialLines = [], flowMode = 'whole_batch', selectedWorkstation = null, serialUnits = { total: 0, units: [] }, hooks = {} } = usePage().props;

    const [engViewer, setEngViewer] = useState(null); // { url, title } for the sandboxed viewer

    // A link carrying `?step=` opens the batch that owns that step.
    const [urlStepBatchId] = useState(() => {
        const id = Number(readParam('step'));
        return id ? (workOrder.batches ?? []).find((b) => (b.steps ?? []).some((st) => st.id === id))?.id ?? null : null;
    });

    // The tab travels in the address so a write that redirects back, a reload
    // or a shared link lands on the same one.
    const [tab, setTabState] = useState(() => (TABS.includes(readParam('tab')) ? readParam('tab') : 'batches'));
    const setTab = (next) => { setTabState(next); writeParams({ tab: next === 'batches' ? null : next }); };
    const [matFilter, setMatFilter] = useState('short');

    async function openEngViewer(doc) {
        try {
            const res = await apiGet(`/api/v1/engineering-documents/${doc.document_id}/viewer-url`);
            if (!res.ok) return;
            const json = await res.json();
            if (json.data?.url) setEngViewer({ url: json.data.url, title: doc.original_filename });
        } catch {
            // silent — the Download link remains available as a fallback
        }
    }

    const [createBatchOpen, setCreateBatchOpen] = useState(false);
    const [reportIssueOpen, setReportIssueOpen] = useState(false);
    const [reportScrapOpen, setReportScrapOpen] = useState(false);

    const plannedQty = workOrder.planned_qty ?? 0;
    const producedQty = workOrder.produced_qty ?? 0;
    const remaining = Math.max(plannedQty - producedQty, 0);
    const pct = plannedQty > 0 ? Math.min((producedQty / plannedQty) * 100, 100) : 0;

    // Closed orders take no batch, and neither does one whose batches already
    // cover the plan (unless overproduction is allowed) — the server refuses both.
    const canCreateBatch = !['DONE', 'CANCELLED', 'REJECTED', 'BLOCKED'].includes(workOrder.status)
        && (workOrder.overproduction_allowed || (workOrder.batch_capacity ?? 1) > 0);
    const canReportIssue = !['DONE', 'CANCELLED'].includes(workOrder.status);
    const hasStepLedger = flowMode === 'transfer' && ((workOrder.process_snapshot?.steps?.length ?? 0) > 0 || workOrder.batches?.some((batch) => batch.steps?.length));
    const canReportScrap = !hasStepLedger && scrapReasons.length > 0 && !['DONE', 'CANCELLED'].includes(workOrder.status);
    // In step-ledger mode the Scrap buttons stay and point at the steps instead.
    const canScrap = canReportScrap || (hasStepLedger && !['DONE', 'CANCELLED'].includes(workOrder.status));
    const toast = useToast();
    const openScrap = () => (canReportScrap
        ? setReportScrapOpen(true)
        : toast({ severity: 'warning', title: __('Record scrap on the work order steps.') }));

    const scrapEntries = workOrder.scrap_entries ?? [];
    const totalScrap = scrapEntries.reduce((sum, e) => sum + Number(e.quantity ?? 0), 0);
    const qualityPct = producedQty > 0 ? (Math.max(0, producedQty - totalScrap) / producedQty) * 100 : null;

    const batches = workOrder.batches ?? [];
    const issues = workOrder.issues ?? [];
    const due = dueLine(workOrder.due_date, workOrder.status);
    const priority = priorityTag(workOrder.priority);
    const status = statusMeta(workOrder.status);

    // Whole pieces the stock on hand (plus what is already reserved for this
    // order) could still make — the number the shortage banner leads with.
    const canBuild = materialShortages.length > 0 && plannedQty > 0
        ? Math.max(0, Math.floor(Math.min(...materialLines.filter((m) => m.need > 0).map((m) => (m.have / m.need) * plannedQty))))
        : null;
    const shortCount = materialLines.filter((m) => m.short).length || materialShortages.length;
    const instructionCount = processPhotos.length + engineeringDocuments.length;
    const hasMaterials = materialLines.length > 0 || (workOrder.process_snapshot?.bom?.length ?? 0) > 0;

    const tabs = [
        { key: 'batches', label: __('Batches'), count: batches.length },
        { key: 'materials', label: __('Materials'), count: shortCount > 0 ? __(':count short', { count: shortCount }) : materialLines.length, warn: shortCount > 0 },
        { key: 'instructions', label: __('Instructions'), count: instructionCount },
        { key: 'units', label: __('Serial units'), count: serialUnits?.total ?? 0 },
    ];

    return (
        <>
            <Head title={`Work Order ${workOrder.order_no}`} />

            {/* Live-refresh when the work order changes on the line */}
            {line && <LineSync lineId={line.id} reloadOnly={['workOrder']} />}

            <div className="h-full flex flex-col overflow-auto xl:overflow-hidden">
                {/* Hero — the order, how far it has got, and what can be done to it, in one row */}
                <section className="shrink-0 flex flex-wrap items-center gap-x-[22px] gap-y-3 bg-om-card border-b border-om-line2 px-5 py-3" data-testid="wo-hero">
                    <Link href="/operator/queue" title={__('Back to queue')} aria-label={__('Back to queue')} className="shrink-0 w-10 h-10 rounded-[10px] border border-om-line flex items-center justify-center text-[17px] text-om-ink hover:bg-om-bg">
                        <Icon name="arrow-left" size={18} />
                    </Link>
                    <div className="min-w-0">
                        <div className="flex items-center gap-2.5 flex-wrap">
                            <h1 className="m-0 font-mono text-[22px] font-semibold tracking-[-0.02em] text-om-ink whitespace-nowrap">{workOrder.order_no}</h1>
                            <span className={`inline-flex items-center gap-1.5 font-mono text-[9.5px] uppercase tracking-[0.06em] rounded-full px-[9px] py-[3px] whitespace-nowrap ${status.pill}`}>
                                <span className={`w-1.5 h-1.5 rounded-full ${status.dot}`} />{status.label}
                            </span>
                        </div>
                        <div className="text-[13px] text-om-muted mt-0.5">
                            {workOrder.product_type?.name && <>{workOrder.product_type.name} · </>}
                            {__('priority')} <span className="font-mono">{workOrder.priority ?? 0}</span>{priority && ` (${priority.label})`}
                            {due && (
                                <>
                                    {' · '}{__('due')}{' '}
                                    <span className={`font-mono font-semibold ${due.overdue ? 'text-om-blocked' : 'text-om-ink'}`}>{due.date}</span>
                                    {due.note && <span className={due.overdue ? 'text-om-blocked' : ''}> ({due.note})</span>}
                                </>
                            )}
                        </div>
                    </div>
                    <div className="flex-1 min-w-[150px] max-w-[320px]">
                        <div className="flex items-baseline gap-1.5">
                            <span className="font-mono text-[22px] font-medium leading-none text-om-ink">{qtyS(producedQty)}</span>
                            <span className="font-mono text-[13px] text-om-faint">/ {qtyS(plannedQty)} {__('pcs')}</span>
                            <span className="ml-auto whitespace-nowrap text-[12px] text-om-muted">
                                <span className="font-mono font-semibold text-om-accent">{qtyS(remaining)}</span> {__('left')}
                            </span>
                        </div>
                        <div className="h-1.5 rounded-full bg-om-chip overflow-hidden mt-2" title={`${fmtQty(pct, 0)}%`}>
                            <div className={`h-full ${pct >= 100 ? 'bg-om-running' : 'bg-om-accent'}`} style={{ width: `${pct}%` }} />
                        </div>
                    </div>
                    <div className="flex-1 hidden xl:block" />
                    <div className="flex flex-wrap items-center gap-2">
                        <LabelPrintMenu kind="work-order" id={workOrder.id} templates={labelTemplates} label={__('Print label')} />
                        {canScrap && (
                            <button type="button" onClick={openScrap} className={`${heroBtnCls} text-om-downtime bg-om-downtime-bg`}>
                                {__('Scrap')}
                            </button>
                        )}
                        {canReportIssue && (
                            <button type="button" onClick={() => setReportIssueOpen(true)} className={`${heroBtnCls} text-om-blocked bg-om-blocked-bg`}>
                                {__('Report issue')}
                            </button>
                        )}
                        {canCreateBatch && (
                            <button type="button" onClick={() => setCreateBatchOpen(true)} className={`${heroBtnCls} text-white bg-om-accent px-[18px]`}>
                                <Icon name="plus" size={16} />{__('Create batch')}
                            </button>
                        )}
                    </div>
                </section>

                {/* Stock cannot cover this order. Said here, before the operator
                    starts, rather than surfacing as a failed allocation mid-run.
                    The lines themselves are on the Materials tab. */}
                {materialShortages.length > 0 && (
                    <div className="shrink-0 flex flex-wrap items-center gap-x-3.5 gap-y-2 bg-om-blocked-bg border-b border-om-blocked/30 px-5 py-[7px]" data-testid="material-shortage">
                        <span className="w-[9px] h-[9px] rounded-full bg-om-blocked shrink-0" />
                        <div className="flex-1 min-w-0">
                            <span className="text-[14px] font-semibold text-om-blocked">{__('Not enough material to complete this order')}</span>
                            <span className="text-[13px] text-om-ink">
                                {' · '}{__(':short of :total components short', { short: shortCount, total: materialLines.length || shortCount })}
                                {canBuild !== null && <>{' · '}{__('can build :count of :planned', { count: canBuild, planned: qtyS(plannedQty) })}</>}
                            </span>
                        </div>
                        <button type="button" onClick={() => { setMatFilter('short'); setTab('materials'); }} className="inline-flex items-center gap-1.5 text-[13px] font-semibold text-om-blocked bg-om-card border border-om-blocked/30 rounded-[9px] px-3.5 py-[9px] cursor-pointer whitespace-nowrap hover:bg-om-bg">
                            {__('Review materials')}<Icon name="arrow-right" size={14} />
                        </button>
                    </div>
                )}

                <div className="xl:flex-1 xl:min-h-0 flex flex-col xl:flex-row">
                    {/* Main — the order's work, one tab at a time */}
                    <div className="flex-1 min-w-0 flex flex-col bg-om-card">
                        <div className="shrink-0 flex flex-wrap gap-0.5 px-[18px] border-b border-om-line2" role="tablist">
                            {tabs.map((t) => (
                                <button
                                    key={t.key}
                                    type="button"
                                    role="tab"
                                    aria-selected={tab === t.key}
                                    onClick={() => setTab(t.key)}
                                    className={`flex items-center gap-[7px] text-[14.5px] px-4 py-[17px] -mb-px border-b-2 cursor-pointer whitespace-nowrap ${tab === t.key ? 'border-om-accent text-om-ink font-semibold' : 'border-transparent text-om-muted font-medium hover:text-om-ink'}`}
                                >
                                    {t.label}
                                    <span className={`font-mono text-[10px] font-medium rounded-full px-[7px] py-px ${t.warn ? 'text-om-blocked bg-om-blocked-bg' : 'text-om-faint bg-om-chip'}`}>{t.count}</span>
                                </button>
                            ))}
                        </div>
                        <div className="xl:flex-1 xl:min-h-0 xl:overflow-auto">
                                {tab === 'batches' && (
                                    <div className="px-[18px] pt-4 pb-5 flex flex-col gap-3" role="tabpanel">
                                        {batches.length === 0 ? (
                                            <div className="text-center p-9 border border-dashed border-om-faintest rounded-xl text-[13.5px] text-om-faint">{__('No batches created yet.')}</div>
                                        ) : batches.map((batch, idx) => (
                                            <BatchCard
                                                key={batch.id}
                                                batch={batch}
                                                defaultOpen={urlStepBatchId ? batch.id === urlStepBatchId : idx === 0}
                                                labelTemplates={labelTemplates}
                                                stepPhotos={stepPhotos}
                                                stepMedia={stepMedia}
                                                stepChecklists={stepChecklists}
                                                stepOutputs={stepOutputs}
                                                flowMode={flowMode}
                                                selectedWorkstation={selectedWorkstation}
                                            />
                                        ))}
                                    </div>
                                )}

                                {tab === 'materials' && (
                                    <div className="px-[18px] pt-3.5 pb-[18px] space-y-4" role="tabpanel">
                                        {hasMaterials ? (
                                            <>
                                                <MaterialsTable lines={materialLines} filter={matFilter} onFilter={setMatFilter} />
                                                <BomSection workOrder={workOrder} />
                                            </>
                                        ) : (
                                            <div className="text-center p-7 text-[13px] text-om-faint">{__('This order has no bill of materials.')}</div>
                                        )}
                                    </div>
                                )}

                                {tab === 'instructions' && (
                                    <div className="px-[18px] pt-4 pb-5 space-y-[22px]" role="tabpanel">
                                        <ProcessPhotosSection photos={processPhotos} />
                                        <EngineeringDocsSection docs={engineeringDocuments} onView={openEngViewer} />
                                        {instructionCount === 0 && (
                                            <div className="text-center p-7 text-[13px] text-om-faint">{__('No reference photos or documents on this order.')}</div>
                                        )}
                                    </div>
                                )}

                                {tab === 'units' && (
                                    <div className="px-[18px] pt-4 pb-5" role="tabpanel">
                                        <SerialUnitsSection data={serialUnits} />
                                    </div>
                                )}

                            {/* Sections an installed module adds — nothing on a community install */}
                            <div className="empty:hidden px-[18px] pb-5 space-y-4">
                                <Hook name="display.operator.work_order.sections" hooks={hooks} workOrder={workOrder} />
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    {/* Beside the work from xl up; below 1280 px a 360 px column squeezed the step card, so it stacks under it */}
                    <aside className="xl:w-[360px] shrink-0 xl:overflow-auto bg-om-panel border-t xl:border-t-0 xl:border-l border-om-line2 flex flex-col">
                        {/* Issues */}
                        <div className={sideSectionCls}>
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-[14px] font-semibold text-om-ink">
                                    {__('Issues')} <span className="font-mono text-[11px] text-om-faint">{issues.length}</span>
                                </span>
                                {canReportIssue && (
                                    <button type="button" onClick={() => setReportIssueOpen(true)} className={`${sideBtnCls} text-om-blocked bg-om-blocked-bg`}>
                                        <Icon name="plus" size={14} />{__('Report')}
                                    </button>
                                )}
                            </div>
                            {issues.length === 0 ? (
                                <p className="text-[13px] text-om-faint py-1.5 m-0">{__('No issues reported.')}</p>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {issues.slice(0, 5).map((issue) => (
                                        <div key={issue.id} className="bg-om-card border border-om-line2 rounded-[10px] px-3.5 py-3">
                                            <div className="flex items-center gap-2 mb-[5px]">
                                                <span className="flex-1 min-w-0 truncate text-[11.5px] text-om-muted">{issue.issue_type?.name}</span>
                                                {issue.blocks_work_order && (
                                                    <span className="font-mono text-[8.5px] text-white bg-om-blocked rounded-full px-[7px] py-0.5">{__('BLOCKING')}</span>
                                                )}
                                                <span className={`font-mono text-[8.5px] uppercase tracking-[0.05em] rounded-full px-[7px] py-0.5 ${issueStatusCls(issue.status)}`}>{__(issue.status)}</span>
                                            </div>
                                            <div className="text-[13.5px] font-semibold text-om-ink break-words">{issue.title}</div>
                                            {issue.description && (
                                                <p className="text-xs text-om-muted mt-1 mb-0">
                                                    {issue.description.length > 80 ? `${issue.description.slice(0, 80)}…` : issue.description}
                                                </p>
                                            )}
                                            <div className="font-mono text-[10px] text-om-faint mt-[5px]">
                                                {[issue.reported_at ? formatDateTime(new Date(issue.reported_at)) : null, issue.reported_by?.name].filter(Boolean).join(' · ')}
                                            </div>
                                        </div>
                                    ))}
                                    {issues.length > 5 && (
                                        <p className="font-mono text-[10px] text-om-faint text-center m-0">{__('+:count more issues', { count: issues.length - 5 })}</p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Scrap */}
                        <div className={sideSectionCls}>
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-[14px] font-semibold text-om-ink">{__('Scrap')}</span>
                                {canScrap && (
                                    <button type="button" onClick={openScrap} className={`${sideBtnCls} text-om-downtime bg-om-downtime-bg`}>
                                        <Icon name="plus" size={14} />{__('Report')}
                                    </button>
                                )}
                            </div>
                            {hasStepLedger && <p className="text-[12.5px] text-om-muted mt-0 mb-2">{__('Record scrap on the work order steps.')}</p>}
                            <div className="flex justify-between text-[13px] text-om-muted py-[3px]">
                                <span>{__('Total scrap')}</span><span className="font-mono text-om-ink">{fmtQty(totalScrap)}</span>
                            </div>
                            <div className="flex justify-between text-[13px] text-om-muted py-[3px]">
                                <span>{__('Quality')}</span>
                                {qualityPct === null
                                    ? <span className="font-mono text-om-faint">—</span>
                                    : <span className={`font-mono ${qualityPct < 100 ? 'text-om-downtime' : 'text-om-running'}`}>{qualityPct.toFixed(1)}%</span>}
                            </div>
                            {scrapEntries.length > 0 && (
                                <div className="space-y-2 mt-3">
                                    {scrapEntries.slice(0, 5).map((entry) => (
                                        <div key={entry.id} className="bg-om-card border border-om-line2 rounded-[10px] px-3 py-2.5">
                                            <div className="flex items-center justify-between mb-1">
                                                <span className="font-mono text-[13px] font-medium text-om-ink">{fmtQty(entry.quantity)}</span>
                                                <span className="font-mono text-[10.5px] text-om-faint">
                                                    {entry.reported_at ? formatDateTime(new Date(entry.reported_at)) : ''}
                                                </span>
                                            </div>
                                            <p className="text-[12.5px] text-om-muted m-0">
                                                {entry.scrap_reason?.name || __('Unknown reason')}
                                                {entry.reported_by ? ` ${__('by')} ${entry.reported_by.name}` : ''}
                                            </p>
                                            {entry.can_classify && !entry.scrap_reason_id && scrapReasons.length > 0 && <ScrapReasonForm entry={entry} reasons={scrapReasons} />}
                                            {entry.notes && (
                                                <p className="text-xs text-om-muted mt-1 mb-0">
                                                    {entry.notes.length > 80 ? `${entry.notes.slice(0, 80)}…` : entry.notes}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                    {scrapEntries.length > 5 && (
                                        <p className="font-mono text-[10px] text-om-faint text-center m-0">+{scrapEntries.length - 5} {__('more')}</p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Order details */}
                        <div className={sideSectionCls}>
                            <div className="text-[14px] font-semibold text-om-ink mb-2.5">{__('Order details')}</div>
                            <DetailRow label={__('Product type')}>{workOrder.product_type?.name ?? '—'}</DetailRow>
                            <DetailRow label={__('Line')}>{workOrder.line?.name ?? '—'}</DetailRow>
                            <DetailRow label={__('Planned')} mono>{qtyS(plannedQty)} {__('pcs')}</DetailRow>
                            <DetailRow label={__('Created')} mono>{workOrder.created_at ? formatDate(new Date(workOrder.created_at), { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}</DetailRow>
                            <DetailRow label={__('Description')} last>
                                {workOrder.description ? workOrder.description : <span className="text-om-faint">—</span>}
                            </DetailRow>
                        </div>
                    </aside>
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

            <EngineeringViewerModal viewer={engViewer} onClose={() => setEngViewer(null)} />
        </>
    );
}

WorkOrderDetail.layout = (page) => <OperatorLayout flush>{page}</OperatorLayout>;
