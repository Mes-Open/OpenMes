import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { factoryFields } from './fields';

export default function FactoryCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Factory" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Factory</h1>
            <ResourceForm
                action="/admin/factories"
                method="post"
                fields={factoryFields()}
                initial={{ code: '', name: '', description: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/factories"
            />
        </div>
    );
}

FactoryCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
