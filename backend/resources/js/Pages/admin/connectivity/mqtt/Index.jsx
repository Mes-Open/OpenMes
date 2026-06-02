import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';

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
                                                {Number(conn.messages_received).toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                                {conn.last_connected_at ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <a href={`/admin/connectivity/mqtt/${conn.id}`} className="text-xs px-2 py-1 text-blue-600 dark:text-blue-400 hover:underline">View</a>
                                                    <a href={`/admin/connectivity/mqtt/${conn.id}/edit`} className="text-xs px-2 py-1 text-gray-600 dark:text-gray-300 hover:underline">Edit</a>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(conn)}
                                                        className="text-xs px-2 py-1 text-red-500 hover:underline"
                                                    >
                                                        Delete
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
