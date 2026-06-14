import { __ } from '../../../lib/i18n';

export function lineFields(areas) {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'area_id', label: __('Area'), type: 'select',
            options: [{ value: '', label: __('— None —') }, ...areas.map((a) => ({ value: String(a.id), label: a.name }))],
        },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
