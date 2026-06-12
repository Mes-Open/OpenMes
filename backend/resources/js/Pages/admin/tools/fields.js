import { __ } from '../../../lib/i18n';

const STATUSES = [
    { value: 'available', label: __('Available') },
    { value: 'in_use', label: __('In Use') },
    { value: 'maintenance', label: __('Maintenance') },
    { value: 'retired', label: __('Retired') },
];

export function toolFields(workstationTypes) {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        { name: 'description', label: __('Description'), type: 'textarea' },
        {
            name: 'workstation_type_id',
            label: __('Workstation Type'),
            type: 'select',
            help: __('Optional — the type of workstation this tool belongs to.'),
            options: [
                { value: '', label: __('— None —') },
                ...workstationTypes.map((w) => ({ value: String(w.id), label: w.name })),
            ],
        },
        { name: 'status', label: __('Status'), type: 'select', options: STATUSES },
        { name: 'next_service_at', label: __('Next Service'), type: 'date', help: __('Optional — leave blank if no service is scheduled.') },
    ];
}

export const TOOL_STATUS_LABELS = Object.fromEntries(STATUSES.map((s) => [s.value, s.label]));
