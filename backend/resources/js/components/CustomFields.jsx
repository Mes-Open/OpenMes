import { __ } from '../lib/i18n';

/**
 * Renders admin-defined custom fields inside a form. Scalar values bind to the
 * form's `custom_fields` object; File/Image uploads bind to `custom_field_files`
 * (new File per key) and `custom_field_files_remove` (keys to clear), which the
 * server merges into custom_fields. Laravel returns errors keyed
 * `custom_fields.<key>` or `custom_field_files.<key>`.
 */
export default function CustomFields({
    definitions = [],
    values = {},
    onChange,
    files = {},
    onFileChange,
    removed = [],
    onRemovedChange,
    errors = {},
}) {
    if (!definitions.length) return null;

    const set = (key, val) => onChange({ ...values, [key]: val });
    const toggleRemove = (key) =>
        onRemovedChange(removed.includes(key) ? removed.filter((k) => k !== key) : [...removed, key]);

    return (
        <fieldset className="space-y-5 border-t border-gray-200 pt-5">
            <legend className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                {__('Custom fields')}
            </legend>
            {definitions.map((def) => (
                <CustomFieldInput
                    key={def.key}
                    def={def}
                    value={values?.[def.key]}
                    error={errors[`custom_fields.${def.key}`] ?? errors[`custom_field_files.${def.key}`]}
                    onChange={(v) => set(def.key, v)}
                    file={files?.[def.key] ?? null}
                    onFileChange={(f) => onFileChange(def.key, f)}
                    removed={removed.includes(def.key)}
                    onToggleRemove={() => toggleRemove(def.key)}
                />
            ))}
        </fieldset>
    );
}

function fileUrl(meta) {
    return meta?.path ? `/admin/custom-field-files/${meta.path.split('/').pop()}` : null;
}

function CustomFieldInput({ def, value, error, onChange, file, onFileChange, removed, onToggleRemove }) {
    const { label, type, required, config = {} } = def;
    const options = config.options ?? [];

    if (type === 'file' || type === 'image') {
        const meta = value && typeof value === 'object' ? value : null;
        const showExisting = meta && !removed && !file;
        const url = fileUrl(meta);
        return (
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    {label} {required && <span className="text-red-500">*</span>}
                </label>
                {showExisting && (
                    <div className="flex items-center gap-3 mb-2">
                        {type === 'image' ? (
                            <img src={url} alt={meta.name} className="h-20 w-20 rounded border border-gray-200 object-cover" />
                        ) : (
                            <a href={url} target="_blank" rel="noreferrer" className="text-sm text-blue-600 hover:underline">
                                {meta.name}
                            </a>
                        )}
                        <button type="button" onClick={onToggleRemove} className="text-xs text-red-600 hover:text-red-800">
                            {__('Remove')}
                        </button>
                    </div>
                )}
                {removed && <p className="text-xs text-gray-500 mb-1">{__('Will be removed on save.')}</p>}
                <input
                    type="file"
                    accept={type === 'image' ? 'image/*' : undefined}
                    onChange={(e) => onFileChange(e.target.files?.[0] ?? null)}
                    className="block w-full text-sm text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm hover:file:bg-gray-200"
                />
                {file && <p className="mt-1 text-xs text-gray-600">{__('Selected')}: {file.name}</p>}
                {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
            </div>
        );
    }

    if (type === 'boolean') {
        return (
            <div>
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={!!value} onChange={(e) => onChange(e.target.checked)} />
                    {label}
                </label>
                {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
            </div>
        );
    }

    let control;
    if (type === 'textarea') {
        control = (
            <textarea value={value ?? ''} onChange={(e) => onChange(e.target.value)} rows={3} className="form-input w-full" />
        );
    } else if (type === 'select') {
        control = (
            <select value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="form-input w-full">
                <option value="">{__('— Select —')}</option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </select>
        );
    } else if (type === 'multiselect') {
        const selected = Array.isArray(value) ? value : [];
        const toggle = (val) =>
            onChange(selected.includes(val) ? selected.filter((v) => v !== val) : [...selected, val]);
        control = (
            <div className="space-y-1">
                {options.map((o) => (
                    <label key={o.value} className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={selected.includes(o.value)} onChange={() => toggle(o.value)} />
                        {o.label}
                    </label>
                ))}
            </div>
        );
    } else {
        const inputType = { number: 'number', integer: 'number', date: 'date', datetime: 'datetime-local' }[type] ?? 'text';
        control = (
            <input
                type={inputType}
                step={type === 'integer' ? '1' : undefined}
                min={config.min}
                max={config.max}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className="form-input w-full"
            />
        );
    }

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            {control}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
