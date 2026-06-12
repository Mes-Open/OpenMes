import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { companyFields } from './fields';

export default function CompanyEdit({ company }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${company.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Company</h1>
            <ResourceForm
                action={`/admin/companies/${company.id}`}
                method="put"
                fields={companyFields()}
                initial={{
                    code: company.code ?? '',
                    name: company.name ?? '',
                    tax_id: company.tax_id ?? '',
                    type: company.type ?? 'supplier',
                    email: company.email ?? '',
                    phone: company.phone ?? '',
                    address: company.address ?? '',
                    is_active: !!company.is_active,
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/companies"
            />
        </div>
    );
}

CompanyEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
