import { jsonColumn } from '../../../lib/syncedRow';
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
        {
            name: 'legacy_signature',
            label: __('Legacy signature'),
            type: 'checkbox',
            help: __('Sign the request body only, without the timestamp. Keep on for integrations built before timestamped signatures; turn off once your receiver verifies X-OpenMES-Timestamp.'),
        },
    ];
}

/**
 * A record as form values, and with no record an empty form.
 *
 * `secret` is asymmetric on purpose: a new webhook is seeded with the secret the
 * server generated for the form, while an existing one comes back blank. The
 * stored secret is deliberately absent from the `webhooks` collection — it must
 * never be broadcast to every subscribed browser — so editing can only ever set
 * a new one, never show the current one.
 */
export function webhookInitial(record, { generatedSecret = '' } = {}) {
    if (!record) {
        // New endpoints use the timestamped signature by default.
        return { name: '', url: '', events: [], secret: generatedSecret, is_active: true, legacy_signature: false };
    }

    return {
        name: record.name ?? '',
        url: record.url ?? '',
        events: jsonColumn(record.events, []),
        secret: '',
        is_active: !!record.is_active,
        legacy_signature: !!record.legacy_signature,
    };
}
