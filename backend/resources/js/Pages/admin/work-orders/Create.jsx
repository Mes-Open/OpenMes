import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import WorkOrderForm from './WorkOrderForm';

export default function WorkOrderCreate() {
    const { lines = [], productTypes = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Work Order" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Work Order</h1>
            <WorkOrderForm lines={lines} productTypes={productTypes} customFields={customFields} cancelHref="/admin/work-orders" />
        </div>
    );
}

WorkOrderCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
