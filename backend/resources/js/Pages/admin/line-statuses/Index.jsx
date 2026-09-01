import { Head, router, usePage } from '@inertiajs/react';
import { Badge, useToast } from '@openmes/ui';

import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { LINE_STATUS_FIELDS, lineStatusInitial } from './fields';
import { apiCall } from '../../../lib/http';
import { __ } from '../../../lib/i18n';

/**
 * Global line statuses — the kanban columns every production line inherits.
 *
 * This was a hand-rolled `<table>` of live inputs with a Save button per row.
 * It worked, but it was the only list in the app that didn't search, filter,
 * sort or page, and its add row and edit row validated separately — so the two
 * could disagree about what a status is. It is now the shared `ResourceTable`
 * over the same synced collection, with create/edit through `ResourceForm`
 * (`fields.js` is the single description of a status), which is what every
 * other admin list does.
 */
export default function LineStatusesIndex() {
    const toast = useToast();
    // Only sent once the drawer asks for it — see the controller's index().
    const { nextSortOrder } = usePage().props;

    const drawer = useResourceDrawer();

    /**
     * A plain POST, not an Inertia visit: these rows are a synced collection, so
     * the new order arrives over the websocket by itself. A visit would re-render
     * a list that is already right and remount the toast provider mid-confirmation.
     *
     * And a toast, not the page's flash bar — the bar pushes the whole table down
     * as it appears, moving the rows you just dropped.
     */
    const reorder = async (ids) => {
        try {
            const res = await apiCall('/admin/line-statuses/reorder', 'POST', { ids });
            if (!res.ok) throw new Error(String(res.status));
            toast({ severity: 'success', title: __('Order saved') });
        } catch {
            toast({ severity: 'error', title: __("Couldn't save the new order") });
        }
    };

    const columns = [
        {
            key: 'color',
            label: __('Color'),
            // The hex itself is the searchable value — the swatch can't be typed
            // into a filter box, and "#1c9a55" is what a designer would paste.
            value: (r) => r.color ?? '',
            render: (r) => (
                <span className="inline-flex items-center gap-2">
                    <span
                        aria-hidden
                        className="size-[18px] shrink-0 rounded-[5px] border border-om-line"
                        style={{ backgroundColor: r.color }}
                    />
                    <span className="font-mono text-[11.5px] text-om-faint uppercase">{r.color}</span>
                </span>
            ),
        },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text', link: true },
        { key: 'sort_order', label: __('Order'), className: 'font-mono text-om-muted' },
        {
            key: 'is_default',
            label: __('Default'),
            value: (r) => __(r.is_default ? 'Yes' : 'No'),
            // Only one status can hold it, so the interesting cell is the one
            // that says yes — the rest stay quiet rather than each printing "No".
            render: (r) => (r.is_default ? <Badge variant="outline">{__('Default')}</Badge> : <span className="text-om-faintest">—</span>),
        },
    ];

    const actions = (r) => [
        // The row is the record: `line_statuses_global` syncs every column the
        // form needs, so the drawer opens filled in without a round-trip.
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete status ":name"?', { name: r.name }),
                body: __('Work orders sitting in it keep their history and move to no status.'),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/line-statuses/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Line Statuses')} />
            <ResourceTable
                shape="line_statuses_global"
                title={__('Line Statuses')}
                subtitle={
                    <span className="text-[13px] text-om-muted">
                        {__('Global kanban statuses available to every production line.')}
                    </span>
                }
                // No detail page — the name opens the editor, which is the only
                // thing there is to see about a status.
                detailHref={(r) => `/admin/line-statuses/${r.id}/edit`}
                createHref="/admin/line-statuses/create"
                onCreate={drawer.create}
                createLabel={__('New Status')}
                columns={columns}
                orderBy="sort_order"
                // The board's column order is the point of this list, so it is
                // set by dragging rather than by typing a number and hoping it
                // doesn't collide with someone else's.
                onReorder={reorder}
                actions={actions}
                emptyText={__('No line statuses yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/line-statuses"
                fields={LINE_STATUS_FIELDS}
                initial={(record) => lineStatusInitial(record, { nextSortOrder })}
                ensure={['nextSortOrder']}
                ready={nextSortOrder !== undefined}
                title={{ create: __('New Status'), edit: __('Edit Status') }}
            />

        </>
    );
}

LineStatusesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
