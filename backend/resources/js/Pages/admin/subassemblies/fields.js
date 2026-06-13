import { __ } from '../../../lib/i18n';

export function subassemblyFields(productTypes) {
    return [
        {
            name: 'product_type_id',
            label: __('Product Type'),
            type: 'select',
            options: [
                { value: '', label: __('— None —') },
                ...productTypes.map((p) => ({ value: String(p.id), label: p.name })),
            ],
        },
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
