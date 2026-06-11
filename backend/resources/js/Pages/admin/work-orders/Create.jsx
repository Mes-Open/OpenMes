import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { woFields } from './fields';

export default function WorkOrderCreate() {
    const { lines = [], productTypes = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Work Order" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Work Order</h1>
            <ResourceForm
                action="/admin/work-orders"
                method="post"
                fields={woFields(lines, productTypes)}
                customFields={customFields}
                initial={{ order_no: '', line_id: '', product_type_id: '', planned_qty: '', priority: 0, due_date: '', description: '', custom_fields: {} }}
                submitLabel="Create"
                cancelHref="/admin/work-orders"
            />
        </div>
    );
}

WorkOrderCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
