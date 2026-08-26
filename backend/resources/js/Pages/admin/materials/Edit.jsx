import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialFields, materialInitial } from './fields';

export default function MaterialEdit() {
    const { material, materialTypes = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`${__("Edit")} ${material.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("Edit Material")}</h1>
            <ResourceForm
                action={`/admin/materials/${material.id}`}
                method="put"
                fields={materialFields(materialTypes)}
                customFields={customFields}
                initial={materialInitial(material)}
                submitLabel={__("Save Changes")}
                cancelHref="/admin/materials"
            />
        </div>
    );
}

MaterialEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
