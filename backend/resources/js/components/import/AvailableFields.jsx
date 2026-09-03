import { __ } from '../../lib/i18n';

/** The PrestaShop-style "available fields" panel for the chosen entity. */
export default function AvailableFields({ entity }) {
    const fields = entity?.fields ?? [];
    const groups = entity?.identifierGroups ?? [];
    const labelOf = (key) => fields.find((f) => f.key === key)?.label ?? key;

    return (
        <div className="px-5 py-4">
            <h2 className="text-sm font-bold text-om-ink mb-2">{__('Available fields')}</h2>
            <ul className="space-y-1.5">
                {fields.map((f) => (
                    <li key={f.key} className="text-sm">
                        <span className="text-om-ink">
                            {f.label}
                            {f.required && <span className="text-om-blocked font-bold ml-0.5" aria-label={__('required')}>*</span>}
                        </span>
                        <code className="ml-2 text-[11px] text-om-faint font-mono">{f.key}</code>
                        {f.description && <p className="text-xs text-om-muted">{f.description}</p>}
                    </li>
                ))}
                {entity?.allowsCustomFields && (
                    <li className="text-sm pt-1 border-t border-om-line2">
                        <code className="text-[11px] text-purple-700 font-mono">custom:field_name</code>
                        <p className="text-xs text-om-muted">{__('Any other column, kept on the record as a custom field.')}</p>
                    </li>
                )}
            </ul>
            <p className="text-xs text-om-faint mt-3">* {__('Required field')}</p>
            {groups.length > 0 && (
                <p className="text-xs text-om-faint">
                    {__('One of :fields is required', { fields: groups.map((g) => g.map(labelOf).join(' + ')).join(', ') })}
                </p>
            )}
        </div>
    );
}
