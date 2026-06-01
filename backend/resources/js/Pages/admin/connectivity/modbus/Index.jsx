import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { StatusDot } from '../ui';

export default function ModbusIndex() {
    const { connections = [] } = usePage().props;

    return (
        <>
            <Head title="Modbus Connections" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <Link
                            href="/admin/connectivity"
                            className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1 mb-1"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            All connectivity
                        </Link>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Modbus TCP</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Poll registers from Modbus TCP devices and map them to machine signals.
                        </p>
                    </div>
                    <Link
                        href="/admin/connectivity/modbus/create"
                        className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Connection
                    </Link>
                </div>

                {connections.length === 0 ? (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <p className="text-sm">No Modbus connections defined yet.</p>
                        <Link href="/admin/connectivity/modbus/create" className="mt-2 inline-block text-blue-500 hover:underline text-sm">
                            Create your first Modbus connection →
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        {connections.map((conn) => (
                            <div
                                key={conn.id}
                                className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow p-5 flex flex-col gap-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <StatusDot color={conn.status_color} pulse={conn.status === 'connected'} />
                                        <h3 className="font-semibold text-gray-900 dark:text-white truncate">{conn.name}</h3>
                                    </div>
                                    {!conn.is_active && (
                                        <span className="shrink-0 text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                            Inactive
                                        </span>
                                    )}
                                </div>

                                {conn.description && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{conn.description}</p>
                                )}

                                {conn.host && (
                                    <p className="text-xs text-gray-400 dark:text-gray-500 font-mono">
                                        {conn.host}:{conn.port} · unit {conn.unit_id}
                                    </p>
                                )}

                                <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>{conn.tags_count} {conn.tags_count === 1 ? 'tag' : 'tags'}</span>
                                    {conn.last_connected_at && <span>{conn.last_connected_at}</span>}
                                </div>

                                <div className="flex gap-2 pt-1 border-t border-gray-100 dark:border-gray-700 mt-auto">
                                    <Link
                                        href={`/admin/connectivity/modbus/${conn.id}`}
                                        className="flex-1 text-center text-xs px-3 py-1.5 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-md hover:bg-blue-100 transition-colors font-medium"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        href={`/admin/connectivity/modbus/${conn.id}/edit`}
                                        className="flex-1 text-center text-xs px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 transition-colors font-medium"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ModbusIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
