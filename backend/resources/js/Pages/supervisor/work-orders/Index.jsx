import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import usePrompt from '../../../components/usePrompt';
import { woColumns } from '../../admin/work-orders/columns';
import { __ } from '../../../lib/i18n';

const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

export default function SupervisorWorkOrdersIndex() {
    const { prompt, dialog: promptDialog } = usePrompt();
    const { counts = {}, lineNames = {}, productTypeNames = {} } = usePage().props;

    const post = (id, verb, data = {}) => router.post(`/supervisor/work-orders/${id}/${verb}`, data, { preserveScroll: true });

    const columns = woColumns({ lineNames, productTypeNames, counts });

    const actions = (r) => {
        const a = [{ label: 'Edit', icon: 'edit', href: `/supervisor/work-orders/${r.id}/edit` }];
        const s = r.status;

        if (s === 'PENDING') {
            a.push({ label: 'Accept', onClick: () => post(r.id, 'accept') });
            a.push({ label: 'Reject', onClick: () => post(r.id, 'reject') });
        } else if (s === 'ACCEPTED') {
            a.push({ label: 'Reject', onClick: () => post(r.id, 'reject') });
        } else if (s === 'IN_PROGRESS') {
            a.push({ label: 'Pause', onClick: () => post(r.id, 'pause') });
            a.push({
                label: 'Complete',
                onClick: () => prompt(
                    {
                        title: __('Complete'),
                        label: __('Produced quantity'),
                        defaultValue: r.planned_qty,
                        type: 'number',
                        min: 0,
                        confirmLabel: __('Complete'),
                    },
                    (qty) => post(r.id, 'complete', { produced_qty: qty }),
                ),
            });
        } else if (s === 'PAUSED') {
            a.push({ label: 'Resume', onClick: () => post(r.id, 'resume') });
        }

        if (TERMINAL.includes(s)) {
            a.push({ label: 'Reopen', onClick: () => post(r.id, 'reopen') });
        } else {
            a.push({
                label: 'Cancel',
                variant: 'warning',
                confirm: {
                    title: __('Cancel work order :order?', { order: r.order_no }),
                    confirmLabel: __('Cancel work order'),
                },
                onClick: () => post(r.id, 'cancel'),
            });
        }

        return a;
    };

    return (
        <>
            <Head title={__('Work Orders')} />
            <ResourceTable
                shape="work_orders_all"
                detailHref={(r) => `/supervisor/work-orders/${r.id}`}
                title={__('Work Orders')}
                createHref="/supervisor/work-orders/create"
                createLabel="+ New Work Order"
                columns={columns}
                orderBy="order_no"
                actions={actions}
                emptyText={__('No work orders yet.')}
            />
            {promptDialog}
        </>
    );
}

SupervisorWorkOrdersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
