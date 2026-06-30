import { Link, useForm } from '@inertiajs/react';
import { Checkbox, DatePicker, Dropdown } from '@openmes/ui';
import { __ } from '../lib/i18n';

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

export default function DefinitionForm({ action, method = 'post', initial, entities = [], types = [], submitLabel }) {
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
        <form onSubmit={submit} className="bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5">
            <SelectField label={__('Entity')} required value={data.entity_type} error={errors.entity_type}
                placeholder={__('— Select entity —')} options={entities} onChange={(v) => setData('entity_type', v)} />

            <TextField label={__('Key')} required value={data.key} error={errors.key}
                help={__('Machine name: lowercase letters, numbers, underscores (e.g. shelf_life_days).')}
                onChange={(v) => setData('key', v)} />

            <TextField label={__('Label')} required value={data.label} error={errors.label} onChange={(v) => setData('label', v)} />

            <SelectField label={__('Type')} required value={data.type} error={errors.type}
                placeholder={__('— Select type —')} options={types} onChange={(v) => setData('type', v)} />

            {isOptioned && (
                <OptionsEditor options={options} setOption={setOption} addOption={addOption}
                    removeOption={removeOption} error={errors['config.options']} />
            )}

            {isRange && (
                <div className="grid grid-cols-2 gap-4">
                    <TextField label={__('Min')} type="number" value={data.config?.min ?? ''} error={errors['config.min']}
                        onChange={(v) => setConfig({ min: v })} />
                    <TextField label={__('Max')} type="number" value={data.config?.max ?? ''} error={errors['config.max']}
                        onChange={(v) => setConfig({ max: v })} />
                </div>
            )}

            <div className="flex items-end gap-6">
                <CheckboxField label={__('Required')} checked={data.required} onChange={(v) => setData('required', v)} />
                <CheckboxField label={__('Active')} checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                <div className="w-28">
                    <TextField label={__('Position')} type="number" value={data.position ?? 0} error={errors.position}
                        onChange={(v) => setData('position', v)} />
                </div>
            </div>

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing}
                    className="bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover disabled:opacity-50">
                    {processing ? __('Saving…') : (submitLabel ?? __('Save'))}
                </button>
                <Link href="/admin/custom-fields" className="text-om-muted hover:text-om-ink text-sm">{__('Cancel')}</Link>
            </div>
        </form>
    );
}

function TextField({ label, value, error, onChange, required, help, type = 'text' }) {
    return (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">
                {label} {required && <span className="text-om-blocked">*</span>}
            </label>
            {type === 'date' ? (
                <DatePicker className="w-full" value={value || null} onChange={(iso) => onChange(iso ?? '')} />
            ) : (
                <input type={type} value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="form-input w-full" />
            )}
            {help && <p className="text-sm text-om-muted mt-1">{help}</p>}
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}

function SelectField({ label, value, error, onChange, required, placeholder, options }) {
    return (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">
                {label} {required && <span className="text-om-blocked">*</span>}
            </label>
            <Dropdown
                className="w-full"
                options={options.map((o) => ({ value: String(o.value), label: o.label }))}
                value={value == null ? '' : String(value)}
                onChange={(v) => onChange(v)}
                placeholder={placeholder}
            />
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}

function CheckboxField({ label, checked, onChange }) {
    return (
        <div className="pb-2">
            <Checkbox checked={!!checked} onChange={(next) => onChange(next)} label={label} />
        </div>
    );
}

function OptionsEditor({ options, setOption, addOption, removeOption, error }) {
    return (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">
                {__('Options')} <span className="text-om-blocked">*</span>
            </label>
            <div className="space-y-2">
                {options.length === 0 && <p className="text-sm text-om-faint">{__('No options yet.')}</p>}
                {options.map((o, i) => (
                    <div key={i} className="flex items-center gap-2">
                        <input className="form-input flex-1" placeholder={__('value')} value={o.value ?? ''}
                            onChange={(e) => setOption(i, { value: e.target.value })} />
                        <input className="form-input flex-1" placeholder={__('label')} value={o.label ?? ''}
                            onChange={(e) => setOption(i, { label: e.target.value })} />
                        <button type="button" onClick={() => removeOption(i)}
                            className="text-om-blocked hover:text-om-blocked text-sm px-2" title={__('Remove')}>✕</button>
                    </div>
                ))}
            </div>
            <button type="button" onClick={addOption} className="mt-2 text-sm text-om-accent hover:text-om-accent">
                {__('+ Add option')}
            </button>
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
