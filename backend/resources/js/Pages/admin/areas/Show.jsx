import { useMemo } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';

export default function AreaShow() {
    const { area, customFields = [] } = usePage().props;
    const { site, lines = [] } = area;

    const lineColumns = useMemo(() => [
        {
            id: 'code',
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => (
                <span className="font-mono text-om-muted">{row.original.code}</span>
            ),
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
            id: 'workstations',
            accessorKey: 'workstations_count',
            header: 'Workstations',
            cell: ({ row }) => (
                <span className="text-om-muted">{row.original.workstations_count}</span>
            ),
        },
        {
            id: 'status',
            accessorFn: (r) => (r.is_active ? 'Active' : 'Inactive'),
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
    ], []);

    return (
        <>
            <Head title={area.name} />
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex flex-wrap gap-1 items-center text-sm text-om-muted mb-4">
                    <Link href="/admin/dashboard" className="hover:text-om-accent">Dashboard</Link>
                    <span>/</span>
                    <Link href="/admin/sites" className="hover:text-om-accent">Sites</Link>
                    {site && (
                        <>
                            <span>/</span>
                            <Link href={`/admin/sites/${site.id}`} className="hover:text-om-accent">{site.name}</Link>
                        </>
                    )}
                    <span>/</span>
                    <span className="text-om-muted">{area.name}</span>
                </nav>

                {/* Header */}
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{area.name}</h1>
                        <p className="text-om-muted mt-1 font-mono text-sm">{area.code}</p>
                        {site && (
                            <p className="text-om-muted mt-1">
                                Site:{' '}
                                <Link href={`/admin/sites/${site.id}`} className="text-om-accent hover:text-om-accent">
                                    {site.name}
                                </Link>
                            </p>
                        )}
                    </div>
                    <Link
                        href={`/admin/areas/${area.id}/edit`}
                        className="btn-touch btn-primary"
                    >
                        Edit Area
                    </Link>
                </div>

                {/* Info cards */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                    <div className="card p-4">
                        <p className="text-xs uppercase tracking-wide text-om-muted">Status</p>
                        {area.is_active ? (
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
                        <p className="text-om-muted mt-1 text-sm">{area.description || '—'}</p>
                    </div>
                </div>

                <div className="mb-6">
                    <CustomFieldsDisplay definitions={customFields} values={area.custom_fields ?? {}} />
                </div>

                {/* Lines table */}
                <div className="card">
                    <div className="px-4 py-3 border-b border-om-line2">
                        <h2 className="font-semibold text-om-ink">
                            Lines <span className="text-om-muted">({lines.length})</span>
                        </h2>
                    </div>
                    <DataTable
                        data={lines}
                        columns={lineColumns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel="No lines assigned to this area yet."
                    />
                </div>
            </div>
        </>
    );
}

AreaShow.layout = (page) => <AppLayout>{page}</AppLayout>;
