import { __ } from '../../../lib/i18n';

export const PRODUCT_TYPE_FIELDS = [
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
        name: 'image',
        label: __('Product Image'),
        type: 'image',
        help: __('Optional. JPEG, PNG or WebP, up to 5 MB.'),
    },
    {
        name: 'is_active',
        label: __('Active (ready for production)'),
        type: 'checkbox',
    },
];

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function productTypeInitial(record, { imageUrls } = {}) {
    if (!record) {
        return { code: '', name: '', description: '', unit_of_measure: 'pcs', image: null, is_active: true, custom_fields: {} };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        description: record.description ?? '',
        unit_of_measure: record.unit_of_measure ?? 'pcs',
        image: null,
        // The standalone edit page gets this from the model accessor; the list
        // hands it over separately, since no column behind it is synced.
        image_url: record.image_url ?? imageUrls?.[record.id] ?? null,
        remove_image: false,
        is_active: !!record.is_active,
        custom_fields: record.custom_fields ?? {},
    };
}
