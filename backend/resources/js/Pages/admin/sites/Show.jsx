import { Head, Link, usePage } from '@inertiajs/react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';

const areaColumns = [
    {
        id: 'code',
        accessorKey: 'code',
        header: 'Code',
        cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.code}</span>,
    },
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <Link
                href={`/admin/areas/${row.original.id}`}
                className="text-om-accent hover:text-om-accent"
            >
                {row.original.name}
            </Link>
        ),
    },
    {
        id: 'lines',
        accessorKey: 'lines_count',
        header: 'Lines',
        cell: ({ row }) => <span className="text-om-muted">{row.original.lines_count}</span>,
    },
    {
        id: 'status',
        accessorFn: (r) => r.is_active,
        header: 'Status',
        cell: ({ row }) =>
            row.original.is_active ? (
                <span className="px-2 py-0.5 bg-om-running-bg text-om-running rounded-full text-xs">
                    Active
                </span>
            ) : (
                <span className="px-2 py-0.5 bg-om-chip text-om-muted rounded-full text-xs">
                    Inactive
                </span>
            ),
    },
    {
        id: 'actions',
        header: '',
        enableSorting: false,
        meta: { align: 'right' },
        cell: ({ row }) => (
            <Link
                href={`/admin/areas/${row.original.id}/edit`}
                className="text-sm text-om-accent hover:text-om-accent"
            >
                Edit
            </Link>
        ),
    },
];

const lineColumns = [
    {
        id: 'code',
        accessorKey: 'code',
        header: 'Code',
        cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.code}</span>,
    },
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <Link
                href={`/admin/lines/${row.original.id}`}
                className="text-om-accent hover:text-om-accent"
            >
                {row.original.name}
            </Link>
        ),
    },
    {
        id: 'status',
        accessorFn: (r) => r.is_active,
        header: 'Status',
        cell: ({ row }) =>
            row.original.is_active ? (
                <span className="px-2 py-0.5 bg-om-running-bg text-om-running rounded-full text-xs">
                    Active
                </span>
            ) : (
                <span className="px-2 py-0.5 bg-om-chip text-om-muted rounded-full text-xs">
                    Inactive
                </span>
            ),
    },
];

export default function SiteShow() {
    const { site, customFields = [] } = usePage().props;
    const { company, areas = [], lines = [] } = site;

    const locationParts = [site.address, [site.city, site.country].filter(Boolean).join(', ')].filter(Boolean);

    return (
        <>
            <Head title={site.name} />
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex flex-wrap gap-1 items-center text-sm text-om-muted mb-4">
                    <Link href="/admin/dashboard" className="hover:text-om-accent">Dashboard</Link>
                    <span>/</span>
                    <Link href="/admin/sites" className="hover:text-om-accent">Sites</Link>
                    <span>/</span>
                    <span className="text-om-muted">{site.name}</span>
                </nav>

                {/* Header */}
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{site.name}</h1>
                        <p className="text-om-muted mt-1 font-mono text-sm">{site.code}</p>
                        {company && (
                            <p className="text-om-muted mt-1">
                                Company: <span className="font-medium">{company.name}</span>
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href={`/admin/sites/${site.id}/areas/create`}
                            className="btn-touch btn-secondary"
                        >
                            Add Area
                        </Link>
                        <Link
                            href={`/admin/sites/${site.id}/edit`}
                            className="btn-touch btn-primary"
                        >
                            Edit Site
                        </Link>
                    </div>
                </div>

                {/* Info cards */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                    <div className="card p-4">
                        <p className="text-xs uppercase tracking-wide text-om-muted">Location</p>
                        <p className="text-om-ink mt-1">
                            {locationParts.length > 0 ? (
                                locationParts.map((part, i) => (
                                    <span key={i}>
                                        {part}
                                        {i < locationParts.length - 1 && <br />}
                                    </span>
                                ))
                            ) : (
                                '—'
                            )}
                        </p>
                        {site.timezone && (
                            <p className="text-xs text-om-muted mt-2">Timezone: {site.timezone}</p>
                        )}
                    </div>
                    <div className="card p-4">
                        <p className="text-xs uppercase tracking-wide text-om-muted">Status</p>
                        {site.is_active ? (
                            <span className="inline-block mt-2 px-2 py-1 bg-om-running-bg text-om-running rounded-full text-xs font-medium">
                                Active
                            </span>
                        ) : (
                            <span className="inline-block mt-2 px-2 py-1 bg-om-chip text-om-muted rounded-full text-xs font-medium">
                                Inactive
                            </span>
                        )}
                    </div>
                    <div className="card p-4">
                        <p className="text-xs uppercase tracking-wide text-om-muted">Description</p>
                        <p className="text-om-muted mt-1 text-sm">{site.description || '—'}</p>
                    </div>
                </div>

                <div className="mb-6">
                    <CustomFieldsDisplay definitions={customFields} values={site.custom_fields ?? {}} />
                </div>

                {/* Areas table */}
                <div className="card mb-6">
                    <div className="px-4 py-3 border-b border-om-line2 flex items-center justify-between">
                        <h2 className="font-semibold text-om-ink">
                            Areas <span className="text-om-muted">({areas.length})</span>
                        </h2>
                        <Link
                            href={`/admin/sites/${site.id}/areas/create`}
                            className="text-sm text-om-accent hover:text-om-accent"
                        >
                            + Add Area
                        </Link>
                    </div>
                    <DataTable
                        data={areas}
                        columns={areaColumns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel="No areas defined yet."
                    />
                </div>

                {/* Lines table */}
                <div className="card">
                    <div className="px-4 py-3 border-b border-om-line2">
                        <h2 className="font-semibold text-om-ink">
                            Lines under this Site <span className="text-om-muted">({lines.length})</span>
                        </h2>
                    </div>
                    <DataTable
                        data={lines}
                        columns={lineColumns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel="No lines mapped under this site yet."
                    />
                </div>
            </div>
        </>
    );
}

SiteShow.layout = (page) => <AppLayout>{page}</AppLayout>;
