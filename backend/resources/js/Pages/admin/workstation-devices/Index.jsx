import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __, timeAgo } from '../../../lib/i18n';

/**
 * MAIN-side roster of shop-floor workstation clients. Rows live-sync via the
 * `workstation_devices` shape; a station is "online" if it heartbeat within the
 * server-configured window. A ticking clock lets a station that goes silent
 * fade to offline without needing a row change.
 */
export default function WorkstationDevicesIndex() {
    const { onlineWindowSeconds = 30, lines = {} } = usePage().props;

    // Re-render every few seconds so online status decays as heartbeats stop.
    const [, setTick] = useState(0);
    useEffect(() => {
        const id = setInterval(() => setTick((t) => t + 1), 5000);
        return () => clearInterval(id);
    }, []);

    const isOnline = (r) => {
        if (!r.last_seen_at) return false;
        const seen = new Date(r.last_seen_at).getTime();
        return Date.now() - seen < onlineWindowSeconds * 1000;
    };

    const columns = [
        {
            key: 'status',
            label: __('Status'),
            render: (r) => {
                const online = isOnline(r);
                return (
                    <span className="inline-flex items-center gap-2">
                        <span
                            className={`inline-block h-2.5 w-2.5 rounded-full ${online ? 'bg-emerald-500' : 'bg-om-muted/50'}`}
                            aria-hidden="true"
                        />
                        <span className={online ? 'text-emerald-600 font-medium' : 'text-om-muted'}>
                            {online ? __('Online') : __('Offline')}
                        </span>
                    </span>
                );
            },
        },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'ip_address', label: __('IP address'), className: 'font-mono text-om-muted' },
        { key: 'line', label: __('Line'), render: (r) => (r.line_id ? lines[r.line_id] ?? `#${r.line_id}` : '—') },
        { key: 'app_version', label: __('Version'), className: 'font-mono text-om-muted', render: (r) => r.app_version || '—' },
        { key: 'last_seen_at', label: __('Last seen'), render: (r) => (r.last_seen_at ? timeAgo(r.last_seen_at) : '—') },
    ];

    const actions = (r) => [
        {
            label: __('Forget'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Forget workstation ":name"? It can re-register later.', { name: r.name }))) {
                    router.delete(`/admin/workstation-devices/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Workstation Devices')} />
            <ResourceTable
                shape="workstation_devices"
                title={__('Workstation Devices')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No workstations have connected yet. Install the OpenMES Workstation client and point it at this server\'s IP.')}
            />
        </>
    );
}

WorkstationDevicesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
