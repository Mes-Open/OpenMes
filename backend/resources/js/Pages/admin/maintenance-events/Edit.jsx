import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { maintenanceEventFields, maintenanceEventInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function MaintenanceEventEdit() {
    const { event, scheduled_at = '', scheduled_end_at = '', ...lists } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: event.title })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Maintenance Event')}</h1>
            <p className="text-sm text-om-muted mb-4 max-w-2xl">
                {__('Select at least one of Tool, Line, or Workstation.')}
            </p>
            <ResourceForm
                action={`/admin/maintenance-events/${event.id}`}
                method="put"
                fields={maintenanceEventFields(lists)}
                initial={maintenanceEventInitial(event, { scheduled_at, scheduled_end_at })}
                submitLabel="Save Changes"
                cancelHref="/admin/maintenance-events"
            />
        </div>
    );
}

MaintenanceEventEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
