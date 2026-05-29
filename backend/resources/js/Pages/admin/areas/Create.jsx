import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { areaFields } from './fields';

export default function AreaCreate() {
    const { sites = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Area" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Area</h1>
            <ResourceForm
                action="/admin/areas"
                method="post"
                fields={areaFields(sites)}
                initial={{ site_id: '', code: '', name: '', description: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/areas"
            />
        </div>
    );
}

AreaCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
