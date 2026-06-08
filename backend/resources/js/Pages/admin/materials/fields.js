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
        { name: 'unit_of_measure', label: 'Unit of Measure', placeholder: 'pcs' },
        { name: 'tracking_type', label: 'Tracking', type: 'select', options: TRACKING },
        { name: 'default_scrap_percentage', label: 'Default Scrap %', type: 'number' },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'external_code', label: 'External Code' },
        { name: 'external_system', label: 'External System' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}

export const TRACKING_LABELS = Object.fromEntries(TRACKING.map((t) => [t.value, t.label]));
