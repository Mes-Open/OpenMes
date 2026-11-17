import { __ } from '../../../lib/i18n';

/**
 * The alias form. `units` are the units already in use (aliases, materials,
 * products), shown as a hint so an alias points at the spelling the item
 * master really uses.
 */
export function unitAliasFields(units = []) {
    return [
        {
            name: 'alias',
            label: __('Alias'),
            required: true,
            maxLength: 20,
            placeholder: 'Stück',
            help: __('The unit name as your ERP sends it. Case, spaces and a trailing dot are ignored.'),
        },
        {
            name: 'unit',
            label: __('Canonical unit'),
            required: true,
            maxLength: 20,
            placeholder: 'pcs',
            help: units.length
                ? __('The unit OpenMES stores. In use: :units', { units: units.join(', ') })
                : __('The unit OpenMES stores.'),
        },
    ];
}

/**
 * A record as form values, and with no record an empty form — shared by
 * Create.jsx, Edit.jsx and the list's drawer.
 */
export function unitAliasInitial(record) {
    if (!record) {
        return { alias: '', unit: '' };
    }

    return {
        alias: record.alias ?? '',
        unit: record.unit ?? '',
    };
}
