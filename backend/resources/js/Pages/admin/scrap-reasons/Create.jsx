import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { SCRAP_REASON_FIELDS } from './fields';

export default function ScrapReasonCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Scrap Reason" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Scrap Reason</h1>
            <ResourceForm
                action="/admin/scrap-reasons"
                method="post"
                fields={SCRAP_REASON_FIELDS}
                initial={{ code: '', name: '', category: '', description: '', sort_order: 0, is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/scrap-reasons"
            />
        </div>
    );
}

ScrapReasonCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
