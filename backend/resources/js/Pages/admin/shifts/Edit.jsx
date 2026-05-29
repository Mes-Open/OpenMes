import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { shiftFields } from './fields';

export default function ShiftEdit() {
    const { shift, lines = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${shift.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Shift</h1>
            <ResourceForm
                action={`/admin/shifts/${shift.id}`}
                method="put"
                fields={shiftFields(lines)}
                initial={{
                    code: shift.code ?? '',
                    name: shift.name ?? '',
                    line_id: shift.line_id != null ? String(shift.line_id) : '',
                    start_time: (shift.start_time ?? '').slice(0, 5),
                    end_time: (shift.end_time ?? '').slice(0, 5),
                    sort_order: shift.sort_order ?? 0,
                    is_active: !!shift.is_active,
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/shifts"
            />
        </div>
    );
}

ShiftEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
