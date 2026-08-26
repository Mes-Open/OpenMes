import { __ } from '../../../lib/i18n';

export const COMPANY_FIELDS = [
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

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function companyInitial(record) {
    if (!record) {
        return { code: '', name: '', tax_id: '', type: 'supplier', email: '', phone: '', address: '', is_active: true };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        tax_id: record.tax_id ?? '',
        type: record.type ?? 'supplier',
        email: record.email ?? '',
        phone: record.phone ?? '',
        address: record.address ?? '',
        is_active: !!record.is_active,
    };
}
