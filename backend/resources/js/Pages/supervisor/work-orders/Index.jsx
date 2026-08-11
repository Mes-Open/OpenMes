import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Modal } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import usePrompt from '../../../components/usePrompt';
import WorkOrderForm from '../../admin/work-orders/WorkOrderForm';
import { woColumns } from '../../admin/work-orders/columns';
import { __ } from '../../../lib/i18n';

const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

export default function SupervisorWorkOrdersIndex() {
    const {
        counts = {}, lineNames = {}, productTypeNames = {}, customerNames = {}, can = {},
        // Create-form options, same props the standalone create page receives.
        lines = [], productTypes = [], customers = [], bomTemplates = [], productRevisions = [], customFields = [],
    } = usePage().props;

    // Creating from the list happens in a modal so you keep your filters, page and
    // selection. /supervisor/work-orders/create still renders the same form standalone.
    const [creating, setCreating] = useState(false);
    // Bumped after a successful create to remount the form — see the modal below.
    const [formKey, setFormKey] = useState(0);
    const { prompt, dialog: promptDialog } = usePrompt();

    const post = (id, verb, data = {}) => router.post(`/supervisor/work-orders/${id}/${verb}`, data, { preserveScroll: true });

    // Bulk transitions over the checkbox selection. The backend skips orders the
    // action doesn't apply to (a selection spanning mixed statuses is normal) and
    // reports how many it skipped, so there's nothing to pre-filter here.
    const bulk = (action, rows, clear) => {
        router.post(
            '/supervisor/work-orders/bulk',
            { action, ids: rows.map((r) => r.id) },
            { preserveScroll: true, onSuccess: clear },
        );
    };

    const BULK_VERBS = [
        { key: 'accept', label: __('Accept'), from: ['PENDING'], title: 'Accept :count selected work order(s)?', confirmLabel: __('Accept orders') },
        { key: 'reject', label: __('Reject'), from: ['PENDING', 'ACCEPTED'], title: 'Reject :count selected work order(s)?', confirmLabel: __('Reject orders') },
        { key: 'pause', label: __('Pause'), from: ['IN_PROGRESS'], title: 'Pause :count selected work order(s)?', confirmLabel: __('Pause orders') },
        { key: 'resume', label: __('Resume'), from: ['PAUSED'], title: 'Resume :count selected work order(s)?', confirmLabel: __('Resume orders') },
        { key: 'reopen', label: __('Reopen'), from: TERMINAL, title: 'Reopen :count selected work order(s)?', confirmLabel: __('Reopen orders') },
        {
            key: 'cancel', label: __('Cancel'), variant: 'warning',
            from: ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'PAUSED', 'BLOCKED'],
            title: 'Cancel :count selected work order(s)?', confirmLabel: __('Cancel orders'),
            body: __('Cancelled orders stop production and can be reopened later.'),
        },
    ];

    const bulkActionItems = BULK_VERBS.map((v) => ({
        key: v.key,
        label: v.label,
        variant: v.variant,
        applies: (rows) => rows.some((r) => v.from.includes(r.status)),
        confirm: (rows) => ({
            title: __(v.title, { count: rows.length }),
            body: v.body ?? __("Orders this action doesn't apply to are skipped."),
            confirmLabel: v.confirmLabel,
        }),
        onClick: (rows, clear) => bulk(v.key, rows, clear),
    }));

    const columns = woColumns({ lineNames, productTypeNames, counts, customerNames, withScore: true });

    // Asking for a produced quantity is what separates Complete from the other
    // verbs: it can't be applied blind, so it never joins the bulk bar.
    const promptComplete = (r) => prompt(
        {
            title: __('Complete'),
            label: __('Produced quantity'),
            defaultValue: r.planned_qty,
            type: 'number',
            min: 0,
            confirmLabel: __('Complete'),
        },
        (qty) => post(r.id, 'complete', { produced_qty: qty }),
    );

    /**
     * The one action a row is actually waiting for, by status — same mapping the
     * admin list uses, so a supervisor and a planner see the same next step on the
     * same order. ACCEPTED and BLOCKED have no entry on purpose: an accepted order
     * is started on the shop floor, and nothing here unblocks.
     */
    const PRIMARY = {
        PENDING: (r) => ({ label: __('Accept'), icon: 'accept', onClick: () => post(r.id, 'accept') }),
        IN_PROGRESS: (r) => ({ label: __('Complete'), icon: 'complete', onClick: () => promptComplete(r) }),
        PAUSED: (r) => ({ label: __('Resume'), icon: 'resume', onClick: () => post(r.id, 'resume') }),
        DONE: (r) => ({ label: __('Reopen'), icon: 'reopen', onClick: () => post(r.id, 'reopen') }),
        REJECTED: (r) => ({ label: __('Reopen'), icon: 'reopen', onClick: () => post(r.id, 'reopen') }),
        CANCELLED: (r) => ({ label: __('Reopen'), icon: 'reopen', onClick: () => post(r.id, 'reopen') }),
    };

    /** Every verb this row could take, minus the one already on the button. */
    const secondaryVerbs = (r) => {
        const primary = PRIMARY[r.status]?.(r)?.label;
        return [
            { key: 'accept', label: __('Accept'), from: ['PENDING'] },
            { key: 'reject', label: __('Reject'), from: ['PENDING', 'ACCEPTED'] },
            { key: 'complete', label: __('Complete'), from: ['IN_PROGRESS'], run: () => promptComplete(r) },
            { key: 'pause', label: __('Pause'), from: ['IN_PROGRESS'] },
            { key: 'resume', label: __('Resume'), from: ['PAUSED'] },
            { key: 'reopen', label: __('Reopen'), from: TERMINAL },
            { key: 'cancel', label: __('Cancel'), from: ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'PAUSED', 'BLOCKED'] },
        ]
            .filter((v) => v.from.includes(r.status) && v.label !== primary)
            // The verb key doubles as the icon name — same glyph as the rail.
            .map((v) => ({ key: v.key, label: v.label, icon: v.key, onSelect: v.run ?? (() => post(r.id, v.key)) }));
    };

    // Two slots: the row's next step, then everything else behind a menu.
    //
    // Delete is the one item that isn't unconditional: the shipped Supervisor
    // role has no `delete work orders` ability, so the controller reports whether
    // the current user holds it (an Admin browsing this tree does) and the item
    // only appears then — a menu entry that always 403s is worse than no entry.
    const actionSlots = [
        {
            key: 'primary',
            // Sized for the longest verb in any catalog — Polish "Otwórz ponownie"
            // is nearly twice the English "Reopen", and a slot cut to fit English
            // truncates the word that matters in the language it ships in.
            width: 150,
            resolve: (r) => {
                const a = PRIMARY[r.status]?.(r);
                return a ? { ...a, variant: 'secondary' } : null;
            },
        },
        {
            key: 'more',
            width: 34,
            label: __('More actions'),
            resolve: (r) => ({
                label: __('More actions'),
                menu: [
                    // `href`, not a router call: these are navigations, and a
                    // supervisor triaging a shift middle-clicks them into tabs.
                    { key: 'open', label: __('Open'), icon: 'open', href: `/supervisor/work-orders/${r.id}` },
                    { key: 'edit', label: __('Edit'), icon: 'edit', href: `/supervisor/work-orders/${r.id}/edit` },
                    ...secondaryVerbs(r),
                    ...(can.delete ? [
                        { key: 'sep', divider: true },
                        {
                            key: 'delete',
                            label: __('Delete'),
                            icon: 'delete',
                            destructive: true,
                            confirm: {
                                title: __('Delete work order :order?', { order: r.order_no }),
                                body: __('Only allowed if it has no batches. Logged output stays in reports.'),
                                confirmLabel: __('Delete order'),
                            },
                            onSelect: () => router.delete(`/supervisor/work-orders/${r.id}`, { preserveScroll: true }),
                        },
                    ] : []),
                ],
            }),
        },
    ];

    return (
        <>
            <Head title={__('Work Orders')} />
            <ResourceTable
                breadcrumbs={[{ label: 'Supervisor', href: '/supervisor/dashboard', icon: 'layout-dashboard' }]}
                shape="work_orders_all"
                infinite
                detailHref={(r) => `/supervisor/work-orders/${r.id}`}
                title={__('Work Orders')}
                titleIcon="clipboard-list"
                createHref="/supervisor/work-orders/create"
                onCreate={() => setCreating(true)}
                createLabel={__('New Work Order')}
                columns={columns}
                orderBy="order_no"
                actionSlots={actionSlots}
                emptyText={__('No work orders yet.')}
                bulkActionItems={bulkActionItems}
            />

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title={__('New Work Order')}
                closeLabel={__('Close')}
                className="max-w-[720px]"
                // A misclick on the scrim shouldn't cost a half-filled order.
                keepMounted
            >
                {/* THE work-order form — the same component the create page and the
                    planner render, so a field added there appears here too.
                    `stay` makes the controller send us back to this list instead of
                    redirecting, keeping filters and paging intact. */}
                <WorkOrderForm
                    key={formKey}
                    action="/supervisor/work-orders"
                    lines={lines}
                    productTypes={productTypes}
                    customers={customers}
                    bomTemplates={bomTemplates}
                    productRevisions={productRevisions}
                    customFields={customFields}
                    stay
                    // Cancel means "throw this away", so it resets like a success
                    // does. `keepMounted` is there for the accidental dismissal —
                    // a stray click on the scrim — not for a deliberate one.
                    onCancel={() => {
                        setCreating(false);
                        setFormKey((k) => k + 1);
                    }}
                    onSuccess={() => {
                        setCreating(false);
                        setFormKey((k) => k + 1);
                    }}
                />
            </Modal>
            {promptDialog}
        </>
    );
}

SupervisorWorkOrdersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
