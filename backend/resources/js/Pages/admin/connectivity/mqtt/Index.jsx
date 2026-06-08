import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { formatNumber } from '../../../../lib/i18n';

const STATUS_DOT = {
    green:  'bg-green-500',
    yellow: 'bg-yellow-400',
    red:    'bg-red-500',
    slate:  'bg-slate-400',
};

export default function MqttIndex() {
    const { connections = [] } = usePage().props;

    const handleDelete = (conn) => {
        if (confirm('Delete this connection and all its topics?')) {
            router.delete(`/admin/connectivity/mqtt/${conn.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="MQTT Connections" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">MQTT Connections</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Define and manage MQTT broker connections and topic subscriptions.
                        </p>
                    </div>
                    <a
                        href="/admin/connectivity/mqtt/create"
                        className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New MQTT Connection
                    </a>
                </div>

                {connections.length === 0 ? (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <svg className="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                        <p className="text-sm">No MQTT connections defined.</p>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th className="px-4 py-3 text-left">Status</th>
                                    <th className="px-4 py-3 text-left">Name</th>
                                    <th className="px-4 py-3 text-left">Broker</th>
                                    <th className="px-4 py-3 text-left">Topics</th>
                                    <th className="px-4 py-3 text-left">Messages</th>
                                    <th className="px-4 py-3 text-left">Last connected</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {connections.map((conn) => {
                                    const dot = STATUS_DOT[conn.status_color] ?? 'bg-slate-400';
                                    return (
                                        <tr key={conn.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className={`w-2 h-2 rounded-full ${dot} ${conn.status === 'connected' ? 'animate-pulse' : ''}`} />
                                                    <span className="text-xs text-gray-500 dark:text-gray-400 capitalize">{conn.status}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                                <a href={`/admin/connectivity/mqtt/${conn.id}`} className="hover:text-blue-600 dark:hover:text-blue-400">
                                                    {conn.name}
                                                </a>
                                                {!conn.is_active && (
                                                    <span className="ml-1.5 text-xs text-gray-400 dark:text-gray-500">(inactive)</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">
                                                {conn.mqtt_host ? (
                                                    <>
                                                        {conn.mqtt_host}:{conn.mqtt_port}
                                                        {conn.mqtt_use_tls && (
                                                            <span className="ml-1 text-green-600 dark:text-green-400">TLS</span>
                                                        )}
                                                    </>
                                                ) : (
                                                    <span className="text-red-400">Not configured</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{conn.topics_count}</td>
                                            <td className="px-4 py-3 text-gray-600 dark:text-gray-300">
                                                {formatNumber(Number(conn.messages_received))}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                                {conn.last_connected_at ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <a href={`/admin/connectivity/mqtt/${conn.id}`} className="p-1.5 rounded-md transition-colors text-gray-600 hover:text-gray-800" title="View" aria-label="View">
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </a>
                                                    <a href={`/admin/connectivity/mqtt/${conn.id}/edit`} className="p-1.5 rounded-md transition-colors text-blue-600 hover:text-blue-800" title="Edit" aria-label="Edit">
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </a>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(conn)}
                                                        className="p-1.5 rounded-md transition-colors text-red-600 hover:text-red-800"
                                                        title="Delete"
                                                        aria-label="Delete"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

MqttIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
