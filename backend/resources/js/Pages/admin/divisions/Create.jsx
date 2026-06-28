import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { divisionFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function DivisionCreate() {
    const { factories = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Division')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Division')}</h1>
            <ResourceForm
                action="/admin/divisions"
                method="post"
                fields={divisionFields(factories)}
                initial={{ factory_id: '', code: '', name: '', description: '', is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/divisions"
            />
        </div>
    );
}

DivisionCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
