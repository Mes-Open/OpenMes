import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialTypeFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function MaterialTypeEdit({ materialType }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: materialType.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Material Type')}</h1>
            <ResourceForm
                action={`/admin/material-types/${materialType.id}`}
                method="put"
                fields={materialTypeFields()}
                initial={{
                    code: materialType.code ?? '',
                    name: materialType.name ?? '',
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/material-types"
            />
        </div>
    );
}

MaterialTypeEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
