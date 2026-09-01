import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { absenceFields, absenceInitial } from './fields';

export default function WorkerAbsenceEdit({ absence }) {
    const { workers = [], types = [], statuses = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="Edit Absence" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Absence</h1>
            <ResourceForm
                action={`/admin/worker-absences/${absence.id}`}
                method="put"
                fields={absenceFields(workers, types, statuses)}
                initial={absenceInitial(absence)}
                submitLabel="Save Changes"
                cancelHref="/admin/worker-absences"
            />
        </div>
    );
}

WorkerAbsenceEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
