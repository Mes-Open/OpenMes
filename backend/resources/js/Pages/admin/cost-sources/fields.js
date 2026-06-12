import { __ } from '../../../lib/i18n';

export const costSourceFields = () => [
    { name: 'code', label: __('Code'), required: true },
    { name: 'name', label: __('Name'), required: true },
    { name: 'description', label: __('Description'), type: 'textarea' },
    { name: 'unit_cost', label: __('Unit Cost'), type: 'number' },
    { name: 'unit', label: __('Unit') },
    { name: 'currency', label: __('Currency') },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];
