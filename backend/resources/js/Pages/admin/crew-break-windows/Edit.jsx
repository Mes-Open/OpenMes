import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { crewBreakWindowFields, crewBreakWindowInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function CrewBreakWindowEdit({ window }) {
    const { crews = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Break Window')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Break Window')}</h1>
            <ResourceForm
                action={`/admin/crew-break-windows/${window.id}`}
                method="put"
                fields={crewBreakWindowFields(crews)}
                initial={crewBreakWindowInitial(window)}
                submitLabel="Save Changes"
                cancelHref="/admin/crew-break-windows"
            />
        </div>
    );
}

CrewBreakWindowEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
