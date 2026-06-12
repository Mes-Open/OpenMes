import { __ } from '../../../lib/i18n';

/** Localized display label for a material-lot status enum value. */
export function materialLotStatusLabel(status) {
    const labels = {
        received: __('Received'),
        quarantine: __('Quarantine'),
        released: __('Released'),
        consumed: __('Consumed'),
        expired: __('Expired'),
        rejected: __('Rejected'),
    };
    return labels[status] ?? status;
}

export function materialLotFields(materials, sources, statuses) {
    return [
        { name: 'lot_number', label: __('Lot Number'), required: true, placeholder: __('e.g. ACME-STEEL-2026-W24-001'), help: __('Required. A unique identifier for this lot/batch.') },
        {
            name: 'material_id',
            label: __('Material'),
            type: 'select',
            required: true,
            options: [
                { value: '', label: __('— Select material (required) —') },
                ...materials.map((m) => ({ value: String(m.id), label: m.name })),
            ],
        },
        {
            name: 'source_id',
            label: __('Source'),
            type: 'select',
            options: [
                { value: '', label: __('— None —') },
                ...sources.map((s) => ({ value: String(s.id), label: s.external_name })),
            ],
        },
        { name: 'quantity_received', label: __('Qty Received'), type: 'number', required: true },
        { name: 'quantity_available', label: __('Qty Available'), type: 'number', help: __('Defaults to the received quantity if left blank.') },
        { name: 'unit_of_measure', label: __('Unit'), required: true, placeholder: __('e.g. pcs, kg, l'), help: __('Required.') },
        { name: 'received_at', label: __('Received'), type: 'date', required: true },
        { name: 'manufacturing_date', label: __('Mfg Date'), type: 'date' },
        { name: 'expiry_date', label: __('Expiry'), type: 'date' },
        {
            name: 'status',
            label: __('Status'),
            type: 'select',
            required: true,
            options: statuses.map((s) => ({ value: s, label: materialLotStatusLabel(s) })),
        },
        { name: 'supplier_lot_no', label: __('Supplier Lot #') },
        { name: 'supplier_reference', label: __('Supplier Ref') },
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
