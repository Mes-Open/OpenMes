import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { woFields } from './fields';

export default function WorkOrderEdit() {
    const { workOrder, lines = [], productTypes = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${workOrder.order_no}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Work Order</h1>
            <ResourceForm
                action={`/admin/work-orders/${workOrder.id}`}
                method="put"
                fields={woFields(lines, productTypes, { withStatus: true })}
                initial={{
                    order_no: workOrder.order_no ?? '',
                    line_id: workOrder.line_id != null ? String(workOrder.line_id) : '',
                    product_type_id: workOrder.product_type_id != null ? String(workOrder.product_type_id) : '',
                    planned_qty: workOrder.planned_qty ?? '',
                    priority: workOrder.priority ?? 0,
                    due_date: workOrder.due_date ?? '',
                    description: workOrder.description ?? '',
                    status: workOrder.status ?? 'PENDING',
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/work-orders"
            />
        </div>
    );
}

WorkOrderEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
