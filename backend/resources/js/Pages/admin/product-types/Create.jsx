import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { PRODUCT_TYPE_FIELDS } from './fields';

export default function ProductTypeCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Product Type" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Product Type</h1>
            <ResourceForm
                action="/admin/product-types"
                method="post"
                fields={PRODUCT_TYPE_FIELDS}
                initial={{ code: '', name: '', description: '', unit_of_measure: 'pcs', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/product-types"
            />
        </div>
    );
}

ProductTypeCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
