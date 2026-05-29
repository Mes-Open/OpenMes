import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { maintenanceEventFields } from './fields';

export default function MaintenanceEventEdit() {
    const { event, scheduled_at = '', scheduled_end_at = '', ...lists } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${event.title}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Maintenance Event</h1>
            <p className="text-sm text-gray-500 mb-4 max-w-2xl">
                Select at least one of Tool, Line, or Workstation.
            </p>
            <ResourceForm
                action={`/admin/maintenance-events/${event.id}`}
                method="put"
                fields={maintenanceEventFields(lists)}
                initial={{
                    title: event.title ?? '',
                    event_type: event.event_type ?? 'planned',
                    tool_id: event.tool_id != null ? String(event.tool_id) : '',
                    line_id: event.line_id != null ? String(event.line_id) : '',
                    workstation_id: event.workstation_id != null ? String(event.workstation_id) : '',
                    cost_source_id: event.cost_source_id != null ? String(event.cost_source_id) : '',
                    assigned_to_id: event.assigned_to_id != null ? String(event.assigned_to_id) : '',
                    scheduled_at: scheduled_at ?? '',
                    scheduled_end_at: scheduled_end_at ?? '',
                    actual_cost: event.actual_cost ?? '',
                    currency: event.currency ?? '',
                    description: event.description ?? '',
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/maintenance-events"
            />
        </div>
    );
}

MaintenanceEventEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
