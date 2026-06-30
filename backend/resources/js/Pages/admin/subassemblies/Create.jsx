import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { subassemblyFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function SubassemblyCreate() {
    const { productTypes = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Subassembly')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Subassembly')}</h1>
            <ResourceForm
                action="/admin/subassemblies"
                method="post"
                fields={subassemblyFields(productTypes)}
                initial={{ product_type_id: '', code: '', name: '', description: '', is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/subassemblies"
            />
        </div>
    );
}

SubassemblyCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
