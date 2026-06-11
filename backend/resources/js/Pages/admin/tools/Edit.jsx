import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { toolFields } from './fields';

export default function ToolEdit() {
    const { tool, workstationTypes = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${tool.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Tool</h1>
            <ResourceForm
                action={`/admin/tools/${tool.id}`}
                method="put"
                fields={toolFields(workstationTypes)}
                initial={{
                    code: tool.code ?? '',
                    name: tool.name ?? '',
                    description: tool.description ?? '',
                    workstation_type_id: tool.workstation_type_id != null ? String(tool.workstation_type_id) : '',
                    status: tool.status ?? 'available',
                    next_service_at: (tool.next_service_at ?? '').slice(0, 10),
                    custom_fields: tool.custom_fields ?? {},
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/tools"
            />
        </div>
    );
}

ToolEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
