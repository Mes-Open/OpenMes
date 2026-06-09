export function materialLotFields(materials, sources, statuses) {
    return [
        { name: 'lot_number', label: 'Lot Number', required: true, placeholder: 'e.g. ACME-STEEL-2026-W24-001', help: 'Required. A unique identifier for this lot/batch.' },
        {
            name: 'material_id',
            label: 'Material',
            type: 'select',
            required: true,
            options: [
                { value: '', label: '— Select material (required) —' },
                ...materials.map((m) => ({ value: String(m.id), label: m.name })),
            ],
        },
        {
            name: 'source_id',
            label: 'Source',
            type: 'select',
            options: [
                { value: '', label: '— None —' },
                ...sources.map((s) => ({ value: String(s.id), label: s.external_name })),
            ],
        },
        { name: 'quantity_received', label: 'Qty Received', type: 'number', required: true },
        { name: 'quantity_available', label: 'Qty Available', type: 'number', help: 'Defaults to the received quantity if left blank.' },
        { name: 'unit_of_measure', label: 'Unit', required: true, placeholder: 'e.g. pcs, kg, l', help: 'Required.' },
        { name: 'received_at', label: 'Received', type: 'date', required: true },
        { name: 'manufacturing_date', label: 'Mfg Date', type: 'date' },
        { name: 'expiry_date', label: 'Expiry', type: 'date' },
        {
            name: 'status',
            label: 'Status',
            type: 'select',
            required: true,
            options: statuses.map((s) => ({ value: s, label: s })),
        },
        { name: 'supplier_lot_no', label: 'Supplier Lot #' },
        { name: 'supplier_reference', label: 'Supplier Ref' },
    ];
}

export const STATUS_STYLES = {
    received: 'bg-blue-100 text-blue-700',
    quarantine: 'bg-yellow-100 text-yellow-700',
    released: 'bg-green-100 text-green-700',
    consumed: 'bg-gray-100 text-gray-700',
    expired: 'bg-red-100 text-red-700',
    rejected: 'bg-red-100 text-red-700',
};
