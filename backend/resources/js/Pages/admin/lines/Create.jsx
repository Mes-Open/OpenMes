import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { lineFields } from './fields';

export default function LineCreate() {
    const { areas = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__("New Production Line")} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("New Production Line")}</h1>
            <ResourceForm
                action="/admin/lines"
                method="post"
                fields={lineFields(areas)}
                initial={{ code: '', name: '', area_id: '', description: '', is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/lines"
            />
        </div>
    );
}

LineCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
