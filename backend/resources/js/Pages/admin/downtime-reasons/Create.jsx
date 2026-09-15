import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { downtimeReasonFields, downtimeReasonInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function DowntimeReasonCreate() {
    const { kinds = [] } = usePage().props;

    return (
        <>
            <Head title={__('New Downtime Reason')} />
            <ResourceForm
                title={__('New Downtime Reason')}
                action="/admin/downtime-reasons"
                fields={downtimeReasonFields(kinds)}
                initial={downtimeReasonInitial()}
                cancelHref="/admin/downtime-reasons"
            />
        </>
    );
}

DowntimeReasonCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
