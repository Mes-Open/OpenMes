import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function FactoryShow() {
    const { factory } = usePage().props;
    const { divisions = [] } = factory;

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
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-om-line2">
                                    <th className="text-left py-2 px-4 font-semibold text-om-muted">Code</th>
                                    <th className="text-left py-2 px-4 font-semibold text-om-muted">Name</th>
                                    <th className="text-left py-2 px-4 font-semibold text-om-muted">Crews</th>
                                    <th className="text-left py-2 px-4 font-semibold text-om-muted">Status</th>
                                    <th className="text-right py-2 px-4"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-om-line2">
                                {divisions.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="py-6 text-center text-om-muted">
                                            No divisions assigned to this factory yet.
                                        </td>
                                    </tr>
                                ) : (
                                    divisions.map((division) => (
                                        <tr key={division.id} className="hover:bg-om-bg">
                                            <td className="py-2 px-4 font-mono text-om-muted">{division.code}</td>
                                            <td className="py-2 px-4 font-medium text-om-ink">{division.name}</td>
                                            <td className="py-2 px-4 text-om-muted">{division.crews_count}</td>
                                            <td className="py-2 px-4">
                                                {division.is_active ? (
                                                    <span className="px-2 py-0.5 bg-om-running-bg text-om-running rounded-full text-xs">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-0.5 bg-om-chip text-om-muted rounded-full text-xs">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 px-4 text-right">
                                                <Link
                                                    href={`/admin/divisions/${division.id}/edit`}
                                                    className="text-sm text-om-accent hover:text-om-accent"
                                                >
                                                    Edit
                                                </Link>
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

FactoryShow.layout = (page) => <AppLayout>{page}</AppLayout>;
