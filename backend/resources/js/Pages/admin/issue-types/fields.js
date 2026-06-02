export const ISSUE_TYPE_FIELDS = [
    { name: 'code', label: 'Code', required: true },
    { name: 'name', label: 'Name', required: true },
    {
        name: 'severity',
        label: 'Severity',
        type: 'select',
        required: true,
        options: [
            { value: 'LOW', label: 'Low' },
            { value: 'MEDIUM', label: 'Medium' },
            { value: 'HIGH', label: 'High' },
            { value: 'CRITICAL', label: 'Critical' },
        ],
    },
    { name: 'is_blocking', label: 'Blocking', type: 'checkbox' },
    { name: 'is_active', label: 'Active', type: 'checkbox' },
];

export const SEVERITY_LABELS = {
    LOW: 'Low',
    MEDIUM: 'Medium',
    HIGH: 'High',
    CRITICAL: 'Critical',
};
