import { __ } from '../../../lib/i18n';

/**
 * Form fields for a webhook endpoint. `events` is [{key,label}] from the
 * WebhookEventRegistry. On edit the secret is never sent back, so the field is
 * optional and only overwrites when filled.
 */
export function webhookFields(events, { isEdit = false } = {}) {
    return [
        { name: 'name', label: __('Name'), required: true },
        { name: 'url', label: __('Endpoint URL'), required: true, placeholder: 'https://example.com/hooks/openmes' },
        {
            name: 'events',
            label: __('Subscribed events'),
            type: 'checkbox-group',
            required: true,
            options: events.map((e) => ({ value: e.key, label: e.label })),
        },
        {
            name: 'secret',
            label: __('Signing secret'),
            help: isEdit
                ? __('Leave blank to keep the current secret.')
                : __('Leave blank to auto-generate.'),
        },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
