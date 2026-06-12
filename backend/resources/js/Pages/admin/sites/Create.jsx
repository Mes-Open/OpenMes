import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { siteFields } from './fields';

export default function SiteCreate() {
    const { companies = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Site" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Site</h1>
            <ResourceForm
                action="/admin/sites"
                method="post"
                fields={siteFields(companies)}
                initial={{ company_id: '', code: '', name: '', description: '', address: '', city: '', country: '', timezone: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/sites"
            />
        </div>
    );
}

SiteCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
