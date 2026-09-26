import { Checkbox, Dropdown } from '@openmes/ui';
import { __ } from '../lib/i18n';
import { moduleFieldsFor, moduleFieldValue } from '../lib/moduleFields';

/**
 * Renders the form fields an installed module contributed.
 *
 * The server side is App\Extension\HookRegistry: the controller resolves the
 * hook point its form offers and hands the contributions over as the `hooks`
 * prop. On an install with no module that prop is `{}` and this renders null,
 * which is why a core form can offer the extension point at no cost.
 *
 * A module distributed as a ZIP cannot ship working JSX — the page globs are
 * expanded by Vite when core is built — so it describes its field as data and
 * core draws it. That is the whole reason this component exists rather than the
 * module supplying its own control.
 *
 * Values bind FLAT (`data.module_example_code`), not nested: the module's
 * validation rule is a flat key and Laravel returns its error under the same
 * one. CustomFields nests only because it has a JSON column on the other side.
 */

// Same idiom as CustomFields.jsx (light-only v1).
const LABEL_CLASS = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT_CLASS =
    'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

export default function ModuleFields({ hooks, name, values = {}, onChange, errors = {}, legend }) {
    const fields = moduleFieldsFor(hooks, name);

    if (! fields.length) return null;

    return (
        <fieldset className="space-y-5 border-t border-om-line pt-5">
            <legend className="font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint">
                {legend ?? __('Additional fields')}
            </legend>
            {fields.map((field) => (
                <ModuleFieldInput
                    key={field.name}
                    field={field}
                    value={moduleFieldValue(field, values)}
                    error={errors[field.name]}
                    onChange={(v) => onChange(field.name, v)}
                />
            ))}
        </fieldset>
    );
}

function ModuleFieldInput({ field, value, error, onChange }) {
    const { type = 'text', label, placeholder, help, required, options = [] } = field;

    if (type === 'checkbox') {
        return (
            <div>
                <Checkbox checked={!! value} onChange={(next) => onChange(next)} label={label} />
                {help && <p className="mt-1 text-[11.5px] text-om-muted">{help}</p>}
                {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
            </div>
        );
    }

    const control =
        type === 'select' ? (
            <Dropdown
                className="w-full"
                options={options.map((o) => ({ value: String(o.value), label: o.label }))}
                value={value == null ? '' : String(value)}
                onChange={(v) => onChange(v)}
                placeholder={placeholder ?? __('— Select —')}
            />
        ) : (
            <input
                type="text"
                value={value ?? ''}
                placeholder={placeholder}
                onChange={(e) => onChange(e.target.value)}
                className={INPUT_CLASS}
            />
        );

    return (
        <div>
            <div className={LABEL_CLASS}>
                {label} {required && <span className="text-om-accent">*</span>}
            </div>
            {control}
            {help && <p className="mt-1 text-[11.5px] text-om-muted">{help}</p>}
            {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
        </div>
    );
}
