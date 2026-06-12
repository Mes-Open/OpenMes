import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { StatusDot } from '../ui';
import { __ } from '../../../../lib/i18n';

export default function ModbusIndex() {
    const { connections = [] } = usePage().props;

    return (
        <>
            <Head title={__('Modbus Connections')} />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <Link
                            href="/admin/connectivity"
                            className="text-sm text-om-muted hover:text-om-ink flex items-center gap-1 mb-1"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            {__('All connectivity')}
                        </Link>
                        <h1 className="text-2xl font-bold text-om-ink">{__('Modbus TCP')}</h1>
                        <p className="text-sm text-om-muted mt-1">
                            {__('Poll registers from Modbus TCP devices and map them to machine signals.')}
                        </p>
                    </div>
                    <Link
                        href="/admin/connectivity/modbus/create"
                        className="inline-flex items-center gap-2 px-4 py-2 bg-om-ink text-white text-sm font-medium rounded-om-sm hover:bg-black transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {__('New Connection')}
                    </Link>
                </div>

                {connections.length === 0 ? (
                    <div className="text-center py-16 text-om-faint">
                        <p className="text-sm">{__('No Modbus connections defined yet.')}</p>
                        <Link href="/admin/connectivity/modbus/create" className="mt-2 inline-block text-om-accent hover:underline text-sm">
                            {__('Create your first Modbus connection →')}
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        {connections.map((conn) => (
                            <div
                                key={conn.id}
                                className="bg-om-card rounded-om border border-om-line2 shadow-sm hover:shadow-md transition-shadow p-5 flex flex-col gap-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <StatusDot color={conn.status_color} pulse={conn.status === 'connected'} />
                                        <h3 className="font-semibold text-om-ink truncate">{conn.name}</h3>
                                    </div>
                                    {!conn.is_active && (
                                        <span className="shrink-0 text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">
                                            {__('Inactive')}
                                        </span>
                                    )}
                                </div>

                                {conn.description && (
                                    <p className="text-xs text-om-muted line-clamp-2">{conn.description}</p>
                                )}

                                {conn.host && (
                                    <p className="text-xs text-om-faint font-mono">
                                        {conn.host}:{conn.port} · {__('unit')} {conn.unit_id}
                                    </p>
                                )}

                                <div className="flex items-center justify-between text-xs text-om-muted">
                                    <span>{conn.tags_count} {conn.tags_count === 1 ? __('tag') : __('tags')}</span>
                                    {conn.last_connected_at && <span>{conn.last_connected_at}</span>}
                                </div>

                                <div className="flex gap-2 pt-1 border-t border-om-line2 mt-auto">
                                    <Link
                                        href={`/admin/connectivity/modbus/${conn.id}`}
                                        className="flex-1 text-center text-xs px-3 py-1.5 bg-om-chip text-om-accent rounded-md hover:bg-om-chip transition-colors font-medium"
                                    >
                                        {__('View')}
                                    </Link>
                                    <Link
                                        href={`/admin/connectivity/modbus/${conn.id}/edit`}
                                        className="flex-1 text-center text-xs px-3 py-1.5 bg-om-panel text-om-muted rounded-md hover:bg-om-chip transition-colors font-medium"
                                    >
                                        {__('Edit')}
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
