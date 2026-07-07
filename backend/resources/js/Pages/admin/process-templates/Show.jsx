import { useState, useRef, useEffect } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Dropdown, Checkbox, TextField } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

/* ------------------------------------------------------------------ */
/* Small SVG helper                                                      */
/* ------------------------------------------------------------------ */
function Icon({ d, className = 'w-5 h-5' }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />
        </svg>
    );
}

/* ------------------------------------------------------------------ */
/* Shared "optional / variant" controls for the add & edit step forms.   */
/* ------------------------------------------------------------------ */
function OptionalVariantFields({ data, setData, errors }) {
    const inGroup = !!data.variant_group;
    return (
        <div className="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4 bg-om-panel rounded-om-sm p-3 border border-om-line">
            <div className="flex items-center">
                <Checkbox
                    checked={!!data.is_optional}
                    onChange={(next) => setData('is_optional', next)}
                    label={__('Optional (can be skipped)')}
                />
            </div>

            <div>
                <TextField
                    label={__('Variant group')}
                    value={data.variant_group ?? ''}
                    onChange={(v) => setData('variant_group', v)}
                    placeholder={__('e.g. finish')}
                    maxLength={50}
                />
                <p className="text-xs text-om-muted mt-1">
                    {__('Steps sharing a group are alternatives — one is run, the rest skipped.')}
                </p>
            </div>

            <div className="flex items-center">
                <Checkbox
                    checked={!!data.is_default_variant}
                    disabled={!inGroup}
                    onChange={(next) => setData('is_default_variant', next)}
                    label={__('Default variant for this product')}
                />
            </div>

            {errors.is_default_variant && (
                <p className="md:col-span-3 text-om-blocked text-xs">{errors.is_default_variant}</p>
            )}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Add-step inline form                                                  */
/* ------------------------------------------------------------------ */
function AddStepForm({ productType, processTemplate, processSegments, workstations, onCancel }) {
    const form = useForm({
        name: '',
        instruction: '',
        estimated_duration_minutes: '',
        workstation_id: '',
        process_segment_id: '',
        is_optional: false,
        variant_group: '',
        is_default_variant: false,
    });

    const { data, setData, errors, processing } = form;

    const applySegment = (segId) => {
        setData('process_segment_id', segId);
        if (!segId) return;
        const seg = processSegments.find((s) => String(s.id) === String(segId));
        if (!seg) return;
        if (!data.name) setData('name', seg.name);
        if (!data.instruction) setData('instruction', seg.instruction ?? '');
        if (!data.estimated_duration_minutes && seg.duration)
            setData('estimated_duration_minutes', String(seg.duration));
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(
            `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/steps`,
            { onSuccess: onCancel },
        );
    };

    return (
        <div className="card mb-6" style={{ borderLeft: '4px solid #3b82f6' }}>
            <div className="flex items-center justify-between mb-4">
                <h2 className="text-xl font-bold text-om-ink">{__("Add New Step")}</h2>
                <button type="button" onClick={onCancel} className="text-om-muted hover:text-om-ink">
                    <Icon d="M6 18L18 6M6 6l12 12" />
                </button>
            </div>

            <form onSubmit={submit}>
                {processSegments.length > 0 && (
                    <div className="mb-4">
                        <label className="form-label">Use Process Segment (optional)</label>
                        <Dropdown
                            value={data.process_segment_id == null ? '' : String(data.process_segment_id)}
                            onChange={(v) => applySegment(v)}
                            options={[
                                { value: '', label: __('— Define ad-hoc step —') },
                                ...processSegments.map((seg) => ({
                                    value: String(seg.id),
                                    label: `[${capitalize(seg.segment_type)}] ${seg.code} — ${seg.name}`,
                                })),
                            ]}
                            className="w-full"
                        />
                        <p className="text-xs text-om-muted mt-1">
                            Picking a segment pre-fills name, instruction and duration. You can still override after.
                        </p>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="form-label">Step Name</label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={`form-input w-full${errors.name ? ' border-om-blocked' : ''}`}
                            placeholder="e.g., Attach component A"
                            required
                        />
                        {errors.name && <p className="text-om-blocked text-xs mt-1">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="form-label">Workstation (Optional)</label>
                        <Dropdown
                            value={data.workstation_id == null ? '' : String(data.workstation_id)}
                            onChange={(v) => setData('workstation_id', v)}
                            options={[
                                { value: '', label: __('No specific workstation') },
                                ...workstations.map((ws) => ({
                                    value: String(ws.id),
                                    label: `${ws.name} (${ws.line_name ?? '-'})`,
                                })),
                            ]}
                            className="w-full"
                        />
                    </div>

                    <div className="md:col-span-2">
                        <label className="form-label">Instructions</label>
                        <textarea
                            value={data.instruction}
                            onChange={(e) => setData('instruction', e.target.value)}
                            rows={3}
                            className="form-input w-full"
                            placeholder="Detailed instructions for this step..."
                        />
                    </div>

                    <div>
                        <label className="form-label">Estimated Duration (minutes)</label>
                        <input
                            type="number"
                            value={data.estimated_duration_minutes}
                            onChange={(e) => setData('estimated_duration_minutes', e.target.value)}
                            min="0"
                            className="form-input w-full"
                            placeholder="e.g., 15"
                        />
                    </div>

                    <OptionalVariantFields data={data} setData={setData} errors={errors} />
                </div>

                <div className="flex justify-end gap-3 mt-4">
                    <button type="button" onClick={onCancel} className="btn-touch btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" disabled={processing} className="btn-touch btn-primary">
                        {processing ? 'Adding…' : 'Add Step'}
                    </button>
                </div>
            </form>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Inline step-edit form                                                 */
/* ------------------------------------------------------------------ */
function EditStepForm({ step, productType, processTemplate, processSegments, workstations, onCancel }) {
    const form = useForm({
        name: step.name ?? '',
        instruction: step.instruction ?? '',
        estimated_duration_minutes: step.estimated_duration_minutes != null ? String(step.estimated_duration_minutes) : '',
        workstation_id: step.workstation_id != null ? String(step.workstation_id) : '',
        process_segment_id: step.process_segment_id != null ? String(step.process_segment_id) : '',
        is_optional: !!step.is_optional,
        variant_group: step.variant_group ?? '',
        is_default_variant: !!step.is_default_variant,
    });

    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.put(
            `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/steps/${step.id}`,
            { onSuccess: onCancel },
        );
    };

    return (
        <form onSubmit={submit}>
            {processSegments.length > 0 && (
                <div className="mb-4">
                    <label className="form-label">Linked Process Segment</label>
                    <Dropdown
                        value={data.process_segment_id == null ? '' : String(data.process_segment_id)}
                        onChange={(v) => setData('process_segment_id', v)}
                        options={[
                            { value: '', label: __('— None (ad-hoc step) —') },
                            ...processSegments.map((seg) => ({
                                value: String(seg.id),
                                label: `[${capitalize(seg.segment_type)}] ${seg.code} — ${seg.name}`,
                            })),
                        ]}
                        className="w-full"
                    />
                    <p className="text-xs text-om-muted mt-1">
                        Step-level values override segment defaults; if blank, segment values apply.
                    </p>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="form-label">Step Name</label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={`form-input w-full${errors.name ? ' border-om-blocked' : ''}`}
                        required
                    />
                    {errors.name && <p className="text-om-blocked text-xs mt-1">{errors.name}</p>}
                </div>

                <div>
                    <label className="form-label">Workstation (Optional)</label>
                    <Dropdown
                        value={data.workstation_id == null ? '' : String(data.workstation_id)}
                        onChange={(v) => setData('workstation_id', v)}
                        options={[
                            { value: '', label: __('No specific workstation') },
                            ...workstations.map((ws) => ({
                                value: String(ws.id),
                                label: `${ws.name} (${ws.line_name ?? '-'})`,
                            })),
                        ]}
                        className="w-full"
                    />
                </div>

                <div className="md:col-span-2">
                    <label className="form-label">Instructions</label>
                    <textarea
                        value={data.instruction}
                        onChange={(e) => setData('instruction', e.target.value)}
                        rows={3}
                        className="form-input w-full"
                    />
                </div>

                <div>
                    <label className="form-label">Estimated Duration (minutes)</label>
                    <input
                        type="number"
                        value={data.estimated_duration_minutes}
                        onChange={(e) => setData('estimated_duration_minutes', e.target.value)}
                        min="0"
                        className="form-input w-full"
                    />
                </div>

                <OptionalVariantFields data={data} setData={setData} errors={errors} />
            </div>

            <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={onCancel} className="btn-touch btn-secondary">
                    Cancel
                </button>
                <button type="submit" disabled={processing} className="btn-touch btn-primary">
                    {processing ? 'Saving…' : 'Save Changes'}
                </button>
            </div>
        </form>
    );
}

/* ------------------------------------------------------------------ */
/* Single step row (view + edit toggle)                                  */
/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
/* Per-step photo (one image per step) — upload / replace / delete       */
/* ------------------------------------------------------------------ */
function StepPhoto({ step, photo, baseUrl }) {
    const form = useForm({ photo: null, template_step_id: step.id });
    const inputRef = useRef(null);
    const [zoom, setZoom] = useState(false);

    const pick = () => inputRef.current?.click();

    const onFile = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        // upload immediately (replace-on-upload, one per step)
        form.transform(() => ({ photo: file, template_step_id: step.id }));
        form.post(baseUrl, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                form.transform((d) => d);
                if (inputRef.current) inputRef.current.value = '';
            },
        });
    };

    const remove = () => {
        if (confirm(__('Delete this step photo?'))) {
            router.delete(`${baseUrl}/${photo.id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="mt-3 flex items-center gap-3">
            {photo ? (
                <>
                    <button type="button" onClick={() => setZoom(true)} title={photo.caption || photo.original_name}>
                        <img
                            src={photo.url}
                            alt={photo.caption || 'Step photo'}
                            className="w-20 h-20 object-cover rounded-om-sm border border-om-line2 bg-om-chip"
                        />
                    </button>
                    <div className="flex flex-col gap-1">
                        <button type="button" onClick={pick} disabled={form.processing} className="text-xs text-om-accent hover:underline text-left">
                            {form.processing ? __('Uploading…') : __('Replace photo')}
                        </button>
                        <button type="button" onClick={remove} className="text-xs text-om-blocked hover:underline text-left">
                            {__('Remove')}
                        </button>
                    </div>
                </>
            ) : (
                <button
                    type="button"
                    onClick={pick}
                    disabled={form.processing}
                    className="flex items-center gap-2 px-3 py-2 rounded-om-sm border border-dashed border-om-line text-sm text-om-muted hover:border-blue-400 hover:text-om-accent disabled:opacity-50"
                >
                    <Icon d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z M15 13a3 3 0 11-6 0 3 3 0 016 0z" className="w-4 h-4" />
                    {form.processing ? __('Uploading…') : __('Add step photo')}
                </button>
            )}
            {form.errors.photo && <span className="text-xs text-om-blocked">{form.errors.photo}</span>}

            <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={onFile} />

            {zoom && photo && (
                <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-6" onClick={() => setZoom(false)}>
                    <img src={photo.url} alt={photo.caption || ''} className="max-w-full max-h-[85vh] rounded-om-sm shadow-2xl" onClick={(e) => e.stopPropagation()} />
                </div>
            )}
        </div>
    );
}

// Per-step rich work-instruction authoring: upload media (image/PDF/video) and
// manage checklist items. Reusable definition; operators see/complete it live.
const MEDIA_ACCEPT = {
    image: 'image/jpeg,image/png,image/webp',
    pdf: 'application/pdf',
    video: 'video/mp4,video/webm',
};

function StepInstructionsEditor({ step, productType, processTemplate }) {
    const mediaBase = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/media`;
    const checklistBase = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/checklist-items`;
    const media = (processTemplate.media ?? []).filter((m) => m.template_step_id === step.id);
    const items = (processTemplate.checklist_items ?? []).filter((c) => c.template_step_id === step.id);

    const mediaForm = useForm({ media_type: 'pdf', file: null, title: '', template_step_id: step.id });
    const fileRef = useRef(null);
    const itemForm = useForm({ label: '', is_required: false, template_step_id: step.id });

    const onFile = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        mediaForm.transform(() => ({ media_type: mediaForm.data.media_type, title: mediaForm.data.title, template_step_id: step.id, file }));
        mediaForm.post(mediaBase, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => mediaForm.setData('title', ''),
            onFinish: () => {
                mediaForm.transform((d) => d);
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    };

    const addItem = (e) => {
        e.preventDefault();
        if (!itemForm.data.label.trim()) return;
        itemForm.post(checklistBase, { preserveScroll: true, onSuccess: () => itemForm.reset('label', 'is_required') });
    };

    return (
        <div className="mt-3 border-t border-om-line2 pt-3 space-y-3">
            {/* Media */}
            <div>
                <p className="text-xs font-semibold text-om-muted mb-1.5">{__('Work-instruction media')}</p>
                {media.length > 0 && (
                    <ul className="mb-2 space-y-1">
                        {media.map((m) => (
                            <li key={m.id} className="flex items-center gap-2 text-sm">
                                <span className="text-[10px] uppercase px-1.5 py-0.5 rounded bg-om-chip text-om-muted">
                                    {m.media_type === 'image' && __('Image')}
                                    {m.media_type === 'pdf' && __('PDF')}
                                    {m.media_type === 'video' && __('Video')}
                                    {!['image', 'pdf', 'video'].includes(m.media_type) && __(m.media_type ? m.media_type.charAt(0).toUpperCase() + m.media_type.slice(1) : '')}
                                </span>
                                <a href={m.url} target="_blank" rel="noopener noreferrer" className="text-om-accent hover:underline truncate max-w-[260px]">{m.title || m.original_name}</a>
                                <button type="button" onClick={() => router.delete(`${mediaBase}/${m.id}`, { preserveScroll: true })} className="text-xs text-om-blocked hover:underline ml-auto">{__('Remove')}</button>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="flex flex-wrap items-center gap-2">
                    <select
                        value={mediaForm.data.media_type}
                        onChange={(e) => mediaForm.setData('media_type', e.target.value)}
                        className="form-select text-sm py-1"
                    >
                        <option value="image">{__('Image')}</option>
                        <option value="pdf">{__('PDF')}</option>
                        <option value="video">{__('Video')}</option>
                    </select>
                    <input
                        type="text"
                        value={mediaForm.data.title}
                        onChange={(e) => mediaForm.setData('title', e.target.value)}
                        placeholder={__('Title (optional)')}
                        className="form-input text-sm py-1 flex-1 min-w-[120px]"
                    />
                    <button type="button" onClick={() => fileRef.current?.click()} disabled={mediaForm.processing} className="text-sm text-om-accent hover:underline disabled:opacity-50">
                        {mediaForm.processing ? __('Uploading…') : __('Upload file')}
                    </button>
                    <input ref={fileRef} type="file" accept={MEDIA_ACCEPT[mediaForm.data.media_type]} className="hidden" onChange={onFile} />
                </div>
                {mediaForm.errors.file && <p className="text-xs text-om-blocked mt-1">{mediaForm.errors.file}</p>}
            </div>

            {/* Checklist */}
            <div>
                <p className="text-xs font-semibold text-om-muted mb-1.5">{__('Checklist')}</p>
                {items.length > 0 && (
                    <ul className="mb-2 space-y-1">
                        {items.map((c) => (
                            <li key={c.id} className="flex items-center gap-2 text-sm">
                                <span className="text-om-ink">{c.label}</span>
                                {c.is_required && <span className="text-[10px] uppercase text-om-downtime">{__('required')}</span>}
                                <button type="button" onClick={() => router.delete(`${checklistBase}/${c.id}`, { preserveScroll: true })} className="text-xs text-om-blocked hover:underline ml-auto">{__('Remove')}</button>
                            </li>
                        ))}
                    </ul>
                )}
                <form onSubmit={addItem} className="flex flex-wrap items-center gap-2">
                    <input
                        type="text"
                        value={itemForm.data.label}
                        onChange={(e) => itemForm.setData('label', e.target.value)}
                        placeholder={__('Add checklist item…')}
                        className="form-input text-sm py-1 flex-1 min-w-[160px]"
                    />
                    <label className="flex items-center gap-1.5 text-xs text-om-muted">
                        <input type="checkbox" checked={itemForm.data.is_required} onChange={(e) => itemForm.setData('is_required', e.target.checked)} />
                        {__('Required')}
                    </label>
                    <button type="submit" disabled={itemForm.processing} className="text-sm text-om-accent hover:underline disabled:opacity-50">{__('Add')}</button>
                </form>
            </div>
        </div>
    );
}

function StepCard({
    step, photo, photosBaseUrl, isFirst, isLast, editingId, onEditStart, onEditCancel,
    productType, processTemplate, processSegments, workstations,
    onMoveUp, onMoveDown, onDelete,
    dragHandleProps,
}) {
    const isEditing = editingId === step.id;

    return (
        <div className="card" {...dragHandleProps}>
            {!isEditing ? (
                <div className="flex items-start justify-between">
                    <div className="flex gap-4 flex-1">
                        {/* Drag handle */}
                        <div
                            className="drag-handle flex-shrink-0 flex items-center cursor-grab active:cursor-grabbing text-om-faintest hover:text-om-muted transition-colors px-1 self-start mt-3"
                            title="Drag to reorder"
                        >
                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="9" cy="5" r="1.5" />
                                <circle cx="15" cy="5" r="1.5" />
                                <circle cx="9" cy="12" r="1.5" />
                                <circle cx="15" cy="12" r="1.5" />
                                <circle cx="9" cy="19" r="1.5" />
                                <circle cx="15" cy="19" r="1.5" />
                            </svg>
                        </div>

                        <div className="flex-shrink-0 w-12 h-12 bg-om-chip rounded-full flex items-center justify-center step-number-badge">
                            <span className="text-lg font-bold text-om-accent">{step.step_number}</span>
                        </div>

                        <div className="flex-1">
                            <div className="flex items-start justify-between mb-2">
                                <div className="flex-1">
                                    <h3 className="text-lg font-bold text-om-ink inline-flex items-center gap-2 flex-wrap">
                                        {step.name}
                                        {step.is_optional && (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-om-downtime-bg text-om-downtime">
                                                {__('Optional')}
                                            </span>
                                        )}
                                        {step.variant_group && (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-om-chip text-om-accent">
                                                {__('Variant')}: {step.variant_group}{step.is_default_variant ? ` (${__('default')})` : ''}
                                            </span>
                                        )}
                                    </h3>

                                    {step.process_segment && (
                                        <p className="mt-1">
                                            <a
                                                href={`/admin/process-segments/${step.process_segment.id}`}
                                                className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-om-chip"
                                                title="ISA-95 Process Segment"
                                            >
                                                <Icon
                                                    d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6z"
                                                    className="w-3 h-3"
                                                />
                                                {step.process_segment.code}
                                            </a>
                                        </p>
                                    )}

                                    {step.workstation && (
                                        <p className="text-sm text-om-muted mt-1">
                                            <Icon
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                                className="w-4 h-4 inline-block mr-1"
                                            />
                                            {step.workstation.name} ({step.workstation.line_name ?? '-'})
                                        </p>
                                    )}

                                    {step.estimated_duration_minutes != null && (
                                        <p className="text-sm text-om-muted">
                                            <Icon
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                                className="w-4 h-4 inline-block mr-1"
                                            />
                                            ~{step.estimated_duration_minutes} min
                                        </p>
                                    )}
                                </div>

                                {/* Actions */}
                                <div className="flex gap-1 ml-4">
                                    <button
                                        type="button"
                                        onClick={() => onEditStart(step.id)}
                                        className="text-om-accent hover:text-om-accent p-2"
                                        title="Edit"
                                    >
                                        <Icon d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </button>

                                    {!isFirst && (
                                        <button
                                            type="button"
                                            onClick={() => onMoveUp(step)}
                                            className="text-om-muted hover:text-om-ink p-2"
                                            title="Move up"
                                        >
                                            <Icon d="M5 15l7-7 7 7" />
                                        </button>
                                    )}

                                    {!isLast && (
                                        <button
                                            type="button"
                                            onClick={() => onMoveDown(step)}
                                            className="text-om-muted hover:text-om-ink p-2"
                                            title="Move down"
                                        >
                                            <Icon d="M19 9l-7 7-7-7" />
                                        </button>
                                    )}

                                    <button
                                        type="button"
                                        onClick={() => onDelete(step)}
                                        className="text-om-blocked hover:text-om-blocked p-2"
                                        title="Delete"
                                    >
                                        <Icon d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </button>
                                </div>
                            </div>

                            {step.instruction && (
                                <div className="mt-2 p-3 bg-om-panel rounded-om-sm">
                                    <p className="text-sm text-om-muted whitespace-pre-wrap">{step.instruction}</p>
                                </div>
                            )}

                            <StepPhoto step={step} photo={photo} baseUrl={photosBaseUrl} />

                            <StepInstructionsEditor step={step} productType={productType} processTemplate={processTemplate} />
                        </div>
                    </div>
                </div>
            ) : (
                <EditStepForm
                    step={step}
                    productType={productType}
                    processTemplate={processTemplate}
                    processSegments={processSegments}
                    workstations={workstations}
                    onCancel={onEditCancel}
                />
            )}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Main page component                                                   */
/* ------------------------------------------------------------------ */
export default function ProcessTemplatesShow() {
    const { productType, processTemplate, workstations = [], processSegments = [] } = usePage().props;

    const steps = processTemplate.steps ?? [];
    const allPhotos = processTemplate.photos ?? [];
    const photoByStep = {};
    allPhotos.forEach((p) => {
        if (p.template_step_id) photoByStep[p.template_step_id] = p;
    });
    const photosBaseUrl = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/photos`;
    const [showAddForm, setShowAddForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [saveStatus, setSaveStatus] = useState(null); // 'saving' | 'saved' | 'error'

    const handleMoveUp = (step) => {
        router.post(
            `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/steps/${step.id}/move-up`,
            {},
            { preserveScroll: true },
        );
    };

    const handleMoveDown = (step) => {
        router.post(
            `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/steps/${step.id}/move-down`,
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = (step) => {
        if (!confirm('Delete this step?')) return;
        router.delete(
            `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/steps/${step.id}`,
            { preserveScroll: true },
        );
    };

    /* Drag-sort via SortableJS (loaded globally via vendor/sortable.min.js) */
    const listRef = useRef(null);
    useEffect(() => {
        if (typeof window === 'undefined' || !window.Sortable) return;
        if (!listRef.current) return;

        const reorderUrl = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/reorder-steps`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const sortable = window.Sortable.create(listRef.current, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            chosenClass: 'sortable-chosen',
            onEnd() {
                const order = Array.from(listRef.current.children).map((el) =>
                    parseInt(el.dataset.stepId, 10),
                );
                // Optimistic badge update
                listRef.current.querySelectorAll('.step-number-badge span').forEach((el, i) => {
                    el.textContent = i + 1;
                });

                setSaveStatus('saving');
                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ order }),
                })
                    .then((res) => {
                        if (!res.ok) throw new Error('Server error');
                        setSaveStatus('saved');
                    })
                    .catch(() => setSaveStatus('error'))
                    .finally(() => {
                        setTimeout(() => setSaveStatus(null), 2000);
                    });
            },
        });

        return () => sortable.destroy();
    }, [steps.length, productType.id, processTemplate.id]);

    return (
        <>
            <Head title={`${processTemplate.name} — Process Template`} />

            <style>{`
                .sortable-ghost  { opacity: 0.4; }
                .sortable-drag   { opacity: 0.9; box-shadow: 0 8px 24px rgba(0,0,0,.15); }
                .sortable-chosen { background: #f0f7ff; }
            `}</style>

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <a
                        href={`/admin/product-types/${productType.id}/process-templates`}
                        className="text-om-accent hover:text-om-accent flex items-center gap-2 mb-4"
                    >
                        <Icon d="M15 19l-7-7 7-7" />
                        {__("Back to Templates")}
                    </a>

                    <div className="flex items-center justify-between">
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-3xl font-bold text-om-ink">{processTemplate.name}</h1>
                                {processTemplate.is_active ? (
                                    <span className="px-3 py-1 bg-om-running-bg text-om-running rounded-full text-sm font-medium">
                                        Active
                                    </span>
                                ) : (
                                    <span className="px-3 py-1 bg-om-chip text-om-muted rounded-full text-sm font-medium">
                                        Inactive
                                    </span>
                                )}
                                <span className="px-3 py-1 bg-om-chip text-om-accent rounded-full text-sm font-medium">
                                    v{processTemplate.version}
                                </span>
                            </div>
                            <p className="text-sm text-om-muted mt-1">
                                {productType.name} &bull; {steps.length} steps
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <a
                                href={`/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/edit`}
                                className="btn-touch btn-secondary"
                            >
                                <Icon
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                    className="w-5 h-5 inline-block mr-2"
                                />
                                Edit Template
                            </a>

                            <a
                                href={`/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/bom`}
                                className="btn-touch btn-secondary"
                            >
                                <Icon
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                    className="w-5 h-5 inline-block mr-2"
                                />
                                BOM
                            </a>

                            <button
                                type="button"
                                onClick={() => setShowAddForm(true)}
                                className="btn-touch btn-primary"
                            >
                                <Icon d="M12 4v16m8-8H4" className="w-5 h-5 inline-block mr-2" />
                                Add Step
                            </button>
                        </div>
                    </div>
                </div>

                {/* Add Step Form */}
                {showAddForm && (
                    <AddStepForm
                        productType={productType}
                        processTemplate={processTemplate}
                        processSegments={processSegments}
                        workstations={workstations}
                        onCancel={() => setShowAddForm(false)}
                    />
                )}

                {/* Steps List header */}
                <div className="flex items-center gap-2 mb-4">
                    <h2 className="text-xl font-bold text-om-ink">{__("Production Steps")}</h2>
                    <span className="text-sm text-om-muted">({__("first to last")})</span>
                </div>

                {steps.length > 0 ? (
                    <div className="space-y-3" ref={listRef}>
                        {steps.map((step, idx) => (
                            <div key={step.id} data-step-id={step.id}>
                                <StepCard
                                    step={step}
                                    photo={photoByStep[step.id] ?? null}
                                    photosBaseUrl={photosBaseUrl}
                                    isFirst={idx === 0}
                                    isLast={idx === steps.length - 1}
                                    editingId={editingId}
                                    onEditStart={(id) => setEditingId(id)}
                                    onEditCancel={() => setEditingId(null)}
                                    productType={productType}
                                    processTemplate={processTemplate}
                                    processSegments={processSegments}
                                    workstations={workstations}
                                    onMoveUp={handleMoveUp}
                                    onMoveDown={handleMoveDown}
                                    onDelete={handleDelete}
                                />
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="card text-center py-12">
                        <svg className="mx-auto h-16 w-16 text-om-faint mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p className="text-lg font-medium text-om-muted">{__("No production steps yet")}</p>
                        <p className="text-sm text-om-muted mt-1 mb-4">
                            {__("Add steps to define the manufacturing process for this product.")}
                        </p>
                        <button
                            type="button"
                            onClick={() => setShowAddForm(true)}
                            className="inline-block btn-touch btn-primary"
                        >
                            <Icon d="M12 4v16m8-8H4" className="w-5 h-5 inline-block mr-2" />
                            {__("Add First Step")}
                        </button>
                    </div>
                )}

                {/* Reference photos (work instructions) */}
                <PhotosSection productType={productType} processTemplate={processTemplate} />

                {/* Drag-sort save status toast */}
                {saveStatus && (
                    <div
                        className={`fixed bottom-5 right-5 px-4 py-2 rounded-om-sm text-white text-sm font-medium z-50 transition-opacity ${
                            saveStatus === 'saving'
                                ? 'bg-indigo-500'
                                : saveStatus === 'saved'
                                ? 'bg-om-running'
                                : 'bg-om-blocked'
                        }`}
                    >
                        {saveStatus === 'saving' && 'Saving…'}
                        {saveStatus === 'saved' && 'Saved'}
                        {saveStatus === 'error' && 'Error — reload page'}
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Reference photos for the template. Upload accepts JPEG/PNG/WebP only;
 * the server re-encodes every image (strips EXIF and any embedded payloads)
 * and serves files through an authenticated endpoint — never a public URL.
 */
function PhotosSection({ productType, processTemplate }) {
    // Only general (non-step) photos here; per-step photos live on each StepCard.
    const photos = (processTemplate.photos ?? []).filter((p) => !p.template_step_id);
    const baseUrl = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/photos`;

    const form = useForm({ photo: null, caption: '' });
    const fileInputRef = useRef(null);
    const [lightbox, setLightbox] = useState(null); // photo object or null

    const submit = (e) => {
        e.preventDefault();
        if (!form.data.photo) return;
        form.post(baseUrl, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const handleDelete = (photo) => {
        if (!confirm(__('Delete this photo?'))) return;
        router.delete(`${baseUrl}/${photo.id}`, { preserveScroll: true });
    };

    return (
        <div className="mt-10">
            <div className="flex items-center gap-2 mb-4">
                <h2 className="text-xl font-bold text-om-ink">{__("General Reference Photos")}</h2>
                <span className="text-sm text-om-muted">({photos.length}/20)</span>
            </div>

            {/* Upload form */}
            <form onSubmit={submit} className="card mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">
                        {__("Photo")} <span className="text-xs text-om-faint">{__("(JPEG/PNG/WebP, max 10 MB)")}</span>
                    </label>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(e) => form.setData('photo', e.target.files[0] ?? null)}
                        className="block text-sm text-om-muted file:mr-3 file:px-3 file:py-1.5 file:rounded-om-sm file:border-0 file:bg-om-chip file:text-om-accent file:text-sm file:font-medium hover:file:bg-om-chip"
                    />
                </div>
                <div className="flex-1 min-w-[200px]">
                    <label className="block text-sm font-medium text-om-muted mb-1">{__("Caption")}</label>
                    <input
                        type="text"
                        value={form.data.caption}
                        onChange={(e) => form.setData('caption', e.target.value)}
                            placeholder={__("Optional description")}
                        className="form-input w-full"
                    />
                </div>
                <button
                    type="submit"
                    disabled={form.processing || !form.data.photo}
                    className="btn-touch btn-primary disabled:opacity-50"
                >
                    {form.processing ? __('Uploading…') : __('Upload')}
                </button>
                {form.errors.photo && <p className="w-full text-sm text-om-blocked">{form.errors.photo}</p>}
                {form.errors.caption && <p className="w-full text-sm text-om-blocked">{form.errors.caption}</p>}
            </form>
 
            {/* Photo grid */}
            {photos.length > 0 ? (
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    {photos.map((photo) => (
                        <div key={photo.id} className="card p-2 group relative">
                            <button
                                type="button"
                                onClick={() => setLightbox(photo)}
                                className="block w-full"
                                title={photo.original_name}
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.caption || photo.original_name}
                                    loading="lazy"
                                    className="w-full h-32 object-cover rounded-om-sm bg-om-chip"
                                />
                            </button>
                            <div className="mt-2 text-xs text-om-muted truncate" title={photo.caption || ''}>
                                {photo.caption || <span className="text-om-faint">{__("No caption")}</span>}
                            </div>
                            <div className="text-[10px] text-om-faint">
                                {photo.width}×{photo.height} • {photo.file_size}
                            </div>
                            <button
                                type="button"
                                onClick={() => handleDelete(photo)}
                                className="absolute top-3 right-3 bg-om-card/90 text-om-blocked rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow"
                                title={__("Delete photo")}
                            >
                                <Icon d="M6 18L18 6M6 6l12 12" className="w-4 h-4" />
                            </button>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="card text-center py-8 text-sm text-om-muted">
                    {__("No reference photos yet. Upload assembly/work-instruction images for operators.")}
                </div>
            )}

            {/* Lightbox */}
            {lightbox && (
                <div
                    className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-6"
                    onClick={() => setLightbox(null)}
                >
                    <figure className="max-w-4xl max-h-full" onClick={(e) => e.stopPropagation()}>
                        <img
                            src={lightbox.url}
                            alt={lightbox.caption || lightbox.original_name}
                            className="max-w-full max-h-[80vh] rounded-om-sm shadow-2xl"
                        />
                        <figcaption className="text-white/90 text-sm mt-3 text-center">
                            {lightbox.caption || lightbox.original_name}
                            {lightbox.uploaded_by && (
                                <span className="text-white/50"> — {lightbox.uploaded_by}, {lightbox.created_at}</span>
                            )}
                        </figcaption>
                    </figure>
                    <button
                        type="button"
                        onClick={() => setLightbox(null)}
                        className="absolute top-5 right-5 text-white/80 hover:text-white"
                        title="Close"
                    >
                        <Icon d="M6 18L18 6M6 6l12 12" className="w-8 h-8" />
                    </button>
                </div>
            )}
        </div>
    );
}

ProcessTemplatesShow.layout = (page) => <AppLayout>{page}</AppLayout>;

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
