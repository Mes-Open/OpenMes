import { Link } from '@inertiajs/react';
import { Button, Checkbox, Dropdown } from '@openmes/ui';
import { __ } from '../../../lib/i18n';

/**
 * Label template form. Beyond the scalar selects (type/size/barcode), it has a
 * fixed checkbox grid of AVAILABLE_FIELDS toggling which fields print on the
 * label — submitted as `fields` { key: bool } (server reads fields.{key}).
 * Non-optimistic write-through via the passed-in Inertia useForm.
 */
export default function LabelTemplateForm({ form, types, sizes, barcodeFormats, availableFields, submitLabel, onSubmit }) {
    const { data, setData, errors, processing } = form;

    const sel = (label, name, map, error) => (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">{label} <span className="text-om-blocked">*</span></label>
            <Dropdown
                options={Object.entries(map).map(([v, l]) => ({ value: String(v), label: l }))}
                value={data[name] == null ? '' : String(data[name])}
                onChange={(v) => setData(name, v)}
                className="w-full"
            />
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );

    return (
        <form onSubmit={onSubmit} className="bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5">
            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">{__('Name')} <span className="text-om-blocked">*</span></label>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" autoFocus />
                {errors.name && <p className="mt-1 text-xs text-om-blocked">{errors.name}</p>}
            </div>

            {sel(__('Type'), 'type', types, errors.type)}
            {sel(__('Label Size'), 'size', sizes, errors.size)}
            {sel(__('Barcode Format'), 'barcode_format', barcodeFormats, errors.barcode_format)}

            <div>
                <label className="block text-sm font-medium text-om-muted mb-2">{__('Fields on label')}</label>
                <div className="grid grid-cols-2 gap-2 border border-om-line2 rounded-om-sm p-3">
                    {Object.entries(availableFields).map(([key, label]) => (
                        <Checkbox
                            key={key}
                            checked={!!data.fields?.[key]}
                            onChange={(next) => setData('fields', { ...data.fields, [key]: next })}
                            label={label}
                        />
                    ))}
                </div>
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
                    {processing ? 'Saving…' : submitLabel}
                </Button>
                <Link href="/packaging/label-templates" className="text-om-muted hover:text-om-ink text-sm">Cancel</Link>
            </div>
        </form>
    );
}
