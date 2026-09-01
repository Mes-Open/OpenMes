import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { shiftFields, shiftInitial } from './fields';

export default function ShiftEdit() {
    const { shift, lines = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${shift.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Shift</h1>
            <ResourceForm
                action={`/admin/shifts/${shift.id}`}
                method="put"
                fields={shiftFields(lines)}
                initial={shiftInitial(shift)}
                submitLabel="Save Changes"
                cancelHref="/admin/shifts"
            />
        </div>
    );
}

ShiftEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
