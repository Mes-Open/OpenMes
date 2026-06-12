import { __ } from '../lib/i18n';

/**
 * Renders admin-defined custom fields inside a form. Scalar values bind to the
 * form's `custom_fields` object; File/Image uploads bind to `custom_field_files`
 * (new File per key) and `custom_field_files_remove` (keys to clear), which the
 * server merges into custom_fields. Laravel returns errors keyed
 * `custom_fields.<key>` or `custom_field_files.<key>`.
 */

// Geist White input + label idiom (light-only v1 — no dark: variants).
const LABEL_CLASS = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT_CLASS =
    'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

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
        <fieldset className="space-y-5 border-t border-om-line pt-5">
            <legend className="font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint">
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
                <label className={LABEL_CLASS}>
                    {label} {required && <span className="text-om-accent">*</span>}
                </label>
                {showExisting && (
                    <div className="flex items-center gap-3 mb-2">
                        {type === 'image' ? (
                            <img src={url} alt={meta.name} className="h-20 w-20 rounded-om-sm border border-om-line object-cover" />
                        ) : (
                            <a href={url} target="_blank" rel="noreferrer" className="text-[13px] text-om-accent hover:underline">
                                {meta.name}
                            </a>
                        )}
                        <button type="button" onClick={onToggleRemove} className="text-[11.5px] text-om-blocked hover:underline">
                            {__('Remove')}
                        </button>
                    </div>
                )}
                {removed && <p className="text-[11.5px] text-om-muted mb-1">{__('Will be removed on save.')}</p>}
                <input
                    type="file"
                    accept={type === 'image' ? 'image/*' : undefined}
                    onChange={(e) => onFileChange(e.target.files?.[0] ?? null)}
                    className="block w-full text-[13px] text-om-muted file:mr-3 file:rounded-om-sm file:border-0 file:bg-om-chip file:px-3 file:py-1.5 file:text-[13px] file:font-semibold file:text-om-ink hover:file:bg-om-line2"
                />
                {file && <p className="mt-1 text-[12px] text-om-muted">{__('Selected')}: {file.name}</p>}
                {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
            </div>
        );
    }

    if (type === 'boolean') {
        return (
            <div>
                <label className="flex items-center gap-2.5 text-[13px] text-om-ink">
                    {/* 18px accent checkbox idiom — native input kept for identical behavior */}
                    <input
                        type="checkbox"
                        checked={!!value}
                        onChange={(e) => onChange(e.target.checked)}
                        className="size-[18px] accent-om-accent"
                    />
                    {label}
                </label>
                {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
            </div>
        );
    }

    let control;
    if (type === 'textarea') {
        control = (
            <textarea value={value ?? ''} onChange={(e) => onChange(e.target.value)} rows={3} className={INPUT_CLASS} />
        );
    } else if (type === 'select') {
        // Native <select> kept for now — same input styling.
        control = (
            <select value={value ?? ''} onChange={(e) => onChange(e.target.value)} className={INPUT_CLASS}>
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
                    <label key={o.value} className="flex items-center gap-2.5 text-[13px] text-om-ink">
                        <input
                            type="checkbox"
                            checked={selected.includes(o.value)}
                            onChange={() => toggle(o.value)}
                            className="size-[18px] accent-om-accent"
                        />
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
                className={INPUT_CLASS}
            />
        );
    }

    return (
        <div>
            <label className={LABEL_CLASS}>
                {label} {required && <span className="text-om-accent">*</span>}
            </label>
            {control}
            {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
        </div>
    );
}
