import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

const STATUS_BADGE = {
    open: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    closed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    shipped: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
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
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-4 py-3">
                <div className="flex items-center gap-3">
                    <span className="text-amber-600 dark:text-amber-400">{PRINTER_ICON}</span>
                    <div>
                        <p className="text-sm font-semibold text-amber-800 dark:text-amber-200">
                            {__('No pallet label template configured')}
                        </p>
                        <p className="text-xs text-amber-700 dark:text-amber-300">
                            {__('Prepare a label template to print pallet labels.')}
                        </p>
                    </div>
                </div>
                <Link
                    href="/packaging/label-templates/create"
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-amber-600 text-white hover:bg-amber-700 shrink-0"
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
        return <span className="text-gray-400">—</span>;
    }
    const tpl = templates.find((t) => t.is_default) ?? templates[0];
    const base = `/packaging/labels/pallet/${palletId}`;
    return (
        <div className="flex items-center gap-1.5">
            <a
                href={`${base}/pdf?template=${tpl.id}`}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 shadow-sm"
            >
                {PRINTER_ICON} PDF
            </a>
            <a
                href={`${base}/zpl?template=${tpl.id}`}
                className="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium bg-gray-700 text-white hover:bg-gray-800"
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
        { key: 'pallet_no', label: 'Pallet number', className: 'font-mono font-medium text-gray-800' },
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
