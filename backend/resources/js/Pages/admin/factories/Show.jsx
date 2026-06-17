import { useMemo } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';

export default function FactoryShow() {
    const { factory } = usePage().props;
    const { divisions = [] } = factory;

    const divisionColumns = useMemo(() => [
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
                <span className="font-medium text-om-ink">{row.original.name}</span>
            ),
        },
        {
            id: 'crews',
            accessorKey: 'crews_count',
            header: 'Crews',
            cell: ({ row }) => (
                <span className="text-om-muted">{row.original.crews_count}</span>
            ),
        },
        {
            id: 'status',
            accessorFn: (r) => r.is_active,
            header: 'Status',
            cell: ({ row }) => (
                row.original.is_active ? (
                    <span className="px-2 py-0.5 bg-om-running-bg text-om-running rounded-full text-xs">
                        Active
                    </span>
                ) : (
                    <span className="px-2 py-0.5 bg-om-chip text-om-muted rounded-full text-xs">
                        Inactive
                    </span>
                )
            ),
        },
        {
            id: 'actions',
            header: '',
            enableSorting: false,
            meta: { align: 'right' },
            cell: ({ row }) => (
                <Link
                    href={`/admin/divisions/${row.original.id}/edit`}
                    className="text-sm text-om-accent hover:text-om-accent"
                >
                    Edit
                </Link>
            ),
        },
    ], []);

    return (
        <>
            <Head title={factory.name} />
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex flex-wrap gap-1 items-center text-sm text-om-muted mb-4">
                    <Link href="/admin/dashboard" className="hover:text-om-accent">Dashboard</Link>
                    <span>/</span>
                    <Link href="/admin/factories" className="hover:text-om-accent">Factories</Link>
                    <span>/</span>
                    <span className="text-om-muted">{factory.name}</span>
                </nav>

                {/* Header */}
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{factory.name}</h1>
                        <p className="text-om-muted mt-1 font-mono text-sm">{factory.code}</p>
                    </div>
                    <Link
                        href={`/admin/factories/${factory.id}/edit`}
                        className="btn-touch btn-primary"
                    >
                        Edit Factory
                    </Link>
                </div>

                {/* Info cards */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                    <div className="card p-4">
                        <p className="text-xs uppercase tracking-wide text-om-muted">Status</p>
                        {factory.is_active ? (
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
                        <p className="text-om-muted mt-1 text-sm">{factory.description || '—'}</p>
                    </div>
                </div>

                {/* Divisions table */}
                <div className="card">
                    <div className="px-4 py-3 border-b border-om-line2">
                        <h2 className="font-semibold text-om-ink">
                            Divisions <span className="text-om-muted">({divisions.length})</span>
                        </h2>
                    </div>
                    <DataTable
                        data={divisions}
                        columns={divisionColumns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel="No divisions assigned to this factory yet."
                    />
                </div>
            </div>
        </>
    );
}

FactoryShow.layout = (page) => <AppLayout>{page}</AppLayout>;
