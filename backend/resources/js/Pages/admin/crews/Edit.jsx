import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { crewFields } from './fields';

export default function CrewEdit() {
    const { crew, divisions = [], users = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${crew.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Crew</h1>
            <ResourceForm
                action={`/admin/crews/${crew.id}`}
                method="put"
                fields={crewFields(divisions, users)}
                initial={{
                    code: crew.code ?? '',
                    name: crew.name ?? '',
                    division_id: crew.division_id != null ? String(crew.division_id) : '',
                    leader_id: crew.leader_id != null ? String(crew.leader_id) : '',
                    description: crew.description ?? '',
                    is_active: !!crew.is_active,
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/crews"
            />
        </div>
    );
}

CrewEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
