import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { absenceFields } from './fields';

export default function WorkerAbsenceEdit({ absence }) {
    const { workers = [], types = [], statuses = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="Edit Absence" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Absence</h1>
            <ResourceForm
                action={`/admin/worker-absences/${absence.id}`}
                method="put"
                fields={absenceFields(workers, types, statuses)}
                initial={{
                    worker_id: absence.worker_id ? String(absence.worker_id) : '',
                    type: absence.type ?? 'vacation',
                    starts_on: absence.starts_on ?? '',
                    ends_on: absence.ends_on ?? '',
                    all_day: !!absence.all_day,
                    start_time: absence.start_time ?? '',
                    end_time: absence.end_time ?? '',
                    status: absence.status ?? 'approved',
                    reason: absence.reason ?? '',
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/worker-absences"
            />
        </div>
    );
}

WorkerAbsenceEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
