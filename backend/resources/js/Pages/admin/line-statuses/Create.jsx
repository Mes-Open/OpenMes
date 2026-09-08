import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { LINE_STATUS_FIELDS, lineStatusInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function LineStatusCreate() {
    const { nextSortOrder = 0 } = usePage().props;

    return (
        <div className="w-full pb-10">
            <Head title={__('New Line Status')} />
            <h1 className="mb-6 text-[26px] font-semibold tracking-[-0.02em] text-om-ink">
                {__('New Line Status')}
            </h1>
            <ResourceForm
                action="/admin/line-statuses"
                method="post"
                fields={LINE_STATUS_FIELDS}
                initial={lineStatusInitial(null, { nextSortOrder })}
                submitLabel={__('Create')}
                cancelHref="/admin/line-statuses"
            />
        </div>
    );
}

LineStatusCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
