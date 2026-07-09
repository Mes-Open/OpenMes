import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { woFields } from './fields';

export default function WorkOrderCreate() {
    const { lines = [], productTypes = [], customers = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__("New Work Order")} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("New Work Order")}</h1>
            <ResourceForm
                action="/admin/work-orders"
                method="post"
                fields={woFields(lines, productTypes, { customers })}
                customFields={customFields}
                initial={{ order_no: '', customer_order_no: '', customer_id: '', line_id: '', product_type_id: '', planned_qty: '', unit_price: '', priority: 0, due_date: '', description: '', custom_fields: {} }}
                submitLabel="Create"
                cancelHref="/admin/work-orders"
            />
        </div>
    );
}

WorkOrderCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
