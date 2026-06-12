import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { maintenanceEventFields } from './fields';

export default function MaintenanceEventCreate() {
    const lists = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Maintenance Event" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Maintenance Event</h1>
            <p className="text-sm text-om-muted mb-4 max-w-2xl">
                Select at least one of Tool, Line, or Workstation.
            </p>
            <ResourceForm
                action="/admin/maintenance-events"
                method="post"
                fields={maintenanceEventFields(lists)}
                initial={{
                    title: '',
                    event_type: 'planned',
                    tool_id: '',
                    line_id: '',
                    workstation_id: '',
                    cost_source_id: '',
                    assigned_to_id: '',
                    scheduled_at: '',
                    scheduled_end_at: '',
                    actual_cost: '',
                    currency: 'PLN',
                    description: '',
                }}
                submitLabel="Create"
                cancelHref="/admin/maintenance-events"
            />
        </div>
    );
}

MaintenanceEventCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
