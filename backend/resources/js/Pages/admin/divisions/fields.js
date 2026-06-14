import { __ } from '../../../lib/i18n';

export function divisionFields(factories) {
    return [
        {
            name: 'factory_id',
            label: __('Factory'),
            type: 'select',
            options: [
                { value: '', label: __('— None —') },
                ...factories.map((f) => ({ value: String(f.id), label: f.name })),
            ],
        },
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
