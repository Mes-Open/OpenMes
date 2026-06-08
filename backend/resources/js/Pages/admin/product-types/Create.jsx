import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { PRODUCT_TYPE_FIELDS } from './fields';

export default function ProductTypeCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Product Type" />
            <ResourceForm
                title="New Product Type"
                breadcrumbs={[
                    { label: 'Dashboard', href: '/admin/dashboard' },
                    { label: 'Product Types', href: '/admin/product-types' },
                    { label: 'New' },
                ]}
                backHref="/admin/product-types"
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
