import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function AreaShow() {
    const { area } = usePage().props;
    const { site, lines = [] } = area;

    return (
        <>
            <Head title={area.name} />
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex flex-wrap gap-1 items-center text-sm text-gray-500 mb-4">
                    <a href="/admin/dashboard" className="hover:text-blue-600">Dashboard</a>
                    <span>/</span>
                    <a href="/admin/sites" className="hover:text-blue-600">Sites</a>
                    {site && (
                        <>
                            <span>/</span>
                            <a href={`/admin/sites/${site.id}`} className="hover:text-blue-600">{site.name}</a>
                        </>
                    )}
                    <span>/</span>
                    <span className="text-gray-700">{area.name}</span>
                </nav>

                {/* Header */}
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800">{area.name}</h1>
                        <p className="text-gray-500 mt-1 font-mono text-sm">{area.code}</p>
                        {site && (
                            <p className="text-gray-600 mt-1">
                                Site:{' '}
                                <a href={`/admin/sites/${site.id}`} className="text-blue-600 hover:text-blue-800">
                                    {site.name}
                                </a>
                            </p>
                        )}
                    </div>
                    <a
                        href={`/admin/areas/${area.id}/edit`}
                        className="btn-touch btn-primary"
                    >
                        Edit Area
                    </a>
                </div>

                {/* Info cards */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                    <div className="card p-4">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Status</p>
                        {area.is_active ? (
                            <span className="inline-block mt-2 px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                Active
                            </span>
                        ) : (
                            <span className="inline-block mt-2 px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                                Inactive
                            </span>
                        )}
                    </div>
                    <div className="card p-4">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Description</p>
                        <p className="text-gray-700 mt-1 text-sm">{area.description || '—'}</p>
                    </div>
                </div>

                {/* Lines table */}
                <div className="card">
                    <div className="px-4 py-3 border-b border-gray-200">
                        <h2 className="font-semibold text-gray-800">
                            Lines <span className="text-gray-500">({lines.length})</span>
                        </h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200">
                                    <th className="text-left py-2 px-4 font-semibold text-gray-700">Code</th>
                                    <th className="text-left py-2 px-4 font-semibold text-gray-700">Name</th>
                                    <th className="text-left py-2 px-4 font-semibold text-gray-700">Workstations</th>
                                    <th className="text-left py-2 px-4 font-semibold text-gray-700">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {lines.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="py-6 text-center text-gray-500">
                                            No lines assigned to this area yet.
                                        </td>
                                    </tr>
                                ) : (
                                    lines.map((line) => (
                                        <tr key={line.id} className="hover:bg-gray-50">
                                            <td className="py-2 px-4 font-mono text-gray-600">{line.code}</td>
                                            <td className="py-2 px-4">
                                                <a
                                                    href={`/admin/lines/${line.id}`}
                                                    className="text-blue-600 hover:text-blue-800"
                                                >
                                                    {line.name}
                                                </a>
                                            </td>
                                            <td className="py-2 px-4 text-gray-600">{line.workstations_count}</td>
                                            <td className="py-2 px-4">
                                                {line.is_active ? (
                                                    <span className="px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">
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
