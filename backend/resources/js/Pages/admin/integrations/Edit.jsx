import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { integrationFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function IntegrationEdit({ integration }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: integration.system_name })} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">{__('Edit Integration')}</h1>
            <ResourceForm
                action={`/admin/integrations/${integration.id}`}
                method="put"
                fields={integrationFields()}
                initial={{
                    system_type: integration.system_type ?? '',
                    system_name: integration.system_name ?? '',
                    is_active: !!integration.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/integrations"
            />
        </div>
    );
}

IntegrationEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
