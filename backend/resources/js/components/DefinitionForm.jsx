import { Link, useForm } from '@inertiajs/react';

/**
 * Create/edit form for a custom-field definition. Unlike ResourceForm this is
 * a bespoke form because the `config` editor is conditional on the chosen type:
 * select/multiselect get an options list; number/integer get min/max.
 *
 * Props:
 *   action, method, initial — Inertia useForm wiring
 *   entities — [{ value, label }] entity-type options (from the registry)
 *   types    — [{ value, label }] field-type options (from CustomFieldType)
 *   submitLabel
 */
const OPTION_TYPES = ['select', 'multiselect'];
const RANGE_TYPES = ['number', 'integer'];

export default function DefinitionForm({ action, method = 'post', initial, entities = [], types = [], submitLabel = 'Save' }) {
    const form = useForm(initial);
    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action);
    };

    const setConfig = (patch) => setData('config', { ...(data.config ?? {}), ...patch });
    const options = data.config?.options ?? [];
    const setOption = (i, patch) => setConfig({ options: options.map((o, idx) => (idx === i ? { ...o, ...patch } : o)) });
    const addOption = () => setConfig({ options: [...options, { value: '', label: '' }] });
    const removeOption = (i) => setConfig({ options: options.filter((_, idx) => idx !== i) });

    const isOptioned = OPTION_TYPES.includes(data.type);
    const isRange = RANGE_TYPES.includes(data.type);

    return (
        <form onSubmit={submit} className="bg-white rounded-lg shadow-sm p-6 max-w-2xl space-y-5">
            <SelectField label="Entity" required value={data.entity_type} error={errors.entity_type}
                placeholder="— Select entity —" options={entities} onChange={(v) => setData('entity_type', v)} />

            <TextField label="Key" required value={data.key} error={errors.key}
                help="Machine name: lowercase letters, numbers, underscores (e.g. shelf_life_days)."
                onChange={(v) => setData('key', v)} />

            <TextField label="Label" required value={data.label} error={errors.label} onChange={(v) => setData('label', v)} />

            <SelectField label="Type" required value={data.type} error={errors.type}
                placeholder="— Select type —" options={types} onChange={(v) => setData('type', v)} />

            {isOptioned && (
                <OptionsEditor options={options} setOption={setOption} addOption={addOption}
                    removeOption={removeOption} error={errors['config.options']} />
            )}

            {isRange && (
                <div className="grid grid-cols-2 gap-4">
                    <TextField label="Min" type="number" value={data.config?.min ?? ''} error={errors['config.min']}
                        onChange={(v) => setConfig({ min: v })} />
                    <TextField label="Max" type="number" value={data.config?.max ?? ''} error={errors['config.max']}
                        onChange={(v) => setConfig({ max: v })} />
                </div>
            )}

            <div className="flex items-end gap-6">
                <CheckboxField label="Required" checked={data.required} onChange={(v) => setData('required', v)} />
                <CheckboxField label="Active" checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                <div className="w-28">
                    <TextField label="Position" type="number" value={data.position ?? 0} error={errors.position}
                        onChange={(v) => setData('position', v)} />
                </div>
            </div>

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing}
                    className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link href="/admin/custom-fields" className="text-gray-500 hover:text-gray-800 text-sm">Cancel</Link>
            </div>
        </form>
    );
}

function TextField({ label, value, error, onChange, required, help, type = 'text' }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            <input type={type} value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="form-input w-full" />
            {help && <p className="text-sm text-gray-500 mt-1">{help}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function SelectField({ label, value, error, onChange, required, placeholder, options }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            <select value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="form-input w-full">
                {placeholder && <option value="">{placeholder}</option>}
                {options.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </select>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function CheckboxField({ label, checked, onChange }) {
    return (
        <label className="flex items-center gap-2 text-sm text-gray-700 pb-2">
            <input type="checkbox" checked={!!checked} onChange={(e) => onChange(e.target.checked)} />
            {label}
        </label>
    );
}

function OptionsEditor({ options, setOption, addOption, removeOption, error }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                Options <span className="text-red-500">*</span>
            </label>
            <div className="space-y-2">
                {options.length === 0 && <p className="text-sm text-gray-400">No options yet.</p>}
                {options.map((o, i) => (
                    <div key={i} className="flex items-center gap-2">
                        <input className="form-input flex-1" placeholder="value" value={o.value ?? ''}
                            onChange={(e) => setOption(i, { value: e.target.value })} />
                        <input className="form-input flex-1" placeholder="label" value={o.label ?? ''}
                            onChange={(e) => setOption(i, { label: e.target.value })} />
                        <button type="button" onClick={() => removeOption(i)}
                            className="text-red-600 hover:text-red-800 text-sm px-2" title="Remove">✕</button>
                    </div>
                ))}
            </div>
            <button type="button" onClick={addOption} className="mt-2 text-sm text-blue-600 hover:text-blue-800">
                + Add option
            </button>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
