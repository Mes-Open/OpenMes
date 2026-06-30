import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { subassemblyFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function SubassemblyEdit() {
    const { subassembly, productTypes = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Subassembly: :name', { name: subassembly.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Subassembly')}</h1>
            <ResourceForm
                action={`/admin/subassemblies/${subassembly.id}`}
                method="put"
                fields={subassemblyFields(productTypes)}
                initial={{
                    product_type_id: subassembly.product_type_id != null ? String(subassembly.product_type_id) : '',
                    code: subassembly.code ?? '',
                    name: subassembly.name ?? '',
                    description: subassembly.description ?? '',
                    is_active: !!subassembly.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/subassemblies"
            />
        </div>
    );
}

SubassemblyEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
