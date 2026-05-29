import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { PRODUCT_TYPE_FIELDS } from './fields';

export default function ProductTypeEdit({ productType }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${productType.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Product Type</h1>
            <ResourceForm
                action={`/admin/product-types/${productType.id}`}
                method="put"
                fields={PRODUCT_TYPE_FIELDS}
                initial={{
                    code: productType.code ?? '',
                    name: productType.name ?? '',
                    description: productType.description ?? '',
                    unit_of_measure: productType.unit_of_measure ?? 'pcs',
                    is_active: !!productType.is_active,
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/product-types"
            />
        </div>
    );
}

ProductTypeEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
