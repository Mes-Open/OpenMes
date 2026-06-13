import { __ } from '../../../lib/i18n';

export const WO_STATUSES = ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'PAUSED', 'BLOCKED', 'DONE', 'REJECTED', 'CANCELLED'];

export const WO_STATUS_STYLES = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    ACCEPTED: 'bg-blue-100 text-blue-800',
    IN_PROGRESS: 'bg-emerald-100 text-emerald-800',
    PAUSED: 'bg-gray-200 text-gray-700',
    BLOCKED: 'bg-red-100 text-red-800',
    DONE: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
    CANCELLED: 'bg-gray-200 text-gray-500',
};

/** Localized display label for a work-order status enum value. */
export function woStatusLabel(status) {
    const labels = {
        PENDING: __('Pending'),
        ACCEPTED: __('Accepted'),
        IN_PROGRESS: __('In Progress'),
        PAUSED: __('Paused'),
        BLOCKED: __('Blocked'),
        DONE: __('Done'),
        REJECTED: __('Rejected'),
        CANCELLED: __('Cancelled'),
    };
    return labels[status] ?? status;
}

export function woFields(lines, productTypes, { withStatus = false } = {}) {
    const fields = [
        { name: 'order_no', label: __('Order No'), required: true },
        {
            name: 'line_id', label: __('Line'), type: 'select',
            options: [{ value: '', label: __('— None —') }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))],
        },
        {
            name: 'product_type_id', label: __('Product Type'), type: 'select',
            options: [{ value: '', label: __('— None —') }, ...productTypes.map((p) => ({ value: String(p.id), label: p.name }))],
        },
        { name: 'planned_qty', label: __('Planned Qty'), type: 'number', required: true },
        { name: 'priority', label: __('Priority (0–100)'), type: 'number' },
        { name: 'due_date', label: __('Due Date'), type: 'date' },
        { name: 'description', label: __('Description'), type: 'textarea' },
    ];
    if (withStatus) {
        fields.push({ name: 'status', label: __('Status'), type: 'select', options: WO_STATUSES.map((s) => ({ value: s, label: woStatusLabel(s) })) });
    }
    return fields;
}
