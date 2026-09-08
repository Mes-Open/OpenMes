import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { COMPANY_FIELDS, companyInitial } from './fields';

export default function CompanyCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Company" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Company</h1>
            <ResourceForm
                action="/admin/companies"
                method="post"
                fields={COMPANY_FIELDS}
                initial={companyInitial(null)}
                submitLabel="Create"
                cancelHref="/admin/companies"
            />
        </div>
    );
}

CompanyCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
