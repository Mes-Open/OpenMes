import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { WORKSTATION_TYPE_FIELDS } from './fields';

export default function WorkstationTypeCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Workstation Type" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Workstation Type</h1>
            <ResourceForm
                action="/admin/workstation-types"
                method="post"
                fields={WORKSTATION_TYPE_FIELDS}
                initial={{ code: '', name: '', description: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/workstation-types"
            />
        </div>
    );
}

WorkstationTypeCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
