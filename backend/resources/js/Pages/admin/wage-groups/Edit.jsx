import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { WAGE_GROUP_FIELDS } from './fields';

export default function WageGroupEdit({ wageGroup }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${wageGroup.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Wage Group</h1>
            <ResourceForm
                action={`/admin/wage-groups/${wageGroup.id}`}
                method="put"
                fields={WAGE_GROUP_FIELDS}
                initial={{
                    code: wageGroup.code ?? '',
                    name: wageGroup.name ?? '',
                    description: wageGroup.description ?? '',
                    base_hourly_rate: wageGroup.base_hourly_rate ?? '',
                    currency: wageGroup.currency ?? '',
                    is_active: !!wageGroup.is_active,
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/wage-groups"
            />
        </div>
    );
}

WageGroupEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
