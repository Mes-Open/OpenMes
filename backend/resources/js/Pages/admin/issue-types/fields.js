import { __ } from '../../../lib/i18n';

export const issueTypeFields = () => [
    { name: 'code', label: __('Code'), required: true },
    { name: 'name', label: __('Name'), required: true },
    {
        name: 'severity',
        label: __('Severity'),
        type: 'select',
        required: true,
        options: [
            { value: 'LOW', label: __('Low') },
            { value: 'MEDIUM', label: __('Medium') },
            { value: 'HIGH', label: __('High') },
            { value: 'CRITICAL', label: __('Critical') },
        ],
    },
    { name: 'is_blocking', label: __('Blocking'), type: 'checkbox' },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];

export const SEVERITY_LABELS = {
    LOW: __('Low'),
    MEDIUM: __('Medium'),
    HIGH: __('High'),
    CRITICAL: __('Critical'),
};
