import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialFields, materialInitial } from './fields';

export default function MaterialCreate() {
    const { materialTypes = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__("New Material")} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("New Material")}</h1>
            <ResourceForm
                action="/admin/materials"
                method="post"
                fields={materialFields(materialTypes)}
                customFields={customFields}
                initial={materialInitial(null)}
                submitLabel="Create"
                cancelHref="/admin/materials"
            />
        </div>
    );
}

MaterialCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
