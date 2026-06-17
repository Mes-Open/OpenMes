import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { FACTORY_FIELDS } from './fields';
import { __ } from '../../../lib/i18n';

export default function FactoryCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Factory')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Factory')}</h1>
            <ResourceForm
                action="/admin/factories"
                method="post"
                fields={FACTORY_FIELDS}
                initial={{ code: '', name: '', description: '', is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/factories"
            />
        </div>
    );
}

FactoryCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
