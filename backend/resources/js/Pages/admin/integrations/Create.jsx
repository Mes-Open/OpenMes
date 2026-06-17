import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { INTEGRATION_FIELDS } from './fields';
import { __ } from '../../../lib/i18n';

export default function IntegrationCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Integration')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Integration')}</h1>
            <ResourceForm
                action="/admin/integrations"
                method="post"
                fields={INTEGRATION_FIELDS}
                initial={{ system_type: '', system_name: '', is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/integrations"
            />
        </div>
    );
}

IntegrationCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
