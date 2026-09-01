import { __ } from '../../../lib/i18n';

/**
 * Kind options and form fields, as functions rather than module constants.
 *
 * Page modules are bundled eagerly (`import.meta.glob(..., { eager: true })` in
 * app.jsx), so anything evaluated at module scope runs BEFORE the locale chunk is
 * loaded — a `__()` there would freeze the untranslated English key. Calling these
 * during render translates against the loaded dictionary.
 *
 * Keep the values in sync with Warehouse::KINDS — the backend validates against it.
 */
export function warehouseKinds() {
    return [
        { value: 'mixed', label: __('Mixed (materials & products)') },
        { value: 'raw_material', label: __('Raw materials') },
        { value: 'finished_goods', label: __('Finished goods') },
    ];
}

export function warehouseFields() {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'kind',
            label: __('Kind'),
            type: 'select',
            options: warehouseKinds(),
            help: __('What this warehouse may hold. Documents can only be posted to a matching warehouse.'),
        },
        { name: 'description', label: __('Description'), type: 'textarea' },
        {
            name: 'erp_code',
            label: __('ERP Code'),
            help: __('Identifier of this warehouse in the connected ERP. Leave blank for an OpenMES-only warehouse.'),
        },
        {
            name: 'is_default',
            label: __('Default for its kind'),
            type: 'checkbox',
            help: __('Used when an import or a generated document names no warehouse.'),
        },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function warehouseInitial(record) {
    if (!record) {
        return {
            code: '',
            name: '',
            kind: 'mixed',
            description: '',
            erp_code: '',
            is_default: false,
            is_active: true,
        };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        kind: record.kind ?? 'mixed',
        description: record.description ?? '',
        erp_code: record.erp_code ?? '',
        is_default: !!record.is_default,
        is_active: !!record.is_active,
    };
}
