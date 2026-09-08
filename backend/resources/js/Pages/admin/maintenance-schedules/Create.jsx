import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { maintenanceScheduleFields, maintenanceScheduleInitial } from './fields';

export default function MaintenanceScheduleCreate() {
    const lists = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Maintenance Schedule" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Maintenance Schedule</h1>
            <ResourceForm
                action="/admin/maintenance-schedules"
                method="post"
                fields={maintenanceScheduleFields(lists)}
                initial={maintenanceScheduleInitial(null)}
                submitLabel="Create"
                cancelHref="/admin/maintenance-schedules"
            />
        </div>
    );
}

MaintenanceScheduleCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
