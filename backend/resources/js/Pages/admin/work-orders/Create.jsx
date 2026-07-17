import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import WorkOrderForm from './WorkOrderForm';

export default function WorkOrderCreate() {
    const { lines = [], productTypes = [], customers = [], bomTemplates = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__("New Work Order")} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("New Work Order")}</h1>
            <WorkOrderForm lines={lines} productTypes={productTypes} customers={customers} bomTemplates={bomTemplates} customFields={customFields} cancelHref="/admin/work-orders" />
        </div>
    );
}

WorkOrderCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
