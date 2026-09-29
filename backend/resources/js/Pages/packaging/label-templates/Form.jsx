import { useEffect, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, TextField } from '@openmes/ui';
import LabelPreviewModal from '../../../components/LabelPreviewModal';
import { __ } from '../../../lib/i18n';

/**
 * Blank/loaded form values. A synced row carries `fields_config` as a JSON
 * string (no Eloquent casts behind the collection snapshot), the edit deep link
 * as an object — both land here.
 */
export function labelTemplateInitial(r, defaultFieldsByType = {}) {
    const type = r?.type ?? 'work_order';
    let fields = r?.fields_config ?? defaultFieldsByType[type] ?? {};
    if (typeof fields === 'string') {
        try { fields = JSON.parse(fields) || {}; } catch { fields = {}; }
    }
    return {
        name: r?.name ?? '',
        type,
        size: r?.size ?? '100x50',
        barcode_format: r?.barcode_format ?? 'code128',
        fields,
        is_default: !!r?.is_default,
        is_active: r ? !!r.is_active : true,
    };
}

/**
 * Label template form: the scalar selects (type/size/barcode) and a checkbox
 * grid of AVAILABLE_FIELDS toggling what prints — submitted as `fields`
 * { key: bool } (the server reads fields.{key}). The option maps come from the
 * model's constants in English; they are translated here, at render.
 */
export default function LabelTemplateForm({ action, method, initial, submitLabel, onSuccess, onCancel }) {
    const { types = {}, sizes = {}, barcodeFormats = {}, availableFields = {}, defaultFieldsByType = {}, fieldsByType = {} } = usePage().props;
    const form = useForm(initial);
    const { data, setData, errors, processing } = form;
    const creating = method === 'post';

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action, { preserveScroll: true, ...(onSuccess ? { onSuccess } : {}) });
    };

    // The label as the form now describes it, on sample data - before saving.
    const [previewOpen, setPreviewOpen] = useState(false);
    const previewUrl = () => {
        const q = new URLSearchParams({ name: data.name || '', type: data.type, size: data.size, barcode_format: data.barcode_format });
        Object.entries(data.fields ?? {}).forEach(([k, v]) => q.append(`fields[${k}]`, v ? '1' : '0'));
        return `/packaging/label-templates/preview?${q.toString()}`;
    };
    // The inline preview follows the fields as they change, a beat behind the typing.
    const [liveUrl, setLiveUrl] = useState(null);
    const previewKey = JSON.stringify([data.type, data.size, data.barcode_format, data.fields]);
    useEffect(() => {
        const t = setTimeout(() => setLiveUrl(previewUrl()), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [previewKey]);

    // A new template of another type starts from that type's usual fields;
    // an existing one keeps what was chosen for it.
    const changeType = (type) => {
        setData((d) => ({ ...d, type, ...(creating && defaultFieldsByType[type] ? { fields: defaultFieldsByType[type] } : {}) }));
    };

    const sel = (label, name, map, error, onChange) => (
        <div>
            <div className="block text-sm font-medium text-om-muted mb-1">{label} <span className="text-om-blocked">*</span></div>
            <Dropdown
                aria-label={label}
                options={Object.entries(map).map(([v, l]) => ({ value: String(v), label: __(l) }))}
                value={data[name] == null ? '' : String(data[name])}
                onChange={onChange ?? ((v) => setData(name, v))}
                className="w-full"
            />
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );

    return (
        <form onSubmit={submit} className="space-y-5">
            <TextField
                label={__('Name')}
                required
                autoFocus
                value={data.name}
                onChange={(v) => setData('name', v)}
                error={errors.name}
            />

            {sel(__('Type'), 'type', types, errors.type, changeType)}
            {sel(__('Label Size'), 'size', sizes, errors.size)}
            {sel(__('Barcode Format'), 'barcode_format', barcodeFormats, errors.barcode_format)}

            <div>
                <div className="block text-sm font-medium text-om-muted mb-2">{__('Fields on label')}</div>
                <div className="grid grid-cols-2 gap-2 border border-om-line2 rounded-om-sm p-3">
                    {Object.entries(availableFields).filter(([key]) => !fieldsByType[data.type] || fieldsByType[data.type].includes(key)).map(([key, label]) => (
                        <Checkbox
                            key={key}
                            checked={!!data.fields?.[key]}
                            onChange={(next) => setData('fields', { ...data.fields, [key]: next })}
                            label={__(label)}
                        />
                    ))}
                </div>
            </div>

            <div>
                <div className="block text-sm font-medium text-om-muted mb-2">{__('Preview')}</div>
                <div className="overflow-hidden rounded-om-sm border border-om-line2 bg-[#2b2a27]">
                    {liveUrl ? (
                        <iframe
                            key={liveUrl}
                            title={__('Preview')}
                            src={`${liveUrl}#toolbar=0&navpanes=0&scrollbar=0&view=Fit`}
                            className="block h-[260px] w-full"
                        />
                    ) : <div className="h-[260px]" />}
                </div>
                <p className="mt-1 text-xs text-om-muted">{__('Sample data; the label updates as you change the fields above.')}</p>
            </div>

            <div className="flex flex-col gap-2">
                <Checkbox
                    checked={!!data.is_default}
                    onChange={(next) => setData('is_default', next)}
                    label={__('Default template for this type')}
                />
                <Checkbox
                    checked={!!data.is_active}
                    onChange={(next) => setData('is_active', next)}
                    label={__('Active')}
                />
            </div>

            <div className="flex items-center gap-3 pt-2">
                <Button type="submit" variant="primary" loading={processing} disabled={processing}>
                    {submitLabel}
                </Button>
                <Button type="button" variant="outline" onClick={() => setPreviewOpen(true)}>{__('Preview')}</Button>
                <Button type="button" variant="ghost" onClick={onCancel}>{__('Cancel')}</Button>
            </div>
            <LabelPreviewModal
                open={previewOpen}
                onClose={() => setPreviewOpen(false)}
                title={__('Preview')}
                subtitle={`${__(types[data.type] ?? data.type)} · ${data.size} mm · ${String(data.barcode_format ?? '').toUpperCase()}`}
                pdfUrl={previewOpen ? previewUrl() : null}
            />
        </form>
    );
}
