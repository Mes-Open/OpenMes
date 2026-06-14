import { __ } from '../../../lib/i18n';

export const anomalyReasonFields = () => [
    { name: 'code', label: __('Code'), required: true },
    { name: 'name', label: __('Name'), required: true },
    { name: 'category', label: __('Category') },
    { name: 'description', label: __('Description'), type: 'textarea' },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];
