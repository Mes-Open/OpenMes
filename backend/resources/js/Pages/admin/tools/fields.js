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

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function toolInitial(record) {
    if (!record) {
        return { code: '', name: '', description: '', workstation_type_id: '', status: 'available', next_service_at: '' };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        description: record.description ?? '',
        workstation_type_id: record.workstation_type_id != null ? String(record.workstation_type_id) : '',
        status: record.status ?? 'available',
        next_service_at: (record.next_service_at ?? '').slice(0, 10),
        custom_fields: record.custom_fields ?? {},
    };
}
