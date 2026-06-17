import { __ } from '../../../lib/i18n';

export const INTEGRATION_FIELDS = [
    {
        name: 'system_type',
        label: __('System Type'),
        required: true,
        type: 'select',
        options: [
            { value: '', label: __('Select...') },
            { value: 'subiekt_gt', label: 'Subiekt GT' },
            { value: 'subiekt_nexo', label: 'Subiekt nexo' },
            { value: 'wms', label: 'WMS' },
            { value: 'erp_custom', label: __('Custom ERP') },
        ],
    },
    { name: 'system_name', label: __('System Name'), required: true },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];
