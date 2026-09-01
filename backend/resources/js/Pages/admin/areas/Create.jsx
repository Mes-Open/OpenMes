import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { areaFields, areaInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function AreaCreate() {
    const { sites = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Area')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Area')}</h1>
            <ResourceForm
                action="/admin/areas"
                method="post"
                fields={areaFields(sites)}
                initial={areaInitial(null)}
                submitLabel={__('Create')}
                cancelHref="/admin/areas"
            />
        </div>
    );
}

AreaCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
