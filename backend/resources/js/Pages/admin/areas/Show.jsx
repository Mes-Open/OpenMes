import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';

export default function AreaShow() {
    const { area, customFields = [] } = usePage().props;
    const { site, lines = [] } = area;

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
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-om-line2">
                                    <th className="text-left py-2 px-4 font-semibold text-om-muted">Code</th>
                                    <th className="text-left py-2 px-4 font-semibold text-om-muted">Name</th>
                                    <th className="text-left py-2 px-4 font-semibold text-om-muted">Workstations</th>
                                    <th className="text-left py-2 px-4 font-semibold text-om-muted">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-om-line2">
                                {lines.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="py-6 text-center text-om-muted">
                                            No lines assigned to this area yet.
                                        </td>
                                    </tr>
                                ) : (
                                    lines.map((line) => (
                                        <tr key={line.id} className="hover:bg-om-bg">
                                            <td className="py-2 px-4 font-mono text-om-muted">{line.code}</td>
                                            <td className="py-2 px-4">
                                                <Link
                                                    href={`/admin/lines/${line.id}`}
                                                    className="text-om-accent hover:text-om-accent"
                                                >
                                                    {line.name}
                                                </Link>
                                            </td>
                                            <td className="py-2 px-4 text-om-muted">{line.workstations_count}</td>
                                            <td className="py-2 px-4">
                                                {line.is_active ? (
                                                    <span className="px-2 py-0.5 bg-om-running-bg text-om-running rounded-full text-xs">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-0.5 bg-om-chip text-om-muted rounded-full text-xs">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

AreaShow.layout = (page) => <AppLayout>{page}</AppLayout>;
