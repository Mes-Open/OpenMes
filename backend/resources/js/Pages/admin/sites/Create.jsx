import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { siteFields, siteInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function SiteCreate() {
    const { companies = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Site')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Site')}</h1>
            <ResourceForm
                action="/admin/sites"
                method="post"
                fields={siteFields(companies)}
                initial={siteInitial(null)}
                submitLabel={__('Create')}
                cancelHref="/admin/sites"
            />
        </div>
    );
}

SiteCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
