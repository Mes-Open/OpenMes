import { Head } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { PRODUCT_TYPE_FIELDS, productTypeInitial } from './fields';

export default function ProductTypeEdit({ productType, customFields = [] }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`${__("Edit")} ${productType.name}`} />
            <ResourceForm
                title={__("Edit Product Type")}
                breadcrumbs={[
                    { label: __('Dashboard'), href: '/admin/dashboard' },
                    { label: __('Product Types'), href: '/admin/product-types' },
                    { label: productType.name, href: `/admin/product-types/${productType.id}` },
                    { label: __('Edit') },
                ]}
                backHref="/admin/product-types"
                action={`/admin/product-types/${productType.id}`}
                method="put"
                fields={PRODUCT_TYPE_FIELDS}
                customFields={customFields}
                initial={productTypeInitial(productType)}
                submitLabel={__("Save Changes")}
                cancelHref="/admin/product-types"
            />
        </div>
    );
}

ProductTypeEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
