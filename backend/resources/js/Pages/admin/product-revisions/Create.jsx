import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { productRevisionFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function ProductRevisionCreate({ productTypes = [], processTemplates = [] }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Product Revision')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Product Revision')}</h1>
            <ResourceForm
                action="/admin/product-revisions"
                method="post"
                fields={productRevisionFields(productTypes, processTemplates)}
                initial={{ product_type_id: '', revision_code: '', description: '', process_template_id: '', change_reason: '', external_ref: '' }}
                submitLabel={__('Create')}
                cancelHref="/admin/product-revisions"
            />
        </div>
    );
}

ProductRevisionCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
