import { Dropdown, Switch, TextField } from '@openmes/ui';
import { __ } from '../../lib/i18n';

/**
 * Renders one run option from an importer's option schema
 * ({key, type: select|line|number|text|switch, label, choices, min, max, help, …}).
 */
export default function OptionField({ option, value, onChange, lines = [], error }) {
    const label = option.label;
    const choice = option.type === 'select'
        ? (option.choices ?? []).find((c) => String(c.value) === String(value ?? ''))
        : null;

    let control;
    switch (option.type) {
        case 'select':
            control = (
                <Dropdown
                    aria-label={label}
                    className="w-full"
                    value={value == null ? '' : String(value)}
                    onChange={(v) => onChange(v)}
                    options={[
                        ...(option.nullable ? [{ value: '', label: __('— None —') }] : []),
                        ...(option.choices ?? []).map((c) => ({ value: String(c.value), label: c.label })),
                    ]}
                />
            );
            break;
        case 'line':
            control = (
                <Dropdown
                    aria-label={label}
                    className="w-full"
                    value={value == null ? '' : String(value)}
                    onChange={(v) => onChange(v)}
                    options={[
                        { value: '', label: __('— Use line code column from file —') },
                        ...lines.map((l) => ({ value: String(l.id), label: l.name })),
                    ]}
                />
            );
            break;
        case 'number':
            control = (
                <TextField
                    type="number"
                    inputMode="numeric"
                    aria-label={label}
                    min={option.min}
                    max={option.max}
                    value={value ?? ''}
                    onChange={(v) => onChange(v)}
                    error={error}
                />
            );
            break;
        case 'switch':
            control = <Switch aria-label={label} checked={!!value} onChange={(next) => onChange(next)} />;
            break;
        default:
            control = (
                <TextField
                    aria-label={label}
                    value={value ?? ''}
                    onChange={(v) => onChange(v)}
                    maxLength={option.maxLength}
                    pattern={option.pattern}
                    error={error}
                />
            );
    }

    return (
        <div>
            <div className="form-label">{label}</div>
            {control}
            {choice?.description && <p className="text-xs text-om-faint mt-1">{choice.description}</p>}
            {option.help && <p className="text-xs text-om-faint mt-1">{option.help}</p>}
            {error && !['text', 'number'].includes(option.type) && <p className="text-xs text-om-blocked mt-1">{error}</p>}
        </div>
    );
}
