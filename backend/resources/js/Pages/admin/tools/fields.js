const STATUSES = [
    { value: 'available', label: 'Available' },
    { value: 'in_use', label: 'In Use' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'retired', label: 'Retired' },
];

export function toolFields(workstationTypes) {
    return [
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        {
            name: 'workstation_type_id',
            label: 'Workstation Type',
            type: 'select',
            options: [
                { value: '', label: '— None —' },
                ...workstationTypes.map((w) => ({ value: String(w.id), label: w.name })),
            ],
        },
        { name: 'status', label: 'Status', type: 'select', options: STATUSES },
        { name: 'next_service_at', label: 'Next Service', type: 'date' },
    ];
}

export const TOOL_STATUS_LABELS = Object.fromEntries(STATUSES.map((s) => [s.value, s.label]));
