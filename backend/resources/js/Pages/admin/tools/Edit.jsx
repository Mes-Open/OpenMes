import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { toolFields, toolInitial } from './fields';

export default function ToolEdit() {
    const { tool, workstationTypes = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${tool.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Tool</h1>
            <ResourceForm
                action={`/admin/tools/${tool.id}`}
                method="put"
                fields={toolFields(workstationTypes)}
                initial={toolInitial(tool)}
                submitLabel="Save Changes"
                cancelHref="/admin/tools"
            />
        </div>
    );
}

ToolEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
