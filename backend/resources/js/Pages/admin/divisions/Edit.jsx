import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { divisionFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function DivisionEdit() {
    const { division, factories = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Division: :name', { name: division.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Division')}</h1>
            <ResourceForm
                action={`/admin/divisions/${division.id}`}
                method="put"
                fields={divisionFields(factories)}
                initial={{
                    factory_id: division.factory_id != null ? String(division.factory_id) : '',
                    code: division.code ?? '',
                    name: division.name ?? '',
                    description: division.description ?? '',
                    is_active: !!division.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/divisions"
            />
        </div>
    );
}

DivisionEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
