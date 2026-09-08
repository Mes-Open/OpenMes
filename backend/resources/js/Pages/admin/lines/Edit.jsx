import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { lineFields, lineInitial } from './fields';

export default function LineEdit() {
    const { line, areas = [], warehouses = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${line.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("Edit Production Line")}</h1>
            <ResourceForm
                action={`/admin/lines/${line.id}`}
                method="put"
                fields={lineFields(areas, warehouses)}
                initial={lineInitial(line)}
                submitLabel="Save Changes"
                cancelHref="/admin/lines"
            />
        </div>
    );
}

LineEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
