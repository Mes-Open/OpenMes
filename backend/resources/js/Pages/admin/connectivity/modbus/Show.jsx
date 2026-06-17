import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import TagManager from '../TagManager';
import RuntimePanel from '../RuntimePanel';
import { StatusDot, StatCard } from '../ui';
import { __ } from '../../../../lib/i18n';

export default function ModbusShow() {
    const { connection, workstations = [], runtime } = usePage().props;
    const modbus = connection.modbus;

    return (
        <>
            <Head title={`${connection.name} — ${__('Modbus')}`} />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Link
                            href="/admin/connectivity/modbus"
                            className="text-sm text-om-muted hover:text-om-ink flex items-center gap-1 mb-2"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            {__('Modbus Connections')}
                        </Link>
                        <div className="flex items-center gap-3">
                            <StatusDot color={connection.status_color} pulse={connection.status === 'connected'} size="w-3 h-3" />
                            <h1 className="text-2xl font-bold text-om-ink">{connection.name}</h1>
                            {!connection.is_active && (
                                <span className="text-xs px-2 py-0.5 bg-om-chip text-om-muted rounded-full">
                                    {__('Inactive')}
                                </span>
                            )}
                        </div>
                        {modbus && (
                            <p className="mt-1 text-sm text-om-muted font-mono">
                                {modbus.host}:{modbus.port} · {__('unit')} {modbus.unit_id} · {__('poll')} {modbus.poll_interval_ms}ms
                            </p>
                        )}
                    </div>
                    <Link
                        href={`/admin/connectivity/modbus/${connection.id}/edit`}
                        className="px-4 py-2 text-sm font-medium bg-om-chip text-om-muted rounded-om-sm hover:bg-om-line2 transition-colors"
                    >
                        {__('Edit')}
                    </Link>
                </div>

                {/* Status message (error/diagnostic from the poller) */}
                {connection.status_message && (
                    <div className="rounded-om-sm border border-om-line bg-om-blocked-bg px-4 py-3 text-sm text-om-blocked">
                        {connection.status_message}
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                    <StatCard value={connection.tags.length} label={__('Tags')} />
                    <StatCard value={modbus ? `${modbus.byte_order}/${modbus.word_order}` : '—'} label={__('Byte / word order')} />
                    <StatCard value={connection.status} label={connection.is_active ? __('Active') : __('Inactive')} capitalize />
                </div>

                {/* Runtime */}
                <RuntimePanel runtime={runtime} />

                {/* Tags */}
                <TagManager
                    connectionId={connection.id}
                    tags={connection.tags}
                    workstations={workstations}
                    basePath="/admin/connectivity/modbus"
                    showRegisterType
                    addressLabel={__('Register address')}
                    addressPlaceholder="e.g. 40001"
                />
            </div>
        </>
    );
}

ModbusShow.layout = (page) => <AppLayout>{page}</AppLayout>;
