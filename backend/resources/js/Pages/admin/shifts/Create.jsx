import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { shiftFields } from './fields';

export default function ShiftCreate() {
    const { lines = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Shift" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Shift</h1>
            <ResourceForm
                action="/admin/shifts"
                method="post"
                fields={shiftFields(lines)}
                initial={{ code: '', name: '', line_id: '', start_time: '', end_time: '', sort_order: 0, is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/shifts"
            />
        </div>
    );
}

ShiftCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
