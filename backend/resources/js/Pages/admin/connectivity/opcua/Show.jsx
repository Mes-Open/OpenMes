import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import TagManager from '../TagManager';
import RuntimePanel from '../RuntimePanel';
import { StatusDot, StatCard } from '../ui';
import { __ } from '../../../../lib/i18n';

export default function OpcuaShow() {
    const { connection, workstations = [], runtime } = usePage().props;
    const opcua = connection.opcua;

    return (
        <>
            <Head title={`${connection.name} — ${__('OPC UA')}`} />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <Link
                            href="/admin/connectivity/opcua"
                            className="text-sm text-om-muted hover:text-om-ink flex items-center gap-1 mb-2"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            {__('OPC UA Connections')}
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
                        {opcua && (
                            <p className="mt-1 text-sm text-om-muted font-mono truncate" title={opcua.endpoint_url}>
                                {opcua.endpoint_url}
                            </p>
                        )}
                    </div>
                    <Link
                        href={`/admin/connectivity/opcua/${connection.id}/edit`}
                        className="px-4 py-2 text-sm font-medium bg-om-chip text-om-muted rounded-om-sm hover:bg-om-line2 transition-colors shrink-0"
                    >
                        {__('Edit')}
                    </Link>
                </div>

                {/* Status message (error/diagnostic from the gateway) */}
                {connection.status_message && (
                    <div className="rounded-om-sm border border-om-line bg-om-blocked-bg px-4 py-3 text-sm text-om-blocked">
                        {connection.status_message}
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                    <StatCard value={connection.tags.length} label={__('Tags')} />
                    <StatCard value={opcua?.security_policy ?? '—'} label={__('Mode: :mode', { mode: opcua?.security_mode ?? '—' })} />
                    <StatCard value={opcua?.auth_mode ?? '—'} label={__('Authentication')} capitalize />
                </div>

                {/* Runtime */}
                <RuntimePanel runtime={runtime} />

                {/* Tags */}
                <TagManager
                    connectionId={connection.id}
                    tags={connection.tags}
                    workstations={workstations}
                    basePath="/admin/connectivity/opcua"
                    addressLabel={__('Node ID')}
                    addressPlaceholder="ns=2;s=Machine.State"
                />
            </div>
        </>
    );
}

OpcuaShow.layout = (page) => <AppLayout>{page}</AppLayout>;
