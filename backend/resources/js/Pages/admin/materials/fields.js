import { __ } from '../../../lib/i18n';

const TRACKING = [
    { value: 'none', label: __('None') },
    { value: 'batch', label: __('Batch') },
    { value: 'serial', label: __('Serial') },
];

export function materialFields(materialTypes) {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'material_type_id',
            label: __('Material Type'),
            type: 'select',
            required: true,
            options: [
                { value: '', label: __('— Select Material Type —') },
                ...materialTypes.map((t) => ({ value: String(t.id), label: t.name })),
            ],
        },
        { name: 'unit_of_measure', label: __('Unit of Measure'), placeholder: __('pcs'), help: __('e.g. pcs, kg, l, m. Optional.') },
        { name: 'tracking_type', label: __('Tracking'), type: 'select', options: TRACKING, help: __('Batch = grouped lots, Serial = individual items, None = untracked.') },
        { name: 'default_scrap_percentage', label: __('Default Scrap %'), type: 'number', help: __('Pre-fills the scrap % on BOM lines using this material; can be overridden.') },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'external_code', label: __('External Code'), help: __('Only for ERP/integration sync — leave blank otherwise.') },
        { name: 'external_system', label: __('External System'), help: __('Only for ERP/integration sync — leave blank otherwise.') },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}

export const TRACKING_LABELS = Object.fromEntries(TRACKING.map((t) => [t.value, t.label]));

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function materialInitial(record) {
    if (!record) {
        return {
            code: '', name: '', material_type_id: '', unit_of_measure: 'pcs',
            tracking_type: 'none', default_scrap_percentage: '', description: '',
            external_code: '', external_system: '', is_active: true, custom_fields: {},
        };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        material_type_id: record.material_type_id != null ? String(record.material_type_id) : '',
        unit_of_measure: record.unit_of_measure ?? '',
        tracking_type: record.tracking_type ?? 'none',
        default_scrap_percentage: record.default_scrap_percentage ?? '',
        description: record.description ?? '',
        external_code: record.external_code ?? '',
        external_system: record.external_system ?? '',
        is_active: !!record.is_active,
        custom_fields: record.custom_fields ?? {},
    };
}
