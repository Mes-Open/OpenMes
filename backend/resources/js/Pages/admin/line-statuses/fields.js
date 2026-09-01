import { __ } from '../../../lib/i18n';

/**
 * A global kanban status. Shared by the create and edit pages so the two can't
 * drift — the bug the old inline editor made easy, since it hand-wrote its add
 * row and its edit row separately.
 */
export const LINE_STATUS_FIELDS = [
    { name: 'color', label: __('Color'), type: 'color', required: true },
    { name: 'name', label: __('Name'), required: true, placeholder: __('e.g. Waiting for parts') },
    {
        name: 'sort_order',
        label: __('Order'),
        type: 'number',
        help: __('Position on the board, low to high.'),
    },
    {
        name: 'is_default',
        label: __('Default status'),
        type: 'checkbox',
        help: __('New work orders start here. Setting this clears it from the status that had it.'),
    },
];

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer. A new status lands at the end of the board: the set is renumbered
 * 1..n on every write, so `nextSortOrder` is one past the current last.
 */
export function lineStatusInitial(record, { nextSortOrder = 0 } = {}) {
    if (!record) {
        return { color: '#3b82f6', name: '', sort_order: nextSortOrder, is_default: false };
    }

    return {
        color: record.color ?? '#3b82f6',
        name: record.name ?? '',
        sort_order: record.sort_order ?? 0,
        is_default: !!record.is_default,
    };
}
