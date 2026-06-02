export const COMPANY_FIELDS = [
    { name: 'code', label: 'Code', required: true },
    { name: 'name', label: 'Name', required: true },
    { name: 'tax_id', label: 'Tax ID' },
    {
        name: 'type',
        label: 'Type',
        type: 'select',
        required: true,
        options: [
            { value: 'supplier', label: 'Supplier' },
            { value: 'customer', label: 'Customer' },
            { value: 'both', label: 'Both' },
        ],
    },
    { name: 'email', label: 'Email' },
    { name: 'phone', label: 'Phone' },
    { name: 'address', label: 'Address', type: 'textarea' },
    { name: 'is_active', label: 'Active', type: 'checkbox' },
];
