import { __ } from '../../../lib/i18n';

export const companyFields = () => [
    { name: 'code', label: __('Code'), required: true },
    { name: 'name', label: __('Name'), required: true },
    { name: 'tax_id', label: __('Tax ID') },
    {
        name: 'type',
        label: __('Type'),
        type: 'select',
        required: true,
        options: [
            { value: 'supplier', label: __('Supplier') },
            { value: 'customer', label: __('Customer') },
            { value: 'both', label: __('Both') },
        ],
    },
    { name: 'email', label: __('Email') },
    { name: 'phone', label: __('Phone') },
    { name: 'address', label: __('Address'), type: 'textarea' },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];
