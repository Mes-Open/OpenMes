import { Link } from '@inertiajs/react';
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
            <label className="block text-sm font-medium text-gray-700 mb-1">{label} <span className="text-red-500">*</span></label>
            <select value={data[name] ?? ''} onChange={(e) => setData(name, e.target.value)} className="form-input w-full">
                {Object.entries(map).map(([v, l]) => <option key={v} value={v}>{__(l)}</option>)}
            </select>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );

    return (
        <form onSubmit={onSubmit} className="bg-white rounded-lg shadow-sm p-6 max-w-2xl space-y-5">
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">{__('Name')} <span className="text-red-500">*</span></label>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" autoFocus />
                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
            </div>

            {sel(__('Type'), 'type', types, errors.type)}
            {sel(__('Label Size'), 'size', sizes, errors.size)}
            {sel(__('Barcode Format'), 'barcode_format', barcodeFormats, errors.barcode_format)}

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">{__('Fields on label')}</label>
                <div className="grid grid-cols-2 gap-2 border border-gray-200 rounded-lg p-3">
                    {Object.entries(availableFields).map(([key, label]) => (
                        <label key={key} className="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                checked={!!data.fields?.[key]}
                                onChange={(e) => setData('fields', { ...data.fields, [key]: e.target.checked })}
                            />
                            {__(label)}
                        </label>
                    ))}
                </div>
            </div>

            <div className="flex flex-col gap-2">
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={!!data.is_default} onChange={(e) => setData('is_default', e.target.checked)} />
                    {__('Default template for this type')}
                </label>
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={!!data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                    {__('Active')}
                </label>
            </div>

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing} className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link href="/packaging/label-templates" className="text-gray-500 hover:text-gray-800 text-sm">Cancel</Link>
            </div>
        </form>
    );
}
