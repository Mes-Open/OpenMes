import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { woFields } from './fields';

export default function WorkOrderEdit() {
    const { workOrder, lines = [], productTypes = [], customers = [], bomTemplates = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${workOrder.order_no}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("Edit Work Order")}</h1>
            <ResourceForm
                action={`/admin/work-orders/${workOrder.id}`}
                method="put"
                fields={woFields(lines, productTypes, { withStatus: true, customers, bomTemplates, bomLocked: workOrder.bom_locked })}
                customFields={customFields}
                initial={{
                    order_no: workOrder.order_no ?? '',
                    customer_order_no: workOrder.customer_order_no ?? '',
                    customer_id: workOrder.customer_id != null ? String(workOrder.customer_id) : '',
                    line_id: workOrder.line_id != null ? String(workOrder.line_id) : '',
                    product_type_id: workOrder.product_type_id != null ? String(workOrder.product_type_id) : '',
                    bom_template_ids: workOrder.bom_template_ids ?? [],
                    planned_qty: workOrder.planned_qty ?? '',
                    unit_price: workOrder.unit_price ?? '',
                    priority: workOrder.priority ?? 0,
                    due_date: workOrder.due_date ?? '',
                    description: workOrder.description ?? '',
                    status: workOrder.status ?? 'PENDING',
                    custom_fields: workOrder.custom_fields ?? {},
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/work-orders"
            />
        </div>
    );
}

WorkOrderEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
