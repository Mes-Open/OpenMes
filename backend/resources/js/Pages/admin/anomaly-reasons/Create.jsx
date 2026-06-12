import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { anomalyReasonFields } from './fields';

export default function AnomalyReasonCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Anomaly Reason" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Anomaly Reason</h1>
            <ResourceForm
                action="/admin/anomaly-reasons"
                method="post"
                fields={anomalyReasonFields()}
                initial={{ code: '', name: '', category: '', description: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/anomaly-reasons"
            />
        </div>
    );
}

AnomalyReasonCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
