const TRACKING = [
    { value: 'none', label: 'None' },
    { value: 'batch', label: 'Batch' },
    { value: 'serial', label: 'Serial' },
];

export function materialFields(materialTypes) {
    return [
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        {
            name: 'material_type_id',
            label: 'Material Type',
            type: 'select',
            required: true,
            options: [
                { value: '', label: '— Select type —' },
                ...materialTypes.map((t) => ({ value: String(t.id), label: t.name })),
            ],
        },
        { name: 'unit_of_measure', label: 'Unit of Measure', placeholder: 'pcs', help: 'e.g. pcs, kg, l, m. Optional.' },
        { name: 'tracking_type', label: 'Tracking', type: 'select', options: TRACKING, help: 'Batch = grouped lots, Serial = individual items, None = untracked.' },
        { name: 'default_scrap_percentage', label: 'Default Scrap %', type: 'number', help: 'Pre-fills the scrap % on BOM lines using this material; can be overridden.' },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'external_code', label: 'External Code', help: 'Only for ERP/integration sync — leave blank otherwise.' },
        { name: 'external_system', label: 'External System', help: 'Only for ERP/integration sync — leave blank otherwise.' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}

export const TRACKING_LABELS = Object.fromEntries(TRACKING.map((t) => [t.value, t.label]));
