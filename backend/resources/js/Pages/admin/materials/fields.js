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
                { value: '', label: __('— Select type —') },
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
