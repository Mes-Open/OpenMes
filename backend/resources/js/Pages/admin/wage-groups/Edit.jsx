import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { wageGroupFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function WageGroupEdit({ wageGroup }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: wageGroup.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Wage Group')}</h1>
            <ResourceForm
                action={`/admin/wage-groups/${wageGroup.id}`}
                method="put"
                fields={wageGroupFields()}
                initial={{
                    code: wageGroup.code ?? '',
                    name: wageGroup.name ?? '',
                    description: wageGroup.description ?? '',
                    base_hourly_rate: wageGroup.base_hourly_rate ?? '',
                    currency: wageGroup.currency ?? '',
                    is_active: !!wageGroup.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/wage-groups"
            />
        </div>
    );
}

WageGroupEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
