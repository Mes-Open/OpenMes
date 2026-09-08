import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { crewFields, crewInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function CrewCreate() {
    const { divisions = [], users = [], lines = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Crew')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Crew')}</h1>
            <ResourceForm
                action="/admin/crews"
                method="post"
                fields={crewFields(divisions, users, lines)}
                initial={crewInitial(null)}
                submitLabel={__('Create')}
                cancelHref="/admin/crews"
            />
        </div>
    );
}

CrewCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
