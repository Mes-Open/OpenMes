import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { ANOMALY_REASON_FIELDS } from './fields';
import { __ } from '../../../lib/i18n';

export default function AnomalyReasonCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Anomaly Reason')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Anomaly Reason')}</h1>
            <ResourceForm
                action="/admin/anomaly-reasons"
                method="post"
                fields={ANOMALY_REASON_FIELDS}
                initial={{ code: '', name: '', category: '', description: '', is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/anomaly-reasons"
            />
        </div>
    );
}

AnomalyReasonCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
