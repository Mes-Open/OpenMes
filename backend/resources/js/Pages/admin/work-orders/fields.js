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

export function woFields(lines, productTypes, { withStatus = false } = {}) {
    const fields = [
        { name: 'order_no', label: 'Order No', required: true },
        {
            name: 'line_id', label: 'Line', type: 'select',
            options: [{ value: '', label: '— None —' }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))],
        },
        {
            name: 'product_type_id', label: 'Product Type', type: 'select',
            options: [{ value: '', label: '— None —' }, ...productTypes.map((p) => ({ value: String(p.id), label: p.name }))],
        },
        { name: 'planned_qty', label: 'Planned Qty', type: 'number', required: true },
        { name: 'priority', label: 'Priority (0–100)', type: 'number' },
        { name: 'due_date', label: 'Due Date', type: 'date' },
        { name: 'description', label: 'Description', type: 'textarea' },
    ];
    if (withStatus) {
        fields.push({ name: 'status', label: 'Status', type: 'select', options: WO_STATUSES.map((s) => ({ value: s, label: s })) });
    }
    return fields;
}
