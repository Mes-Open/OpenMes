import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function FactoryShow() {
    const { factory } = usePage().props;
    const { divisions = [] } = factory;

    return (
        <>
            <Head title={factory.name} />
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex flex-wrap gap-1 items-center text-sm text-gray-500 mb-4">
                    <a href="/admin/dashboard" className="hover:text-blue-600">Dashboard</a>
                    <span>/</span>
                    <a href="/admin/factories" className="hover:text-blue-600">Factories</a>
                    <span>/</span>
                    <span className="text-gray-700">{factory.name}</span>
                </nav>

                {/* Header */}
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800">{factory.name}</h1>
                        <p className="text-gray-500 mt-1 font-mono text-sm">{factory.code}</p>
                    </div>
                    <a
                        href={`/admin/factories/${factory.id}/edit`}
                        className="btn-touch btn-primary"
                    >
                        Edit Factory
                    </a>
                </div>

                {/* Info cards */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                    <div className="card p-4">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Status</p>
                        {factory.is_active ? (
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
                        <p className="text-gray-700 mt-1 text-sm">{factory.description || '—'}</p>
                    </div>
                </div>

                {/* Divisions table */}
                <div className="card">
                    <div className="px-4 py-3 border-b border-gray-200">
                        <h2 className="font-semibold text-gray-800">
                            Divisions <span className="text-gray-500">({divisions.length})</span>
                        </h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200">
                                    <th className="text-left py-2 px-4 font-semibold text-gray-700">Code</th>
                                    <th className="text-left py-2 px-4 font-semibold text-gray-700">Name</th>
                                    <th className="text-left py-2 px-4 font-semibold text-gray-700">Crews</th>
                                    <th className="text-left py-2 px-4 font-semibold text-gray-700">Status</th>
                                    <th className="text-right py-2 px-4"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {divisions.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="py-6 text-center text-gray-500">
                                            No divisions assigned to this factory yet.
                                        </td>
                                    </tr>
                                ) : (
                                    divisions.map((division) => (
                                        <tr key={division.id} className="hover:bg-gray-50">
                                            <td className="py-2 px-4 font-mono text-gray-600">{division.code}</td>
                                            <td className="py-2 px-4 font-medium text-gray-800">{division.name}</td>
                                            <td className="py-2 px-4 text-gray-600">{division.crews_count}</td>
                                            <td className="py-2 px-4">
                                                {division.is_active ? (
                                                    <span className="px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 px-4 text-right">
                                                <a
                                                    href={`/admin/divisions/${division.id}/edit`}
                                                    className="text-sm text-blue-600 hover:text-blue-800"
                                                >
                                                    Edit
                                                </a>
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
