import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { WAGE_GROUP_FIELDS } from './fields';

export default function WageGroupCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Wage Group" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Wage Group</h1>
            <ResourceForm
                action="/admin/wage-groups"
                method="post"
                fields={WAGE_GROUP_FIELDS}
                initial={{ code: '', name: '', description: '', base_hourly_rate: '', currency: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/wage-groups"
            />
        </div>
    );
}

WageGroupCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
