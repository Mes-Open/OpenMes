import { Link, useForm } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, Icon } from '@openmes/ui';
import AppDatePicker from './AppDatePicker';
import Tooltip from './Tooltip';
import { __ } from '../lib/i18n';
import { numberInputStep } from '../lib/customFieldForm';

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

// The ResourceForm look (Geist White): mono caps labels, chip-tone inputs.
const LABEL_CLASS = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT_CLASS =
    'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';
const HELP_CLASS = 'mt-1.5 text-[11.5px] text-om-muted';
const ERROR_CLASS = 'mt-1 text-[11.5px] text-om-blocked';
const CANCEL_CLASS =
    'inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors';
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
        <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-6 max-w-2xl space-y-5">
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
                    <TextField label={__('Min')} type="number" step={numberInputStep(data.type)} value={data.config?.min ?? ''} error={errors['config.min']}
                        onChange={(v) => setConfig({ min: v })} />
                    <TextField label={__('Max')} type="number" step={numberInputStep(data.type)} value={data.config?.max ?? ''} error={errors['config.max']}
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
                <Button type="submit" variant="primary" loading={processing}>
                    {processing ? __('Saving…') : (submitLabel ?? __('Save'))}
                </Button>
                <Link href="/admin/custom-fields" className={CANCEL_CLASS}>{__('Cancel')}</Link>
            </div>
        </form>
    );
}

function TextField({ label, value, error, onChange, required, help, type = 'text', step }) {
    return (
        <div>
            <div className={LABEL_CLASS}>
                {label} {required && <span className="text-om-accent">*</span>}
            </div>
            {type === 'date' ? (
                <AppDatePicker className="w-full" value={value || null} onChange={(iso) => onChange(iso ?? '')} />
            ) : (
                <input type={type} step={step} value={value ?? ''} onChange={(e) => onChange(e.target.value)} className={INPUT_CLASS} />
            )}
            {help && <p className={HELP_CLASS}>{help}</p>}
            {error && <p className={ERROR_CLASS}>{error}</p>}
        </div>
    );
}

function SelectField({ label, value, error, onChange, required, placeholder, options }) {
    return (
        <div>
            <div className={LABEL_CLASS}>
                {label} {required && <span className="text-om-accent">*</span>}
            </div>
            <Dropdown
                aria-label={label}
                className="w-full"
                options={options.map((o) => ({ value: String(o.value), label: o.label }))}
                value={value == null ? '' : String(value)}
                onChange={(v) => onChange(v)}
                placeholder={placeholder}
            />
            {error && <p className={ERROR_CLASS}>{error}</p>}
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
            <div className={LABEL_CLASS}>
                {__('Options')} <span className="text-om-accent">*</span>
            </div>
            <div className="space-y-2">
                {options.length === 0 && <p className="text-[12.5px] text-om-faint">{__('No options yet.')}</p>}
                {options.map((o, i) => (
                    <div key={i} className="flex items-center gap-2">
                        <input className={`${INPUT_CLASS} flex-1`} placeholder={__('value')} value={o.value ?? ''}
                            onChange={(e) => setOption(i, { value: e.target.value })} />
                        <input className={`${INPUT_CLASS} flex-1`} placeholder={__('label')} value={o.label ?? ''}
                            onChange={(e) => setOption(i, { label: e.target.value })} />
                        <Tooltip label={__('Remove')}>
                            <button type="button" onClick={() => removeOption(i)}
                                className="cursor-pointer p-1.5 rounded-om-sm text-om-blocked transition-colors hover:bg-om-blocked-bg" aria-label={__('Remove')}>
                                <Icon name="trash-2" size={18} />
                            </button>
                        </Tooltip>
                    </div>
                ))}
            </div>
            <Button type="button" variant="secondary" size="sm" className="mt-2" leftIcon={<Icon name="plus" size={13} />} onClick={addOption}>
                {__('Add option')}
            </Button>
            {error && <p className={ERROR_CLASS}>{error}</p>}
        </div>
    );
}
