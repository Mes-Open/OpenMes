import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { absenceFields, absenceInitial } from './fields';

export default function WorkerAbsenceCreate() {
    const { workers = [], types = [], statuses = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Absence" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Absence</h1>
            <ResourceForm
                action="/admin/worker-absences"
                method="post"
                fields={absenceFields(workers, types, statuses)}
                initial={absenceInitial(null)}
                submitLabel="Create"
                cancelHref="/admin/worker-absences"
            />
        </div>
    );
}

WorkerAbsenceCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
