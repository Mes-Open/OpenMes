import { useMemo } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../../layouts/AppLayout';
import { formatNumber } from '../../../../lib/i18n';

const STATUS_DOT = {
    green:  'bg-om-running',
    yellow: 'bg-om-downtime',
    red:    'bg-om-blocked',
    slate:  'bg-slate-400',
};

export default function MqttIndex() {
    const { connections = [] } = usePage().props;

    const handleDelete = (conn) => {
        if (confirm('Delete this connection and all its topics?')) {
            router.delete(`/admin/connectivity/mqtt/${conn.id}`, { preserveScroll: true });
        }
    };

    const columns = useMemo(() => [
        {
            id: 'status',
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const conn = row.original;
                const dot = STATUS_DOT[conn.status_color] ?? 'bg-slate-400';
                return (
                    <div className="flex items-center gap-2">
                        <span className={`w-2 h-2 rounded-full ${dot} ${conn.status === 'connected' ? 'animate-pulse' : ''}`} />
                        <span className="text-xs text-om-muted capitalize">{conn.status}</span>
                    </div>
                );
            },
            meta: { align: 'left' },
        },
        {
            id: 'name',
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => {
                const conn = row.original;
                return (
                    <span className="font-medium text-om-ink">
                        <a href={`/admin/connectivity/mqtt/${conn.id}`} className="hover:text-om-accent">
                            {conn.name}
                        </a>
                        {!conn.is_active && (
                            <span className="ml-1.5 text-xs text-om-faint">(inactive)</span>
                        )}
                    </span>
                );
            },
            meta: { align: 'left' },
        },
        {
            id: 'broker',
            accessorFn: (r) => (r.mqtt_host ? `${r.mqtt_host}:${r.mqtt_port}` : ''),
            header: 'Broker',
            cell: ({ row }) => {
                const conn = row.original;
                return (
                    <span className="font-mono text-xs text-om-muted">
                        {conn.mqtt_host ? (
                            <>
                                {conn.mqtt_host}:{conn.mqtt_port}
                                {conn.mqtt_use_tls && (
                                    <span className="ml-1 text-om-running">TLS</span>
                                )}
                            </>
                        ) : (
                            <span className="text-red-400">Not configured</span>
                        )}
                    </span>
                );
            },
            meta: { align: 'left' },
        },
        {
            id: 'topics',
            accessorKey: 'topics_count',
            header: 'Topics',
            cell: ({ row }) => <span className="text-om-muted">{row.original.topics_count}</span>,
            meta: { align: 'left' },
        },
        {
            id: 'messages',
            accessorKey: 'messages_received',
            header: 'Messages',
            cell: ({ row }) => (
                <span className="text-om-muted">{formatNumber(Number(row.original.messages_received))}</span>
            ),
            meta: { align: 'left' },
        },
        {
            id: 'last_connected',
            accessorKey: 'last_connected_at',
            header: 'Last connected',
            cell: ({ row }) => (
                <span className="text-xs text-om-muted">{row.original.last_connected_at ?? '—'}</span>
            ),
            meta: { align: 'left' },
        },
        {
            id: 'actions',
            header: 'Actions',
            enableSorting: false,
            cell: ({ row }) => {
                const conn = row.original;
                return (
                    <div className="flex items-center justify-end gap-2">
                        <a href={`/admin/connectivity/mqtt/${conn.id}`} className="p-1.5 rounded-md transition-colors text-om-muted hover:text-om-ink" title="View" aria-label="View">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        <a href={`/admin/connectivity/mqtt/${conn.id}/edit`} className="p-1.5 rounded-md transition-colors text-om-accent hover:text-om-accent" title="Edit" aria-label="Edit">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                        <button
                            type="button"
                            onClick={() => handleDelete(conn)}
                            className="p-1.5 rounded-md transition-colors text-om-blocked hover:text-om-blocked"
                            title="Delete"
                            aria-label="Delete"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                );
            },
            meta: { align: 'right' },
        },
    ], []);

    return (
        <>
            <Head title="MQTT Connections" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-om-ink">MQTT Connections</h1>
                        <p className="text-sm text-om-muted mt-1">
                            Define and manage MQTT broker connections and topic subscriptions.
                        </p>
                    </div>
                    <a
                        href="/admin/connectivity/mqtt/create"
                        className="inline-flex items-center gap-2 px-4 py-2 bg-om-ink text-om-on-ink text-sm font-medium rounded-om-sm hover:bg-om-ink-hover transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New MQTT Connection
                    </a>
                </div>

                {connections.length === 0 ? (
                    <div className="text-center py-16 text-om-faint">
                        <svg className="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                        <p className="text-sm">No MQTT connections defined.</p>
                    </div>
                ) : (
                    <DataTable
                        data={connections}
                        columns={columns}
                        searchable
                        columnToggle
                        paginated
                        searchPlaceholder="Search connections…"
                    />
                )}
            </div>
        </>
    );
}

MqttIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
