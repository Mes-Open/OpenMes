import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { downtimeReasonFields, downtimeReasonInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function DowntimeReasonsIndex() {
    const drawer = useResourceDrawer();

    const { counts = {}, kinds = [] } = usePage().props;
    const kindLabels = Object.fromEntries(kinds.map((k) => [k.value, __(k.label)]));
    const countsAsLoss = Object.fromEntries(kinds.map((k) => [k.value, k.counts_as_loss]));

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', filter: 'text' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        {
            key: 'kind',
            label: __('Kind'),
            className: 'text-om-muted',
            value: (r) => kindLabels[r.kind] ?? r.kind,
            render: (r) => kindLabels[r.kind] ?? r.kind,
        },
        // The column that actually matters when reading an OEE report: which of
        // these reasons pull availability down and which merely shorten the
        // window they are measured against.
        {
            key: 'availability',
            label: __('Availability'),
            value: (r) => (countsAsLoss[r.kind] ? __('Counts as loss') : __('Not a loss')),
            render: (r) => (
                <span className={countsAsLoss[r.kind] ? 'text-om-blocked' : 'text-om-muted'}>
                    {countsAsLoss[r.kind] ? __('Counts as loss') : __('Not a loss')}
                </span>
            ),
        },
        { key: 'used', label: __('Used'), align: 'right', value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/downtime-reasons/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete downtime reason ":name"?', { name: r.name }),
                // Said plainly: the reason disappears from the operator's list
                // but every stop already recorded against it keeps its history.
                body: __('Downtime already recorded against it keeps this reason.'),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/downtime-reasons/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Downtime Reasons')} />
            <ResourceTable
                shape="downtime_reasons"
                title={__('Downtime Reasons')}
                createHref="/admin/downtime-reasons/create"
                onCreate={drawer.create}
                createLabel={__('New Reason')}
                columns={columns}
                orderBy="code"
                actions={actions}
                emptyText={__('No downtime reasons yet — operators have nothing to choose from when a machine stops.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/downtime-reasons"
                fields={downtimeReasonFields(kinds)}
                initial={downtimeReasonInitial}
                title={{ create: __('New Downtime Reason'), edit: __('Edit Downtime Reason') }}
            />
        </>
    );
}

DowntimeReasonsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
