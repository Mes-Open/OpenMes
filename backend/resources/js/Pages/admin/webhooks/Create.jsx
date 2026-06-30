import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { webhookFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function WebhookCreate() {
    const { events = [], generatedSecret = '' } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Webhook')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Webhook')}</h1>
            <ResourceForm
                action="/admin/webhooks"
                method="post"
                fields={webhookFields(events)}
                initial={{ name: '', url: '', events: [], secret: generatedSecret, is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/webhooks"
            />
        </div>
    );
}

WebhookCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
