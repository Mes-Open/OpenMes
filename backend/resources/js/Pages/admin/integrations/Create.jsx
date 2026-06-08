import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { INTEGRATION_FIELDS } from './fields';

export default function IntegrationCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Integration" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Integration</h1>
            <ResourceForm
                action="/admin/integrations"
                method="post"
                fields={INTEGRATION_FIELDS}
                initial={{ system_type: '', system_name: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/integrations"
            />
        </div>
    );
}

IntegrationCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
