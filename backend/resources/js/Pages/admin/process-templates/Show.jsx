import { useState, useRef, useEffect } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Breadcrumbs, Button, Dropdown, Checkbox, Icon as UiIcon, TextField } from '@openmes/ui';
import PageTitle from '../../../components/PageTitle';
import { DragDropProvider } from '@dnd-kit/react';
import { useSortable } from '@dnd-kit/react/sortable';
import AppLayout from '../../../layouts/AppLayout';
// Explicit extension: the helper module `engineeringDocuments.js` differs only in
// case and would resolve wrong on case-insensitive filesystems.
import EngineeringDocuments from '../../../components/EngineeringDocuments.jsx';
import RoutingGraph from './RoutingGraph';
import { __ } from '../../../lib/i18n';
import Tooltip from '../../../components/Tooltip';
import useConfirm from '../../../components/useConfirm';

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

            <div className="md:col-span-3 flex items-center border-t border-om-line pt-3">
                <Checkbox
                    checked={!!data.requires_confirmation}
                    onChange={(next) => setData('requires_confirmation', next)}
                    label={__('Require operator to confirm they read the instructions')}
                />
            </div>
            <p className="md:col-span-3 -mt-2 text-xs text-om-muted">
                {__('When on, the operator must acknowledge the instructions before this step can be completed.')}
            </p>
        </div>
    );
}

/**
 * ISA-95 step fields (#52): the required Equipment Class (workstation type) and
 * the Level-4 standard times (setup + run-per-unit) that flow down from an ERP
 * BOM. Shared by the add- and edit-step forms.
 */
function Isa95StepFields({ data, setData, workstationTypes = [] }) {
    return (
        <div className="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4 bg-om-panel rounded-om-sm p-3 border border-om-line">
            <div>
                <div className="form-label">{__('Workstation type (ISA-95)')}</div>
                <Dropdown
                    aria-label={__('Workstation type (ISA-95)')}
                    value={data.workstation_type_id == null ? '' : String(data.workstation_type_id)}
                    onChange={(v) => setData('workstation_type_id', v)}
                    options={[
                        { value: '', label: __('— Any / none —') },
                        ...workstationTypes.map((t) => ({ value: String(t.id), label: t.name })),
                    ]}
                    className="w-full"
                />
                <p className="text-xs text-om-muted mt-1">
                    {__('Required Equipment Class; a specific machine is assigned at dispatch.')}
                </p>
            </div>
            <div>
                <TextField
                    label={__('Setup time (minutes)')}
                    type="number"
                    min="0"
                    value={data.setup_time_minutes}
                    onChange={(v) => setData('setup_time_minutes', v)}
                    placeholder={__('fixed, per run')}
                />
            </div>
            <div>
                <TextField
                    label={__('Run time per unit (minutes)')}
                    type="number"
                    min="0"
                    step="0.01"
                    value={data.run_time_per_unit_minutes}
                    onChange={(v) => setData('run_time_per_unit_minutes', v)}
                    placeholder={__('× quantity')}
                />
            </div>
        </div>
    );
}

