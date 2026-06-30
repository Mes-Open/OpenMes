import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { WORKSTATION_TYPE_FIELDS } from './fields';
import { __ } from '../../../lib/i18n';

export default function WorkstationTypeEdit({ workstationType }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Workstation Type: :name', { name: workstationType.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Workstation Type')}</h1>
            <ResourceForm
                action={`/admin/workstation-types/${workstationType.id}`}
                method="put"
                fields={WORKSTATION_TYPE_FIELDS}
                initial={{
                    code: workstationType.code ?? '',
                    name: workstationType.name ?? '',
                    description: workstationType.description ?? '',
                    is_active: !!workstationType.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/workstation-types"
            />
        </div>
    );
}

WorkstationTypeEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
