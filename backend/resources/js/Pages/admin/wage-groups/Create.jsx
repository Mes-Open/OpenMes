import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { wageGroupFields, wageGroupInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function WageGroupCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Wage Group')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Wage Group')}</h1>
            <ResourceForm
                action="/admin/wage-groups"
                method="post"
                fields={wageGroupFields()}
                initial={wageGroupInitial(null)}
                submitLabel={__('Create')}
                cancelHref="/admin/wage-groups"
            />
        </div>
    );
}

WageGroupCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
