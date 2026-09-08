import { __ } from '../../../lib/i18n';

export const ISSUE_TYPE_FIELDS = [
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

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function issueTypeInitial(record) {
    if (!record) {
        return { code: '', name: '', severity: 'MEDIUM', is_blocking: false, is_active: true };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        severity: record.severity ?? 'MEDIUM',
        is_blocking: !!record.is_blocking,
        is_active: !!record.is_active,
    };
}