// Equipment key:value parameters for a step (temperature, humidity, …). Edited as
// rows and stored as a flat object; a client reads it from the work-order snapshot
// (or the live template) to drive equipment.
function ParametersEditor({ value = {}, onChange }) {
    const rows = Object.entries(value ?? {});

    const emit = (pairs) => {
        const obj = {};
        for (const [k, v] of pairs) {
            if (String(k).trim() !== '') obj[k] = v;
        }
        onChange(obj);
    };
    const setRow = (i, key, val) => emit(rows.map((r, idx) => (idx === i ? [key, val] : r)));
    const addRow = () => onChange({ ...(value ?? {}), '': '' });
    const removeRow = (i) => emit(rows.filter((_, idx) => idx !== i));

    return (
        <div className="md:col-span-2 bg-om-panel rounded-om-sm p-3 border border-om-line">
            <label className="form-label">{__('Equipment parameters')}</label>
            <p className="text-xs text-om-muted mb-2">
                {__('Key:value settings the equipment needs (e.g. temperature, humidity). Read via API.')}
            </p>
            <div className="space-y-2">
                {rows.map(([k, v], i) => (
                    <div key={i} className="flex items-center gap-2">
                        <div className="w-1/2">
                            <TextField mono value={k} onChange={(next) => setRow(i, next, v)} placeholder={__('key (e.g. temperature_c)')} aria-label={__('key (e.g. temperature_c)')} />
                        </div>
                        <div className="w-1/2">
                            <TextField mono value={v ?? ''} onChange={(next) => setRow(i, k, next)} placeholder={__('value')} aria-label={__('value')} />
                        </div>
                        <button
                            type="button"
                            onClick={() => removeRow(i)}
                            className="px-2 text-om-muted hover:text-om-blocked"
                            title={__('Remove')}
                        >
                            ×
                        </button>
                    </div>
                ))}
            </div>
            <button type="button" onClick={addRow} className="mt-2 inline-flex items-center gap-1 text-xs text-om-accent hover:underline">
                <UiIcon name="plus" size={12} />
                {__('Add parameter')}
            </button>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Add-step inline form                                                  */
/* ------------------------------------------------------------------ */
function AddStepForm({ productType, processTemplate, processSegments, workstations, workstationTypes = [], onCancel }) {
    const form = useForm({
        name: '',
        instruction: '',
        requires_confirmation: false,
        estimated_duration_minutes: '',
        setup_time_minutes: '',
        run_time_per_unit_minutes: '',
        required_operators: '',
        workstation_id: '',
        workstation_type_id: '',
        parameters: {},
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
        <div className="card mb-6" style={{ borderLeft: '4px solid var(--om-accent)' }}>
            <div className="flex items-center justify-between mb-4">
                <h2 className="text-xl font-bold text-om-ink">{__("Add New Step")}</h2>
                <button type="button" onClick={onCancel} className="text-om-muted hover:text-om-ink">
                    <Icon d="M6 18L18 6M6 6l12 12" />
                </button>
            </div>

            <form onSubmit={submit}>
                {processSegments.length > 0 && (
                    <div className="mb-4">
                        <div className="form-label">{__("Use Process Segment (optional)")}</div>
                        <Dropdown
                            aria-label="Use Process Segment (optional)"
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
                        <TextField
                            label={__('Step Name')}
                            required
                            value={data.name}
                            onChange={(v) => setData('name', v)}
                            placeholder={__('e.g., Attach component A')}
                            error={errors.name}
                        />
                    </div>

                    <div>
                        <div className="form-label">{__("Workstation (Optional)")}</div>
                        <Dropdown
                            aria-label="Workstation (Optional)"
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
                        <TextField
                            label={__('Instructions')}
                            multiline
                            rows={3}
                            value={data.instruction}
                            onChange={(v) => setData('instruction', v)}
                            placeholder={__('Detailed instructions for this step...')}
                        />
                    </div>

                    <div>
                        <TextField
                            label={__('Estimated Duration (minutes)')}
                            type="number"
                            min="0"
                            value={data.estimated_duration_minutes}
                            onChange={(v) => setData('estimated_duration_minutes', v)}
                            placeholder={__('e.g., 15')}
                        />
                    </div>

                    <div>
                        <TextField
                            label={__('Operators Required')}
                            type="number"
                            min="1"
                            value={data.required_operators}
                            onChange={(v) => setData('required_operators', v)}
                            placeholder={__('Inherit from segment')}
                            hint={__('People needed to run this step (drives crew labor demand). Blank inherits the linked segment, else 1.')}
                        />
                    </div>

                    <Isa95StepFields data={data} setData={setData} workstationTypes={workstationTypes} />
                    <ParametersEditor value={data.parameters} onChange={(v) => setData('parameters', v)} />
                    <OptionalVariantFields data={data} setData={setData} errors={errors} />
                </div>

                <div className="flex justify-end gap-3 mt-4">
                    <Button variant="secondary" onClick={onCancel}>{__('Cancel')}</Button>
                    <Button type="submit" loading={processing}>{processing ? __('Adding…') : __('Add Step')}</Button>
                </div>
            </form>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Inline step-edit form                                                 */
/* ------------------------------------------------------------------ */
function EditStepForm({ step, productType, processTemplate, processSegments, workstations, workstationTypes = [], onCancel }) {
    const form = useForm({
        name: step.name ?? '',
        instruction: step.instruction ?? '',
        requires_confirmation: !!step.requires_confirmation,
        estimated_duration_minutes: step.estimated_duration_minutes != null ? String(step.estimated_duration_minutes) : '',
        setup_time_minutes: step.setup_time_minutes != null ? String(step.setup_time_minutes) : '',
        run_time_per_unit_minutes: step.run_time_per_unit_minutes != null ? String(step.run_time_per_unit_minutes) : '',
        required_operators: step.required_operators != null ? String(step.required_operators) : '',
        workstation_id: step.workstation_id != null ? String(step.workstation_id) : '',
        workstation_type_id: step.workstation_type_id != null ? String(step.workstation_type_id) : '',
        parameters: step.parameters ?? {},
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
                    <div className="form-label">{__("Linked Process Segment")}</div>
                    <Dropdown
                        aria-label="Linked Process Segment"
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
                    <TextField
                        label={__('Step Name')}
                        required
                        value={data.name}
                        onChange={(v) => setData('name', v)}
                        error={errors.name}
                    />
                </div>

                <div>
                    <div className="form-label">{__("Workstation (Optional)")}</div>
                    <Dropdown
                        aria-label="Workstation (Optional)"
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
                    <TextField
                        label={__('Instructions')}
                        multiline
                        rows={3}
                        value={data.instruction}
                        onChange={(v) => setData('instruction', v)}
                    />
                </div>

                <div>
                    <TextField
                        label={__('Estimated Duration (minutes)')}
                        type="number"
                        min="0"
                        value={data.estimated_duration_minutes}
                        onChange={(v) => setData('estimated_duration_minutes', v)}
                    />
                </div>

                <div>
                    <TextField
                        label={__('Operators Required')}
                        type="number"
                        min="1"
                        value={data.required_operators}
                        onChange={(v) => setData('required_operators', v)}
                        placeholder={__('Inherit from segment')}
                        hint={__('People needed to run this step (drives crew labor demand). Blank inherits the linked segment, else 1.')}
                    />
                </div>

                <Isa95StepFields data={data} setData={setData} workstationTypes={workstationTypes} />
                <ParametersEditor value={data.parameters} onChange={(v) => setData('parameters', v)} />
                <OptionalVariantFields data={data} setData={setData} errors={errors} />
            </div>

            <div className="flex justify-end gap-3 mt-4">
                <Button variant="secondary" onClick={onCancel}>{__('Cancel')}</Button>
                <Button type="submit" loading={processing}>{processing ? __('Saving…') : __('Save Changes')}</Button>
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
    const { confirm, dialog } = useConfirm();

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
        confirm({ title: __('Delete this step photo?') }, () => {
            router.delete(`${baseUrl}/${photo.id}`, { preserveScroll: true });
        });
    };

    return (
        <div className="mt-3 flex items-center gap-3">
            {photo ? (
                <>
                    <Tooltip label={photo.caption || photo.original_name}>
                        <button
                            type="button"
                            onClick={() => setZoom(true)}
                            aria-label={photo.caption || photo.original_name}
                        >
                            <img
                                src={photo.url}
                                alt={photo.caption || 'Step photo'}
                                className="w-20 h-20 object-cover rounded-om-sm border border-om-line2 bg-om-chip"
                            />
                        </button>
                    </Tooltip>
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
            {dialog}
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

    const outputsBase = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/outputs`;
    const outputs = (processTemplate.outputs ?? []).filter((o) => o.template_step_id === step.id);
    const outputForm = useForm({ key: '', label: '', value_type: 'text', unit: '', options: '', is_required: false, template_step_id: step.id });

    const addOutput = (e) => {
        e.preventDefault();
        if (!outputForm.data.key.trim() || !outputForm.data.label.trim()) return;
        outputForm.transform((d) => ({
            ...d,
            options: d.value_type === 'select'
                ? d.options.split(',').map((s) => s.trim()).filter(Boolean)
                : null,
        }));
        outputForm.post(outputsBase, {
            preserveScroll: true,
            onSuccess: () => outputForm.reset('key', 'label', 'unit', 'options', 'is_required'),
            onFinish: () => outputForm.transform((d) => d),
        });
    };

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
                    <Dropdown
                        size="sm"
                        className="min-w-[110px]"
                        value={mediaForm.data.media_type}
                        onChange={(v) => v && mediaForm.setData('media_type', v)}
                        options={[
                            { value: 'image', label: __('Image') },
                            { value: 'pdf', label: __('PDF') },
                            { value: 'video', label: __('Video') },
                        ]}
                        aria-label={__('Media type')}
                    />
                    <div className="flex-1 min-w-[140px]">
                        <TextField
                            value={mediaForm.data.title}
                            onChange={(v) => mediaForm.setData('title', v)}
                            placeholder={__('Title (optional)')}
                            aria-label={__('Title (optional)')}
                        />
                    </div>
                    <Button variant="secondary" size="sm" loading={mediaForm.processing} onClick={() => fileRef.current?.click()}>
                        {mediaForm.processing ? __('Uploading…') : __('Upload file')}
                    </Button>
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
                    <div className="flex-1 min-w-[180px]">
                        <TextField
                            value={itemForm.data.label}
                            onChange={(v) => itemForm.setData('label', v)}
                            placeholder={__('Add checklist item…')}
                            aria-label={__('Add checklist item…')}
                        />
                    </div>
                    <Checkbox
                        size="sm"
                        checked={itemForm.data.is_required}
                        onChange={(next) => itemForm.setData('is_required', next)}
                        label={__('Required')}
                    />
                    <Button type="submit" variant="secondary" size="sm" loading={itemForm.processing}>{__('Add')}</Button>
                </form>
            </div>

            {/* Typed operator outputs */}
            <div>
                <p className="text-xs font-semibold text-om-muted mb-1.5">{__('Operator outputs')}</p>
                {outputs.length > 0 && (
                    <ul className="mb-2 space-y-1">
                        {outputs.map((o) => (
                            <li key={o.id} className="flex items-center gap-2 text-sm">
                                <span className="text-om-ink">{o.label}</span>
                                <span className="font-mono text-[10px] text-om-muted">{o.key}</span>
                                <span className="text-[10px] uppercase px-1.5 py-0.5 rounded bg-om-chip text-om-muted">{o.value_type}</span>
                                {o.is_required && <span className="text-[10px] uppercase text-om-downtime">{__('required')}</span>}
                                <button type="button" onClick={() => router.delete(`${outputsBase}/${o.id}`, { preserveScroll: true })} className="text-xs text-om-blocked hover:underline ml-auto">{__('Remove')}</button>
                            </li>
                        ))}
                    </ul>
                )}
                <form onSubmit={addOutput} className="flex flex-wrap items-center gap-2">
                    <div className="w-[160px]">
                        <TextField mono value={outputForm.data.key} onChange={(v) => outputForm.setData('key', v)} placeholder={__('key (e.g. output_qcpic)')} aria-label={__('key (e.g. output_qcpic)')} />
                    </div>
                    <div className="flex-1 min-w-[140px]">
                        <TextField value={outputForm.data.label} onChange={(v) => outputForm.setData('label', v)} placeholder={__('Label')} aria-label={__('Label')} />
                    </div>
                    <Dropdown
                        size="sm"
                        className="min-w-[110px]"
                        value={outputForm.data.value_type}
                        onChange={(v) => v && outputForm.setData('value_type', v)}
                        options={[
                            { value: 'text', label: __('Text') },
                            { value: 'number', label: __('Number') },
                            { value: 'boolean', label: __('Yes/No') },
                            { value: 'select', label: __('Select') },
                            { value: 'date', label: __('Date') },
                            { value: 'picture', label: __('Picture') },
                        ]}
                        aria-label={__('Value type')}
                    />
                    {outputForm.data.value_type === 'number' && (
                        <div className="w-[90px]">
                            <TextField value={outputForm.data.unit} onChange={(v) => outputForm.setData('unit', v)} placeholder={__('unit')} aria-label={__('unit')} />
                        </div>
                    )}
                    {outputForm.data.value_type === 'select' && (
                        <div className="w-[200px]">
                            <TextField value={outputForm.data.options} onChange={(v) => outputForm.setData('options', v)} placeholder={__('options, comma-separated')} aria-label={__('options, comma-separated')} />
                        </div>
                    )}
                    <Checkbox
                        size="sm"
                        checked={outputForm.data.is_required}
                        onChange={(next) => outputForm.setData('is_required', next)}
                        label={__('Required')}
                    />
                    <Button type="submit" variant="secondary" size="sm" loading={outputForm.processing}>{__('Add')}</Button>
                </form>
                {outputForm.errors.options && <p className="text-xs text-om-blocked mt-1">{outputForm.errors.options}</p>}
                {outputForm.errors.key && <p className="text-xs text-om-blocked mt-1">{outputForm.errors.key}</p>}
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Main page component                                                   */
function StepCard({
    step, photo, photosBaseUrl, isFirst, isLast, editingId, onEditStart, onEditCancel,
    productType, processTemplate, processSegments, workstations, workstationTypes = [],
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
                        <Tooltip label="Drag to reorder">
                            <div
                                className="drag-handle flex-shrink-0 flex items-center cursor-grab active:cursor-grabbing text-om-faintest hover:text-om-muted transition-colors px-1 self-start mt-3"
                                role="img"
                                aria-label="Drag to reorder"
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
                        </Tooltip>

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
                                        {step.requires_confirmation && (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-om-blocked-bg text-om-blocked">
                                                {__('Read-confirmation')}
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
                                    <Tooltip label="Edit">
                                        <button
                                            type="button"
                                            onClick={() => onEditStart(step.id)}
                                            className="text-om-accent hover:text-om-accent p-2"
                                            aria-label="Edit"
                                        >
                                            <Icon d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </button>
                                    </Tooltip>

                                    {!isFirst && (
                                        <Tooltip label="Move up">
                                            <button
                                                type="button"
                                                onClick={() => onMoveUp(step)}
                                                className="text-om-muted hover:text-om-ink p-2"
                                                aria-label="Move up"
                                            >
                                                <Icon d="M5 15l7-7 7 7" />
                                            </button>
                                        </Tooltip>
                                    )}

                                    {!isLast && (
                                        <Tooltip label="Move down">
                                            <button
                                                type="button"
                                                onClick={() => onMoveDown(step)}
                                                className="text-om-muted hover:text-om-ink p-2"
                                                aria-label="Move down"
                                            >
                                                <Icon d="M19 9l-7 7-7-7" />
                                            </button>
                                        </Tooltip>
                                    )}

                                    <Tooltip label="Delete">
                                        <button
                                            type="button"
                                            onClick={() => onDelete(step)}
                                            className="text-om-blocked hover:text-om-blocked p-2"
                                            aria-label="Delete"
                                        >
                                            <Icon d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </button>
                                    </Tooltip>
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
                    workstationTypes={workstationTypes}
                    onCancel={onEditCancel}
                />
            )}
        </div>
    );
}


/**
 * One sortable rail row — a component because `useSortable` is a hook and the
 * rows are a `.map()`. `ref` goes on the card (dnd-kit moves it), `handleRef`
 * on the grip, so clicking the card still just selects the step.
 */
function RailStep({ id, index, disabled, children }) {
    const { ref, handleRef, isDragging } = useSortable({ id, index, disabled });

    return children({ ref, handleRef, isDragging });
}

/* ------------------------------------------------------------------ */
export default function ProcessTemplatesShow() {
    const { productType, processTemplate, workstations = [], processSegments = [], workstationTypes = [] } = usePage().props;

    const steps = processTemplate.steps ?? [];
    const allPhotos = processTemplate.photos ?? [];
    const photoByStep = {};
    allPhotos.forEach((p) => {
        if (p.template_step_id) photoByStep[p.template_step_id] = p;
    });
    const photosBaseUrl = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/photos`;
    const [showAddForm, setShowAddForm] = useState(false);
    const [selectedStepId, setSelectedStepId] = useState(null);
    const selectedStep = steps.find((st) => st.id === selectedStepId) ?? steps[0] ?? null;
    const totalMinutes = steps.reduce((acc, st) => acc + (Number(st.estimated_duration_minutes) || 0), 0);

    /** Compact one-line context for the rail: workstation/class · duration · flags. */
    const railSubline = (st) => [
        st.workstation?.name ?? st.workstation_type?.name ?? null,
        st.estimated_duration_minutes != null ? `${st.estimated_duration_minutes}m` : null,
        st.variant_group ? __('variant') : null,
        st.is_optional ? __('optional') : null,
    ].filter(Boolean).join(' · ');

    const [editingId, setEditingId] = useState(null);
    const [saveStatus, setSaveStatus] = useState(null); // 'saving' | 'saved' | 'error'
    const { confirm, dialog } = useConfirm();

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
        confirm({ title: 'Delete this step?' }, () => {
            router.delete(
                `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/steps/${step.id}`,
                { preserveScroll: true },
            );
        });
    };

    /* Drag-sort via @dnd-kit (same pattern as DataTable's onReorder rows):
       optimistic local id order, POST the full order, then reload the steps
       prop so numbering and the routing graph pick up the server renumber. */
    const [orderIds, setOrderIds] = useState(steps.map((st) => st.id));
    useEffect(() => { setOrderIds(steps.map((st) => st.id)); }, [processTemplate]); // eslint-disable-line react-hooks/exhaustive-deps
    const orderedSteps = orderIds.map((id) => steps.find((st) => st.id === id)).filter(Boolean);

    const persistOrder = (event) => {
        const source = event?.operation?.source;
        if (event?.canceled || !source) return;
        const from = source.initialIndex;
        const to = source.index;
        if (from === to || from == null || to == null) return;

        const ids = [...orderIds];
        const [moved] = ids.splice(from, 1);
        ids.splice(to, 0, moved);
        setOrderIds(ids);

        setSaveStatus('saving');
        fetch(`/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/steps/reorder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                Accept: 'application/json',
            },
            body: JSON.stringify({ order: ids }),
        })
            .then((res) => {
                if (!res.ok) throw new Error('Server error');
                setSaveStatus('saved');
                router.reload({ only: ['processTemplate'], preserveScroll: true });
            })
            .catch(() => setSaveStatus('error'))
            .finally(() => setTimeout(() => setSaveStatus(null), 2000));
    };

    return (
        <>
            <Head title={`${processTemplate.name} — Process Template`} />

            <div className="w-full">
                {/* The trail rides in the app header's bar (like Alerts): the
                    product-type crumb IS the way back to the template list, so
                    the page needs no separate back link. */}
                <PageTitle>
                    <Breadcrumbs
                        linkAs={Link}
                        items={[
                            { label: __('Dashboard'), href: '/admin/dashboard', icon: 'layout-dashboard' },
                            { label: __('Product Types'), href: '/admin/product-types', icon: 'box' },
                            { label: productType.name, href: `/admin/product-types/${productType.id}/process-templates` },
                            { label: processTemplate.name },
                        ]}
                    />
                </PageTitle>

                {/* Master–detail shell (design 1b): header bar, step rail + graph +
                    selected-step detail, photos/documents band. */}
                <div className="bg-om-panel border-y border-om-line2 overflow-hidden">
                    {/* Header bar */}
                    <div className="flex items-center gap-3 px-5 py-3.5 bg-om-card border-b border-om-line2 flex-wrap">
                        <h1 className="text-lg font-bold text-om-ink">{processTemplate.name}</h1>
                        {processTemplate.is_active ? (
                            <span className="px-2.5 py-0.5 bg-om-running-bg text-om-running rounded-full font-mono text-[10px] uppercase tracking-wide">{__("Active")}</span>
                        ) : (
                            <span className="px-2.5 py-0.5 bg-om-chip text-om-muted rounded-full font-mono text-[10px] uppercase tracking-wide">{__("Inactive")}</span>
                        )}
                        <span className="px-2 py-0.5 border border-om-line2 text-om-muted rounded font-mono text-[10px]">v{processTemplate.version}</span>
                        <span className="font-mono text-[11px] text-om-muted">
                            {productType.name} · {steps.length} {__("steps")}{totalMinutes > 0 ? ` · ~${totalMinutes} min` : ''}
                        </span>
                        <div className="flex-1" />
                        <a
                            href={`/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/edit`}
                            className="text-[12.5px] font-medium text-om-ink border border-om-line2 rounded-om-sm px-3 py-2 hover:bg-om-chip"
                        >
                            {__("Edit")}
                        </a>
                        <a
                            href={`/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/bom`}
                            className="text-[12.5px] font-medium text-om-ink border border-om-line2 rounded-om-sm px-3 py-2 hover:bg-om-chip"
                        >
                            {__("BOM")}
                        </a>
                        <button
                            type="button"
                            onClick={() => setShowAddForm(true)}
                            className="text-[12.5px] font-semibold text-white bg-om-ink rounded-om-sm px-3.5 py-2 hover:opacity-90"
                        >
                            + {__("Add Step")}
                        </button>
                    </div>

                    {/* Add Step Form */}
                    {showAddForm && (
                        <div className="p-5 border-b border-om-line2 bg-om-card">
                            <AddStepForm
                                productType={productType}
                                processTemplate={processTemplate}
                                processSegments={processSegments}
                                workstations={workstations}
                                workstationTypes={workstationTypes}
                                onCancel={() => setShowAddForm(false)}
                            />
                        </div>
                    )}

                    {/* Rail + graph + detail */}
                    <div className="flex" style={{ minHeight: 560 }}>
                        {/* Left rail — compact reorderable step list */}
                        <div className="w-[296px] shrink-0 bg-om-panel border-r border-om-line2 p-3.5 overflow-y-auto" style={{ maxHeight: 720 }}>
                            <div className="flex items-baseline justify-between mx-1 mb-2.5">
                                <span className="font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint">{__("Steps · drag to reorder")}</span>
                                {saveStatus && (
                                    <span className={`font-mono text-[9px] ${saveStatus === 'error' ? 'text-om-blocked' : 'text-om-faint'}`}>
                                        {saveStatus === 'saving' ? __('Saving…') : saveStatus === 'saved' ? __('Saved') : __('Error — reload page')}
                                    </span>
                                )}
                            </div>
                            {orderedSteps.length > 0 ? (
                                <DragDropProvider onDragEnd={persistOrder}>
                                    <div className="flex flex-col gap-[7px]">
                                        {orderedSteps.map((step, idx) => {
                                            const isSelected = selectedStep?.id === step.id;
                                            return (
                                                <RailStep key={step.id} id={step.id} index={idx}>
                                                    {({ ref, handleRef, isDragging }) => (
                                                        <div
                                                            ref={ref}
                                                            role="button"
                                                            tabIndex={0}
                                                            onClick={() => setSelectedStepId(step.id)}
                                                            onKeyDown={(e) => e.key === 'Enter' && setSelectedStepId(step.id)}
                                                            className={`flex items-center gap-2 rounded-om-sm px-2.5 py-2 cursor-pointer select-none border transition-colors ${
                                                                isDragging ? 'opacity-80 shadow-lg' : ''
                                                            } ${
                                                                isSelected
                                                                    ? 'bg-om-accent-bg border-om-accent'
                                                                    : 'bg-om-card border-om-line2 hover:border-om-faintest'
                                                            }`}
                                                        >
                                                            <span ref={handleRef} className="cursor-grab active:cursor-grabbing text-om-faintest hover:text-om-muted text-[11px] leading-none" title={__("Drag to reorder")}>⠿</span>
                                                            <span className={`w-[22px] h-[22px] rounded-full font-mono text-[10px] font-bold flex items-center justify-center shrink-0 ${
                                                                isSelected ? 'bg-om-accent text-white' : 'bg-om-chip text-om-accent'
                                                            }`}
                                                            >
                                                                {idx + 1}
                                                            </span>
                                                            <div className="min-w-0 flex-1">
                                                                <div className="text-[12.5px] font-semibold text-om-ink truncate">{step.name}</div>
                                                                <div className="text-[10px] text-om-muted truncate">{railSubline(step) || __("Any workstation")}</div>
                                                            </div>
                                                            {step.requires_confirmation && (
                                                                <span className="w-[7px] h-[7px] rounded-[2px] bg-om-blocked shrink-0" title={__("Requires read confirmation")} />
                                                            )}
                                                        </div>
                                                    )}
                                                </RailStep>
                                            );
                                        })}
                                    </div>
                                </DragDropProvider>
                            ) : (
                                <p className="text-xs text-om-faint px-1">{__("No production steps yet")}</p>
                            )}
                            <button
                                type="button"
                                onClick={() => setShowAddForm(true)}
                                className="mt-2 w-full text-center border border-dashed border-om-faintest rounded-om-sm py-2.5 text-xs text-om-muted hover:text-om-ink hover:border-om-muted"
                            >
                                + {__("Add Step")}
                            </button>
                        </div>

                        {/* Right: graph strip + selected-step detail */}
                        <div className="flex-1 min-w-0 flex flex-col">
                            <div className="border-b border-om-line2">
                                <RoutingGraph
                                    compact
                                    height={280}
                                    steps={steps}
                                    links={processTemplate.links ?? []}
                                    baseUrl={`/admin/product-types/${productType.id}/process-templates/${processTemplate.id}`}
                                    selectedId={selectedStep?.id ?? null}
                                    onSelectStep={setSelectedStepId}
                                />
                            </div>
                            <div className="flex-1 bg-om-card p-4 overflow-y-auto [&_.drag-handle]:hidden" style={{ maxHeight: 560 }}>
                                {selectedStep ? (
                                    <StepCard
                                        step={selectedStep}
                                        photo={photoByStep[selectedStep.id] ?? null}
                                        photosBaseUrl={photosBaseUrl}
                                        isFirst={selectedStep.id === steps[0]?.id}
                                        isLast={selectedStep.id === steps[steps.length - 1]?.id}
                                        editingId={editingId}
                                        onEditStart={(id) => setEditingId(id)}
                                        onEditCancel={() => setEditingId(null)}
                                        productType={productType}
                                        processTemplate={processTemplate}
                                        processSegments={processSegments}
                                        workstations={workstations}
                                        workstationTypes={workstationTypes}
                                        onMoveUp={handleMoveUp}
                                        onMoveDown={handleMoveDown}
                                        onDelete={handleDelete}
                                    />
                                ) : (
                                    <div className="h-full flex flex-col items-center justify-center text-center py-12">
                                        <p className="text-lg font-medium text-om-muted">{__("No production steps yet")}</p>
                                        <p className="text-sm text-om-muted mt-1 mb-4">{__("Add steps to define the manufacturing process for this product.")}</p>
                                        <button type="button" onClick={() => setShowAddForm(true)} className="inline-block btn-touch btn-primary">
                                            <Icon d="M12 4v16m8-8H4" className="w-5 h-5 inline-block mr-2" />
                                            {__("Add First Step")}
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Photos + documents band */}
                    <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr] items-start bg-om-panel border-t border-om-line2 p-5">
                        <PhotosSection productType={productType} processTemplate={processTemplate} />
                        <EngineeringDocuments entityType="process_template" entityId={processTemplate.id} variant="band" />
                    </div>
                </div>

            </div>
            {dialog}
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
    const { confirm, dialog } = useConfirm();

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
        confirm({ title: __('Delete this photo?') }, () => {
            router.delete(`${baseUrl}/${photo.id}`, { preserveScroll: true });
        });
    };

    return (
        <div>
            <div className="flex items-baseline gap-2 mb-2.5">
                <h2 className="text-sm font-bold text-om-ink">{__("General Reference Photos")}</h2>
                <span className="font-mono text-[10px] text-om-faint">{photos.length}/20</span>
                <button type="button" onClick={() => fileInputRef.current?.click()} className="ml-auto text-[11.5px] font-semibold text-om-accent hover:underline">
                    + {__('Upload')}
                </button>
            </div>

            <input
                aria-label={__("Photo")}
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(e) => form.setData('photo', e.target.files[0] ?? null)}
                className="hidden"
            />

            {/* A picked file gets a caption row before it goes up. */}
            {form.data.photo && (
                <form onSubmit={submit} className="mb-2.5 flex flex-wrap items-center gap-2 bg-om-card border border-om-line2 rounded-om-sm px-2.5 py-2">
                    <span className="text-xs text-om-muted truncate max-w-[180px]">{form.data.photo.name}</span>
                    <div className="flex-1 min-w-[160px]">
                        <TextField
                            value={form.data.caption}
                            onChange={(v) => form.setData('caption', v)}
                            placeholder={__("Optional description")}
                            aria-label={__("Caption")}
                        />
                    </div>
                    <Button type="submit" size="sm" loading={form.processing}>{form.processing ? __('Uploading…') : __('Upload')}</Button>
                    <Button variant="secondary" size="sm" onClick={() => { form.reset(); if (fileInputRef.current) fileInputRef.current.value = ''; }}>{__('Cancel')}</Button>
                    {form.errors.photo && <p className="w-full text-xs text-om-blocked">{form.errors.photo}</p>}
                    {form.errors.caption && <p className="w-full text-xs text-om-blocked">{form.errors.caption}</p>}
                </form>
            )}

            {/* Photo strip */}
            <div className="flex flex-wrap gap-2.5">
                    {photos.map((photo) => (
                        <div key={photo.id} className="w-[150px] bg-om-card border border-om-line2 rounded-om-sm p-1.5 group relative">
                            <Tooltip label={photo.original_name}>
                                <button
                                    type="button"
                                    onClick={() => setLightbox(photo)}
                                    className="block w-full"
                                    aria-label={photo.original_name}
                                >
                                    <img
                                        src={photo.url}
                                        alt={photo.caption || photo.original_name}
                                        loading="lazy"
                                        className="w-full h-[74px] object-cover rounded bg-om-chip"
                                    />
                                </button>
                            </Tooltip>
                            <div className="mt-1 text-[10px] text-om-muted truncate" title={photo.caption || ''}>
                                {photo.caption || <span className="text-om-faint">{__("No caption")}</span>}
                            </div>
                            <div className="font-mono text-[8.5px] text-om-faintest">
                                {photo.width}×{photo.height} · {photo.file_size}
                            </div>
                            <Tooltip label={__("Delete photo")}>
                                <button
                                    type="button"
                                    onClick={() => handleDelete(photo)}
                                    className="absolute top-3 right-3 bg-om-card/90 text-om-blocked rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow"
                                    aria-label={__("Delete photo")}
                                >
                                    <Icon d="M6 18L18 6M6 6l12 12" className="w-4 h-4" />
                                </button>
                            </Tooltip>
                        </div>
                    ))}
                    <button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        className="w-[150px] min-h-[110px] border border-dashed border-om-faintest rounded-om-sm flex items-center justify-center text-center text-[11px] text-om-muted hover:text-om-ink hover:border-om-muted"
                    >
                        + {__('JPEG/PNG/WebP')}<br />{__('max 10 MB')}
                    </button>
            </div>

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
                    <Tooltip label="Close">
                        <button
                            type="button"
                            onClick={() => setLightbox(null)}
                            className="absolute top-5 right-5 text-white/80 hover:text-white"
                            aria-label="Close"
                        >
                            <Icon d="M6 18L18 6M6 6l12 12" className="w-8 h-8" />
                        </button>
                    </Tooltip>
                </div>
            )}
            {dialog}
        </div>
    );
}

ProcessTemplatesShow.layout = (page) => <AppLayout>{page}</AppLayout>;

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
