import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { siteFields } from './fields';

export default function SiteEdit() {
    const { site, companies = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${site.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Site</h1>
            <ResourceForm
                action={`/admin/sites/${site.id}`}
                method="put"
                fields={siteFields(companies)}
                initial={{
                    company_id: site.company_id != null ? String(site.company_id) : '',
                    code: site.code ?? '',
                    name: site.name ?? '',
                    description: site.description ?? '',
                    address: site.address ?? '',
                    city: site.city ?? '',
                    country: site.country ?? '',
                    timezone: site.timezone ?? '',
                    is_active: !!site.is_active,
                    custom_fields: site.custom_fields ?? {},
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/sites"
            />
        </div>
    );
}

SiteEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
