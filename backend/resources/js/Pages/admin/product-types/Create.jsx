import { Head } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { PRODUCT_TYPE_FIELDS, productTypeInitial } from './fields';

export default function ProductTypeCreate({ customFields = [] }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__("New Product Type")} />
            <ResourceForm
                title={__("New Product Type")}
                breadcrumbs={[
                    { label: __('Dashboard'), href: '/admin/dashboard' },
                    { label: __('Product Types'), href: '/admin/product-types' },
                    { label: __('New') },
                ]}
                backHref="/admin/product-types"
                action="/admin/product-types"
                method="post"
                fields={PRODUCT_TYPE_FIELDS}
                customFields={customFields}
                initial={productTypeInitial(null)}
                submitLabel={__("Create")}
                cancelHref="/admin/product-types"
            />
        </div>
    );
}

ProductTypeCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
