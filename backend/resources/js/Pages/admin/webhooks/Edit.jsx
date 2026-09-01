import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { webhookFields, webhookInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function WebhookEdit() {
    const { webhook, events = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Webhook')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Webhook')}</h1>
            <ResourceForm
                action={`/admin/webhooks/${webhook.id}`}
                method="put"
                fields={webhookFields(events, { isEdit: true })}
                initial={webhookInitial(webhook)}
                submitLabel={__('Save')}
                cancelHref="/admin/webhooks"
            />
        </div>
    );
}

WebhookEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
