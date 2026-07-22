import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { productRevisionFields } from './fields';
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
                initial={{
                    product_type_id: revision.product_type_id != null ? String(revision.product_type_id) : '',
                    revision_code: revision.revision_code ?? '',
                    description: revision.description ?? '',
                    process_template_id: revision.process_template_id != null ? String(revision.process_template_id) : '',
                    change_reason: revision.change_reason ?? '',
                    external_ref: revision.external_ref ?? '',
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/product-revisions"
            />
        </div>
    );
}

ProductRevisionEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
