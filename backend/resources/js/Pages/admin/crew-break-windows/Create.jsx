import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { crewBreakWindowFields, crewBreakWindowInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function CrewBreakWindowCreate() {
    const { crews = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Break Window')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Break Window')}</h1>
            <ResourceForm
                action="/admin/crew-break-windows"
                method="post"
                fields={crewBreakWindowFields(crews)}
                initial={crewBreakWindowInitial(null)}
                submitLabel="Create"
                cancelHref="/admin/crew-break-windows"
            />
        </div>
    );
}

CrewBreakWindowCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
