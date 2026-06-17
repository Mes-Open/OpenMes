import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { toolFields } from './fields';

export default function ToolCreate() {
    const { workstationTypes = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Tool" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Tool</h1>
            <ResourceForm
                action="/admin/tools"
                method="post"
                fields={toolFields(workstationTypes)}
                initial={{ code: '', name: '', description: '', workstation_type_id: '', status: 'available', next_service_at: '' }}
                submitLabel="Create"
                cancelHref="/admin/tools"
            />
        </div>
    );
}

ToolCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
