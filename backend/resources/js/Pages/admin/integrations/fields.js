export const INTEGRATION_FIELDS = [
    {
        name: 'system_type',
        label: 'System Type',
        required: true,
        type: 'select',
        options: [
            { value: '', label: 'Select...' },
            { value: 'subiekt_gt', label: 'Subiekt GT' },
            { value: 'subiekt_nexo', label: 'Subiekt nexo' },
            { value: 'wms', label: 'WMS' },
            { value: 'erp_custom', label: 'Custom ERP' },
        ],
    },
    { name: 'system_name', label: 'System Name', required: true },
    { name: 'is_active', label: 'Active', type: 'checkbox' },
];
