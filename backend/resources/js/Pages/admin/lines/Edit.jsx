import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { lineFields } from './fields';

export default function LineEdit() {
    const { line, areas = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${line.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Production Line</h1>
            <ResourceForm
                action={`/admin/lines/${line.id}`}
                method="put"
                fields={lineFields(areas)}
                initial={{
                    code: line.code ?? '',
                    name: line.name ?? '',
                    area_id: line.area_id != null ? String(line.area_id) : '',
                    description: line.description ?? '',
                    is_active: !!line.is_active,
                    custom_fields: line.custom_fields ?? {},
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/lines"
            />
        </div>
    );
}

LineEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
