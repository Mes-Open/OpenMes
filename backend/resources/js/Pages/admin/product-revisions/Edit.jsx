import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { productRevisionFields, productRevisionInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function ProductRevisionEdit({ revision, productTypes = [], processTemplates = [] }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Product Revision')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Product Revision')}</h1>
            <ResourceForm
                action={`/admin/product-revisions/${revision.id}`}
                method="put"
                fields={productRevisionFields(productTypes, processTemplates, { lockProductType: true })}
                initial={productRevisionInitial(revision)}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/product-revisions"
            />
        </div>
    );
}

ProductRevisionEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
