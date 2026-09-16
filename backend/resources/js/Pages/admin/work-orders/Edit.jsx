import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { woFields } from './fields';

import { workOrderInitial } from './workOrderInitial';

export default function WorkOrderEdit() {
    const { workOrder, lines = [], productTypes = [], customers = [], bomTemplates = [], productRevisions = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${workOrder.order_no}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("Edit Work Order")}</h1>
            <ResourceForm
                action={`/admin/work-orders/${workOrder.id}`}
                method="put"
                fields={woFields(lines, productTypes, { withStatus: true, customers, bomTemplates, bomLocked: workOrder.bom_locked, productRevisions })}
                customFields={customFields}
                initial={workOrderInitial(workOrder)}
                submitLabel="Save Changes"
                cancelHref="/admin/work-orders"
            />
        </div>
    );
}

WorkOrderEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
