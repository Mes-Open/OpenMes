export const PRODUCT_TYPE_FIELDS = [
    {
        name: 'code',
        label: 'Product Code',
        required: true,
        placeholder: 'e.g., WIDGET-A, PROD-001',
        help: 'Unique identifier',
    },
    {
        name: 'name',
        label: 'Product Name',
        required: true,
        placeholder: 'e.g., Widget Type A, Standard Component',
    },
    {
        name: 'description',
        label: 'Description',
        type: 'textarea',
        placeholder: 'Optional description',
    },
    {
        name: 'unit_of_measure',
        label: 'Unit of Measure',
        placeholder: 'e.g., pcs, kg, m (optional)',
        help: 'How this product is counted or measured',
    },
    {
        name: 'is_active',
        label: 'Active (ready for production)',
        type: 'checkbox',
    },
];
