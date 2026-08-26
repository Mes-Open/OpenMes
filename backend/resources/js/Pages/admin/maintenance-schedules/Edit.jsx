import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { maintenanceScheduleFields, maintenanceScheduleInitial } from './fields';

export default function MaintenanceScheduleEdit() {
    const { schedule, preferred_time = '', next_due_at = '', ...lists } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${schedule.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Maintenance Schedule</h1>
            <ResourceForm
                action={`/admin/maintenance-schedules/${schedule.id}`}
                method="put"
                fields={maintenanceScheduleFields(lists)}
                initial={maintenanceScheduleInitial(schedule, { preferred_time, next_due_at })}
                submitLabel="Save Changes"
                cancelHref="/admin/maintenance-schedules"
            />
        </div>
    );
}

MaintenanceScheduleEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
