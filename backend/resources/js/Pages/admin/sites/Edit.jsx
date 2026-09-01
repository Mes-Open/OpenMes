import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { siteFields, siteInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function SiteEdit() {
    const { site, companies = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: site.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Site')}</h1>
            <ResourceForm
                action={`/admin/sites/${site.id}`}
                method="put"
                fields={siteFields(companies)}
                initial={siteInitial(site)}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/sites"
            />
        </div>
    );
}

SiteEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
