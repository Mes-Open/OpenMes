import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

const STATUS_BADGE = {
    open: 'bg-om-running-bg text-om-running',
    closed: 'bg-om-chip text-om-accent',
    shipped: 'bg-om-chip text-om-muted',
};

const PRINTER_ICON = (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
    </svg>
);

/** Top banner shown when no pallet label template is configured. */
function NoTemplateBanner() {
    return (
        <div className="max-w-7xl mx-auto mb-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-om-sm border border-om-line bg-om-downtime-bg px-4 py-3">
                <div className="flex items-center gap-3">
                    <span className="text-om-downtime">{PRINTER_ICON}</span>
                    <div>
                        <p className="text-sm font-semibold text-om-downtime">
                            {__('No pallet label template configured')}
                        </p>
                        <p className="text-xs text-om-downtime">
                            {__('Prepare a label template to print pallet labels.')}
                        </p>
                    </div>
                </div>
                <Link
                    href="/packaging/label-templates/create"
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-om-sm text-sm font-semibold bg-om-accent text-white hover:brightness-95 shrink-0"
                >
                    {PRINTER_ICON} {__('Prepare label')}
                </Link>
            </div>
        </div>
    );
}

/** Inline label buttons for a pallet row — PDF opens in a new tab, ZPL downloads. */
function LabelCell({ palletId, templates }) {
    // No template configured → a muted dash; the page-level banner drives the
    // "prepare a label" call to action instead of repeating it on every row.
    if (!templates.length) {
        return <span className="text-om-faint">—</span>;
    }
    const tpl = templates.find((t) => t.is_default) ?? templates[0];
    const base = `/packaging/labels/pallet/${palletId}`;
    return (
        <div className="flex items-center gap-1.5">
            <a
                href={`${base}/pdf?template=${tpl.id}`}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-om-sm text-xs font-semibold bg-om-ink text-white hover:bg-black shadow-sm"
            >
                {PRINTER_ICON} PDF
            </a>
            <a
                href={`${base}/zpl?template=${tpl.id}`}
                className="inline-flex items-center px-2.5 py-1.5 rounded-om-sm text-xs font-medium bg-om-ink text-white hover:bg-black"
                title="Download ZPL for a Zebra printer"
            >
                ZPL
            </a>
        </div>
    );
}

export default function PalletsIndex() {
    const { workOrderNumbers = {}, statusLabels = {}, labelTemplates = [] } = usePage().props;

    const columns = [
        { key: 'pallet_no', label: 'Pallet number', className: 'font-mono font-medium text-om-ink' },
        {
            key: 'work_order',
            label: 'Work order',
            render: (r) => workOrderNumbers[r.work_order_id] ?? `#${r.work_order_id}`,
        },
        { key: 'qty', label: 'Quantity' },
        {
            key: 'status',
            label: 'Status',
            render: (r) => (
                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[r.status] ?? ''}`}>
                    {statusLabels[r.status] ?? r.status}
                </span>
            ),
        },
        { key: 'location', label: 'Location', render: (r) => r.location || '—' },
        { key: 'erp_reference', label: 'ERP reference', render: (r) => r.erp_reference || '—' },
        {
            key: 'label',
            label: 'Label',
            render: (r) => <LabelCell palletId={r.id} templates={labelTemplates} />,
        },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/pallets/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete pallet "${r.pallet_no}"?`)) {
                    router.delete(`/admin/pallets/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Pallets" />
            {labelTemplates.length === 0 && <NoTemplateBanner />}
            <ResourceTable
                shape="pallets"
                title="Pallets"
                createHref="/admin/pallets/create"
                createLabel="+ New Pallet"
                columns={columns}
                orderBy="pallet_no"
                orderDir="desc"
                actions={actions}
                emptyText="No pallets yet."
            />
        </>
    );
}

PalletsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
