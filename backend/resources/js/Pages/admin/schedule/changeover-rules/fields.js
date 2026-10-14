import { __ } from '../../../../lib/i18n';

/**
 * Form fields for a changeover rule. `from` / `to` are free text matched
 * case-insensitively against the previous / next job's product type code or
 * category; the help lists the values that exist so nobody has to guess.
 */
export function changeoverRuleFields({ attributes = [], workstationTypes = [], workstations = [], productTypeCodes = [], productCategories = [] } = {}) {
    const known = (list) => (list.length ? __('Known values: :values', { values: list.slice(0, 12).join(', ') }) : null);
    const valueHelp = [
        __('Leave empty to match any value.'),
        known(productCategories) && `${__('Product category')}: ${known(productCategories)}`,
        known(productTypeCodes) && `${__('Product type (code)')}: ${known(productTypeCodes)}`,
    ].filter(Boolean).join(' ');

    return [
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'attribute',
            label: __('Compare by'),
            type: 'select',
            required: true,
            options: [{ value: '', label: __('— Select —') }, ...attributes.map((a) => ({ value: a.value, label: a.label }))],
        },
        { name: 'from_value', label: __('Previous job'), help: valueHelp },
        { name: 'to_value', label: __('Next job'), help: __('Leave empty to match any value.') },
        { name: 'setup_minutes', label: __('Setup time (min)'), type: 'number', required: true, min: 0, step: 1,
            help: __('Replaces the routing’s standard setup time for this transition.') },
        {
            name: 'workstation_type_id',
            label: __('Equipment class'),
            type: 'select',
            help: __('Optional — limit the rule to one equipment class.'),
            options: [{ value: '', label: __('— Any —') }, ...workstationTypes.map((t) => ({ value: String(t.id), label: t.name }))],
        },
        {
            name: 'workstation_id',
            label: __('Workstation'),
            type: 'select',
            help: __('Optional — limit the rule to one workstation. The most specific matching rule wins.'),
            options: [{ value: '', label: __('— Any —') }, ...workstations.map((w) => ({ value: String(w.id), label: `${w.name} (${w.code})` }))],
        },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}

/** Where the rule pages sit under Schedule — the sidebar has one entry for all of it. */
export function changeoverTrail({ withList = true } = {}) {
    return [
        { label: __('Automatic scheduling'), href: '/admin/schedule/auto', icon: 'calendar-cog' },
        ...(withList ? [{ label: __('Changeover Rules'), href: '/admin/schedule/changeover-rules', icon: 'repeat' }] : []),
    ];
}

/** A record as form values, and with no record an empty form. */
export function changeoverRuleInitial(record) {
    if (!record) {
        return { name: '', attribute: 'product_category', from_value: '', to_value: '', setup_minutes: '', workstation_type_id: '', workstation_id: '', is_active: true };
    }

    return {
        name: record.name ?? '',
        attribute: record.attribute ?? '',
        from_value: record.from_value ?? '',
        to_value: record.to_value ?? '',
        setup_minutes: record.setup_minutes != null ? String(record.setup_minutes) : '',
        workstation_type_id: record.workstation_type_id != null ? String(record.workstation_type_id) : '',
        workstation_id: record.workstation_id != null ? String(record.workstation_id) : '',
        is_active: !!record.is_active,
    };
}
