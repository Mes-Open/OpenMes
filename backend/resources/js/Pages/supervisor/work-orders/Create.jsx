import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import WorkOrderForm from '../../admin/work-orders/WorkOrderForm';
import PageTrail from '../../../components/PageTrail';
import { __ } from '../../../lib/i18n';

// Supervisor-facing create — same form as admin, posting to the /supervisor route.
export default function SupervisorWorkOrderCreate() {
    const { lines = [], productTypes = [], customers = [], bomTemplates = [], productRevisions = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Work Order')} />
            <PageTrail items={[{ label: __('Supervisor'), href: '/supervisor/dashboard', icon: 'layout-dashboard' }, { label: __('Work Orders'), href: '/supervisor/work-orders', icon: 'clipboard-list' }, { label: __('New Work Order') }]} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Work Order')}</h1>
            <WorkOrderForm
                action="/supervisor/work-orders"
                lines={lines}
                productTypes={productTypes}
                customers={customers}
                bomTemplates={bomTemplates}
                productRevisions={productRevisions}
                customFields={customFields}
                cancelHref="/supervisor/work-orders"
            />
        </div>
    );
}

SupervisorWorkOrderCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
