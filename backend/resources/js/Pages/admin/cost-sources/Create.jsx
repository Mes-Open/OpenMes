import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { COST_SOURCE_FIELDS } from './fields';

export default function CostSourceCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Cost Source" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Cost Source</h1>
            <ResourceForm
                action="/admin/cost-sources"
                method="post"
                fields={COST_SOURCE_FIELDS}
                initial={{ code: '', name: '', description: '', unit_cost: '', unit: '', currency: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/cost-sources"
            />
        </div>
    );
}

CostSourceCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
