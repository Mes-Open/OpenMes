import { __ } from '../../../lib/i18n';

export const productTypeFields = () => [
    {
        name: 'code',
        label: __('Product Code'),
        required: true,
        placeholder: __('e.g., WIDGET-A, PROD-001'),
        help: __('Unique identifier'),
    },
    {
        name: 'name',
        label: __('Product Name'),
        required: true,
        placeholder: __('e.g., Widget Type A, Standard Component'),
    },
    {
        name: 'description',
        label: __('Description'),
        type: 'textarea',
        placeholder: __('Optional description'),
    },
    {
        name: 'unit_of_measure',
        label: __('Unit of Measure'),
        placeholder: __('e.g., pcs, kg, m (optional)'),
        help: __('How this product is counted or measured'),
    },
    {
        name: 'is_active',
        label: __('Active (ready for production)'),
        type: 'checkbox',
    },
];
