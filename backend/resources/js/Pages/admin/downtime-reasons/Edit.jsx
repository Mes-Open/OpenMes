import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { downtimeReasonFields, downtimeReasonInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function DowntimeReasonEdit() {
    const { downtimeReason, kinds = [] } = usePage().props;

    return (
        <>
            <Head title={__('Edit Downtime Reason')} />
            <ResourceForm
                title={__('Edit Downtime Reason')}
                action={`/admin/downtime-reasons/${downtimeReason.id}`}
                method="put"
                fields={downtimeReasonFields(kinds)}
                initial={downtimeReasonInitial(downtimeReason)}
                cancelHref="/admin/downtime-reasons"
            />
        </>
    );
}

DowntimeReasonEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
