import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { companyFields } from './fields';

export default function CompanyCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Company" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Company</h1>
            <ResourceForm
                action="/admin/companies"
                method="post"
                fields={companyFields()}
                initial={{ code: '', name: '', tax_id: '', type: 'supplier', email: '', phone: '', address: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/companies"
            />
        </div>
    );
}

CompanyCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
