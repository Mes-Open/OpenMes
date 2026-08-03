import { __ } from '../../../lib/i18n';

/** Keep in sync with Warehouse::KINDS — the backend validates against that list. */
export const WAREHOUSE_KINDS = [
    { value: 'mixed', label: __('Mixed (materials & products)') },
    { value: 'raw_material', label: __('Raw materials') },
    { value: 'finished_goods', label: __('Finished goods') },
];

export const WAREHOUSE_FIELDS = [
    { name: 'code', label: __('Code'), required: true },
    { name: 'name', label: __('Name'), required: true },
    {
        name: 'kind',
        label: __('Kind'),
        type: 'select',
        options: WAREHOUSE_KINDS,
        help: __('What this warehouse may hold. Documents can only be posted to a matching warehouse.'),
    },
    { name: 'description', label: __('Description'), type: 'textarea' },
    {
        name: 'erp_code',
        label: __('ERP Code'),
        help: __('Identifier of this warehouse in the connected ERP. Leave blank for an OpenMES-only warehouse.'),
    },
    {
        name: 'is_default',
        label: __('Default for its kind'),
        type: 'checkbox',
        help: __('Used when an import or a generated document names no warehouse.'),
    },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];
