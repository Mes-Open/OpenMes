import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { subassemblyFields } from './fields';

export default function SubassemblyCreate() {
    const { productTypes = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Subassembly" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Subassembly</h1>
            <ResourceForm
                action="/admin/subassemblies"
                method="post"
                fields={subassemblyFields(productTypes)}
                initial={{ product_type_id: '', code: '', name: '', description: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/subassemblies"
            />
        </div>
    );
}

SubassemblyCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
