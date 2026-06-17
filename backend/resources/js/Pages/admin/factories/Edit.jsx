import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { FACTORY_FIELDS } from './fields';
import { __ } from '../../../lib/i18n';

export default function FactoryEdit({ factory }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: factory.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Factory')}</h1>
            <ResourceForm
                action={`/admin/factories/${factory.id}`}
                method="put"
                fields={FACTORY_FIELDS}
                initial={{
                    code: factory.code ?? '',
                    name: factory.name ?? '',
                    description: factory.description ?? '',
                    is_active: !!factory.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/factories"
            />
        </div>
    );
}

FactoryEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
