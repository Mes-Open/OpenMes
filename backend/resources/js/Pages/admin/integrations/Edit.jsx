import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { INTEGRATION_FIELDS } from './fields';

export default function IntegrationEdit({ integration }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${integration.system_name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Integration</h1>
            <ResourceForm
                action={`/admin/integrations/${integration.id}`}
                method="put"
                fields={INTEGRATION_FIELDS}
                initial={{
                    system_type: integration.system_type ?? '',
                    system_name: integration.system_name ?? '',
                    is_active: !!integration.is_active,
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/integrations"
            />
        </div>
    );
}

IntegrationEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
