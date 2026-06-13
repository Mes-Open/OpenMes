import { __ } from '../../../lib/i18n';

export function areaFields(sites) {
    return [
        {
            name: 'site_id',
            label: __('Site'),
            type: 'select',
            required: true,
            options: [
                { value: '', label: __('— Select site —') },
                ...sites.map((s) => ({ value: String(s.id), label: s.name })),
            ],
        },
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
