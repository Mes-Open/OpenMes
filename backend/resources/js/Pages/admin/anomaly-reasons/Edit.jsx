import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { ANOMALY_REASON_FIELDS, anomalyReasonInitial } from './fields';

export default function AnomalyReasonEdit({ anomalyReason }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${anomalyReason.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Anomaly Reason</h1>
            <ResourceForm
                action={`/admin/anomaly-reasons/${anomalyReason.id}`}
                method="put"
                fields={ANOMALY_REASON_FIELDS}
                initial={anomalyReasonInitial(anomalyReason)}
                submitLabel="Save Changes"
                cancelHref="/admin/anomaly-reasons"
            />
        </div>
    );
}

AnomalyReasonEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
