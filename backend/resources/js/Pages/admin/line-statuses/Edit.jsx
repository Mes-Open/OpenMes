import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { LINE_STATUS_FIELDS, lineStatusInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function LineStatusEdit() {
    const { lineStatus } = usePage().props;

    return (
        <div className="w-full pb-10">
            <Head title={`${lineStatus.name} — ${__('Edit')}`} />
            <h1 className="mb-6 text-[26px] font-semibold tracking-[-0.02em] text-om-ink">
                {__('Edit Line Status')}
            </h1>
            <ResourceForm
                action={`/admin/line-statuses/${lineStatus.id}`}
                method="put"
                fields={LINE_STATUS_FIELDS}
                initial={lineStatusInitial(lineStatus)}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/line-statuses"
            />
        </div>
    );
}

LineStatusEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
